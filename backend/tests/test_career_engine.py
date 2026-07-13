from types import SimpleNamespace
from pathlib import Path

from PIL import Image

from app.models.career_engine import CareerAnalysis, CareerTask, CareerTarget, Evidence
from app.services import career_engine
from app.tasks import career as career_tasks


def register(client, email="student@example.com"):
    return client.post("/api/v1/auth/register", json={"full_name": "Student User", "email": email, "password": "GucluParola123!"})


def headers(client, email="student@example.com"):
    token = client.post("/api/v1/auth/login", data={"username": email, "password": "GucluParola123!"}).json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


def fake_model(content):
    class Model:
        def invoke(self, _messages):
            return SimpleNamespace(content=content)
    return Model()


def test_invoke_sends_schema_and_accepts_fenced_content_blocks(monkeypatch):
    captured = {}
    payload = '```json\n{"decision":"revise","confidence":0.6,"feedback":"Sahiplik kanıtı eksik"}\n```'
    def invoke(messages):
        captured["prompt"] = messages[-1].content
        return SimpleNamespace(content=[{"type": "text", "text": payload}])
    monkeypatch.setattr(career_engine, "ai_configured", lambda: True)
    monkeypatch.setattr(career_engine, "create_chat_model", lambda: SimpleNamespace(invoke=invoke))
    result = career_engine._invoke("kanıtı incele", career_engine.EvidenceReviewAI)
    assert result.decision == "revise"
    assert result.confidence == 0.6
    assert '"decision"' in captured["prompt"]
    assert '"confidence"' in captured["prompt"]
    assert "Zorunlu JSON Schema" in captured["prompt"]


def test_cv_text_is_authenticated_and_queued(client, monkeypatch):
    register(client)
    monkeypatch.setattr(career_tasks.analyze_cv_task, "delay", lambda _analysis_id: None)
    response = client.post("/api/v1/cv/analyze-text", json={"cv_text": "Student Data Analyst SQL Python Excel project experience for two years."}, headers=headers(client))
    assert response.status_code == 202
    assert response.json()["status"] == "queued"


def test_analysis_strict_ai_contains_all_tiers_and_null_current_role(client, monkeypatch):
    register(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    row = CareerAnalysis(id="analysis-1", user_id=1, status="queued", source="text", cv_text="CV text")
    db.add(row)
    db.commit()
    monkeypatch.setattr(career_engine, "ai_configured", lambda: True)
    captured = {}
    response = '{"current_role":null,"profile":{},"skills":[{"name":"SQL","score":80}],"radar":[{"label":"SQL","score":80,"target":90}],"roles":[{"tier":"A","title":"Junior Analyst","readiness":75,"swot":{"strengths":["SQL"],"weaknesses":[],"opportunities":["Portfolio"],"threats":["Competition"]}},{"tier":"B","title":"BI Analyst","readiness":55,"swot":{"strengths":["SQL"],"weaknesses":["Power BI"],"opportunities":["Course"],"threats":["Gap"]}},{"tier":"C","title":"ML Engineer","readiness":25,"swot":{"strengths":[],"weaknesses":["ML"],"opportunities":["Bootcamp"],"threats":["Depth"]}}]}'
    def invoke(messages):
        captured["prompt"] = messages[-1].content
        return SimpleNamespace(content=response)
    monkeypatch.setattr(career_engine, "create_chat_model", lambda: SimpleNamespace(invoke=invoke))
    evidence = [{"task_title": "SQL portfolio", "skill_impacts": ["SQL"], "confidence": 0.94}]
    career_engine.analyze_row(db, row, evidence)
    assert row.status == "ready"
    assert row.current_role is None
    assert {item["tier"] for item in row.career_ladder} == {"A", "B", "C"}
    assert "kronolojik olarak en son iş deneyiminin meslek unvanıdır" in captured["prompt"]
    assert "ulaşılabilecek en yüksek zirve rollerdir" in captured["prompt"]
    assert "SQL portfolio" in captured["prompt"]
    assert '"confidence": 0.94' in captured["prompt"]
    db.close()


def test_target_closes_previous_and_evidence_review_is_confidence_gated(client, monkeypatch):
    register(client)
    monkeypatch.setattr(career_tasks.plan_target_task, "delay", lambda _target_id: None)
    queued_reviews = []
    monkeypatch.setattr(career_tasks.review_evidence_task, "delay", lambda evidence_id: queued_reviews.append(evidence_id))
    auth = headers(client)
    first = client.post("/api/v1/career/targets", json={"title": "Data Analyst"}, headers=auth)
    second = client.post("/api/v1/career/targets", json={"title": "BI Analyst"}, headers=auth)
    assert first.status_code == 202 and second.status_code == 202
    targets = client.get("/api/v1/career/targets", headers=auth).json()
    assert {item["status"] for item in targets} == {"queued", "closed"}

    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    target_id = next(item["id"] for item in targets if item["status"] == "queued")
    task = CareerTask(id="task-1", user_id=1, target_id=target_id, title="SQL case", hint="", status="pending", evidence_required=True, evidence_types=["link"], skill_impacts=["SQL"], training_suggestions=[])
    db.add(task)
    db.commit()
    task_id = task.id
    db.close()

    evidence = client.post(f"/api/v1/career/tasks/{task_id}/evidence", json={"kind": "link", "url": "https://example.com/project"}, headers=auth)
    assert evidence.status_code == 201
    assert queued_reviews == [evidence.json()["id"]]
    assert client.post(f"/api/v1/career/evidence/{evidence.json()['id']}/review", json={}, headers=auth).status_code == 404


def test_image_ocr_and_owner_context_reach_review_prompt(client, monkeypatch, tmp_path):
    register(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    image_dir = tmp_path / "1"
    image_dir.mkdir()
    image = image_dir / "certificate.png"
    Image.new("RGB", (32, 32), "white").save(image)
    target = CareerTarget(id="target-ocr", user_id=1, title="Data Analyst", source="custom", status="active")
    task = CareerTask(id="task-ocr", user_id=1, target_id=target.id, title="Certificate", hint="Certificate title must be visible", status="pending", evidence_types=["file"], skill_impacts=["SQL"])
    evidence = Evidence(id="evidence-ocr", user_id=1, task_id=task.id, kind="file", file_path=str(image), status="pending")
    db.add_all([target, task, evidence])
    db.commit()
    monkeypatch.setattr(career_engine.settings, "UPLOAD_DIR", str(tmp_path))
    monkeypatch.setattr(career_engine, "ai_configured", lambda: True)
    captured = {}
    def invoke(messages):
        captured["prompt"] = messages[-1].content
        return SimpleNamespace(content='{"decision":"revise","confidence":0.4,"feedback":"OCR yetersiz"}')
    monkeypatch.setattr(career_engine, "create_chat_model", lambda: SimpleNamespace(invoke=invoke))
    monkeypatch.setattr(career_engine.subprocess, "run", lambda *args, **kwargs: SimpleNamespace(stdout="SQL Certificate", returncode=0))
    career_engine.review_evidence(db, evidence)
    assert "SQL Certificate" in captured["prompt"]
    assert "Student User" in captured["prompt"]
    assert evidence.status == "revision_required"
    db.close()


def test_private_link_is_never_sent_to_model(client, monkeypatch):
    register(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    target = CareerTarget(id="target-private", user_id=1, title="Data Analyst", source="custom", status="active")
    task = CareerTask(id="task-private", user_id=1, target_id=target.id, title="Project", hint="", status="pending", evidence_types=["link"], skill_impacts=["SQL"])
    evidence = Evidence(id="evidence-private", user_id=1, task_id=task.id, kind="link", url="http://127.0.0.1/admin", status="pending")
    db.add_all([target, task, evidence])
    db.commit()
    career_engine.review_evidence(db, evidence)
    assert evidence.status == "revision_required"
    db.close()


def test_reset_scopes_are_user_owned_and_keep_unselected_domain(client):
    register(client)
    register(client, "other@example.com")
    auth = headers(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    own_analysis = CareerAnalysis(id="reset-analysis", user_id=1, status="ready", source="text", cv_text="CV")
    other_analysis = CareerAnalysis(id="other-analysis", user_id=2, status="ready", source="text", cv_text="CV")
    own_target = CareerTarget(id="reset-target", user_id=1, title="Data Analyst", source="ladder", status="active")
    own_task = CareerTask(id="reset-task", user_id=1, target_id=own_target.id, title="SQL", hint="", status="pending", evidence_types=["link"], skill_impacts=["SQL"])
    own_evidence = Evidence(id="reset-evidence", user_id=1, task_id=own_task.id, kind="link", url="https://example.com", status="pending")
    db.add_all([own_analysis, other_analysis, own_target, own_task, own_evidence])
    db.commit()
    db.close()

    analysis_reset = client.post("/api/v1/career/reset", json={"scope": "analysis"}, headers=auth)
    assert analysis_reset.status_code == 200
    assert analysis_reset.json()["deleted"] == {"analyses": 1, "targets": 0, "tasks": 0, "evidence": 0}
    assert client.get("/api/v1/career/analysis/other-analysis", headers=headers(client, "other@example.com")).status_code == 200

    plan_reset = client.post("/api/v1/career/reset", json={"scope": "plan"}, headers=auth)
    assert plan_reset.status_code == 200
    assert plan_reset.json()["deleted"] == {"analyses": 0, "targets": 1, "tasks": 1, "evidence": 1}
    assert client.get("/api/v1/career/targets", headers=auth).json() == []

    invalid = client.post("/api/v1/career/reset", json={"scope": "everything"}, headers=auth)
    assert invalid.status_code == 422
