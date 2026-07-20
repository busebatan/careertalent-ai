import json
from types import SimpleNamespace
from pathlib import Path

from PIL import Image
from sqlalchemy.exc import OperationalError

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


def analysis_localizations(data):
    def localized():
        return {
            "current_role": data.get("current_role"),
            "profile": data.get("profile", {}),
            "skill_names": [item["name"] for item in data.get("skills", [])],
            "radar_labels": [item["label"] for item in data.get("radar", [])],
            "roles": [{
                "title": item["title"],
                "strengths": item["swot"]["strengths"],
                "weaknesses": item["swot"]["weaknesses"],
                "opportunities": item["swot"]["opportunities"],
                "threats": item["swot"]["threats"],
            } for item in data.get("roles", [])],
        }

    return {"tr": localized(), "en": localized()}


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


def test_invoke_retries_once_when_first_structured_output_is_invalid(monkeypatch):
    responses = iter([
        SimpleNamespace(content='{"decision":"revise"}'),
        SimpleNamespace(content='{"decision":"revise","confidence":0.6,"feedback":"Kanıtı güçlendir"}'),
    ])
    calls = []

    def invoke(_messages):
        calls.append(True)
        return next(responses)

    monkeypatch.setattr(career_engine, "ai_configured", lambda: True)
    monkeypatch.setattr(career_engine, "create_chat_model", lambda: SimpleNamespace(invoke=invoke))

    result = career_engine._invoke("kanıtı incele", career_engine.EvidenceReviewAI)

    assert result.feedback == "Kanıtı güçlendir"
    assert len(calls) == 2


def test_cv_text_is_authenticated_and_queued(client, monkeypatch):
    register(client)
    monkeypatch.setattr(career_tasks.analyze_cv_task, "delay", lambda _analysis_id: None)
    response = client.post("/api/v1/cv/analyze-text", json={"cv_text": "Student Data Analyst SQL Python Excel project experience for two years."}, headers=headers(client))
    assert response.status_code == 202
    assert response.json()["status"] == "queued"


def test_cv_database_disconnect_retries_with_exponential_backoff():
    calls = []

    class RetryTask:
        request = SimpleNamespace(retries=1)

        def retry(self, *, exc, countdown):
            calls.append((exc, countdown))
            raise RuntimeError("retry queued")

    class Session:
        def __init__(self):
            self.rolled_back = False

        def rollback(self):
            self.rolled_back = True

    db = Session()
    error = OperationalError("UPDATE career_analyses", {}, Exception("connection lost"))

    try:
        career_tasks.retry_cv_database_disconnect(RetryTask(), db, "analysis-1", error)
    except RuntimeError as exc:
        assert str(exc) == "retry queued"

    assert db.rolled_back is True
    assert calls == [(error, 2)]


def test_cv_database_disconnect_marks_analysis_failed_after_last_retry(client, monkeypatch):
    register(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    row = CareerAnalysis(id="database-failed-analysis", user_id=1, status="running", source="upload", cv_text="SQL Python")
    db.add(row)
    db.commit()
    monkeypatch.setattr(career_tasks, "SessionLocal", lambda: db)

    class ExhaustedTask:
        request = SimpleNamespace(retries=3)

        def retry(self, **_kwargs):
            raise AssertionError("last retry must not queue another task")

    error = OperationalError("UPDATE career_analyses", {}, Exception("connection lost"))
    career_tasks.retry_cv_database_disconnect(ExhaustedTask(), db, row.id, error)

    verification_db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    updated = verification_db.get(CareerAnalysis, "database-failed-analysis")
    assert updated.status == "failed"
    assert updated.error_code == "database_unavailable"
    assert "Veritabanı bağlantısı" in updated.error_message
    verification_db.close()


def test_cv_task_routes_operational_error_to_database_retry(monkeypatch):
    row = CareerAnalysis(id="retry-routed-analysis", user_id=1, status="queued", source="upload", cv_text="SQL Python")
    calls = []

    class Session:
        def scalar(self, _query):
            return row

        def close(self):
            return None

    error = OperationalError("UPDATE career_analyses", {}, Exception("connection lost"))
    monkeypatch.setattr(career_tasks, "SessionLocal", Session)
    monkeypatch.setattr(career_tasks, "analyze_row", lambda _db, _row: (_ for _ in ()).throw(error))
    monkeypatch.setattr(career_tasks, "retry_cv_database_disconnect", lambda task, db, analysis_id, caught: calls.append((task, db, analysis_id, caught)))

    result = career_tasks.analyze_cv_task.run(row.id)

    assert result == row.id
    assert calls[0][2:] == (row.id, error)


def test_analysis_strict_ai_contains_all_tiers_and_null_current_role(client, monkeypatch):
    register(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    row = CareerAnalysis(id="analysis-1", user_id=1, status="queued", source="text", cv_text="CV text")
    db.add(row)
    db.commit()
    monkeypatch.setattr(career_engine, "ai_configured", lambda: True)
    captured = {"prompts": []}
    response = '{"current_role":null,"profile":{},"skills":[{"name":"SQL","score":80}],"radar":[{"label":"SQL","score":80,"target":90}],"roles":[{"tier":"A","title":"Junior Analyst","readiness":75,"swot":{"strengths":["SQL"],"weaknesses":[],"opportunities":["Portfolio"],"threats":["Competition"]}},{"tier":"B","title":"BI Analyst","readiness":55,"swot":{"strengths":["SQL"],"weaknesses":["Power BI"],"opportunities":["Course"],"threats":["Gap"]}},{"tier":"C","title":"ML Engineer","readiness":25,"swot":{"strengths":[],"weaknesses":["ML"],"opportunities":["Bootcamp"],"threats":["Depth"]}}]}'
    def invoke(messages):
        prompt = messages[-1].content
        captured["prompts"].append(prompt)
        content = json.dumps(analysis_localizations(json.loads(response))) if '"skill_names"' in prompt else response
        return SimpleNamespace(content=content)
    monkeypatch.setattr(career_engine, "create_chat_model", lambda: SimpleNamespace(invoke=invoke))
    evidence = [{"task_title": "SQL portfolio", "skill_impacts": ["SQL"], "confidence": 0.94}]
    career_engine.analyze_row(db, row, evidence)
    assert row.status == "ready"
    assert row.current_role is None
    assert {item["tier"] for item in row.career_ladder} == {"A", "B", "C"}
    assert "kronolojik olarak en son iş deneyiminin meslek unvanıdır" in captured["prompts"][0]
    assert "ulaşılabilecek en yüksek zirve rollerdir" in captured["prompts"][0]
    assert "SQL portfolio" in captured["prompts"][0]
    assert '"confidence": 0.94' in captured["prompts"][0]
    db.close()


def test_archived_cv_analysis_clears_old_target_only_after_ai_succeeds(client, monkeypatch):
    register(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    row = CareerAnalysis(id="archive-analysis", user_id=1, status="queued", source="archive_generated", cv_text="SQL Python veri analizi deneyimi")
    target = CareerTarget(id="old-target", user_id=1, title="Eski Hedef", source="ladder", status="active")
    task = CareerTask(id="old-task", user_id=1, target_id=target.id, title="Eski görev", hint="", status="pending", evidence_required=True, evidence_types=["link"], skill_impacts=["SQL"], training_suggestions=[])
    evidence = Evidence(id="old-evidence", user_id=1, task_id=task.id, kind="link", url="https://example.com/work", status="accepted")
    db.add_all([row, target, task, evidence]); db.commit()
    analysis_data = {
        "current_role": "Veri Analisti", "profile": {}, "skills": [{"name": "SQL", "score": 80}],
        "radar": [{"label": "SQL", "score": 80, "target": 90}],
        "roles": [
            {"tier": "A", "title": "Data Analyst", "readiness": 80, "swot": {"strengths": ["SQL"], "weaknesses": [], "opportunities": [], "threats": []}},
            {"tier": "B", "title": "BI Analyst", "readiness": 60, "swot": {"strengths": ["SQL"], "weaknesses": [], "opportunities": [], "threats": []}},
            {"tier": "C", "title": "Data Scientist", "readiness": 30, "swot": {"strengths": [], "weaknesses": ["ML"], "opportunities": [], "threats": []}},
        ],
    }
    monkeypatch.setattr(career_engine, "_invoke", lambda _prompt, schema: (
        career_engine.CareerAnalysisAI.model_validate(analysis_data)
        if schema is career_engine.CareerAnalysisAI
        else career_engine.CareerAnalysisLocalizationsAI.model_validate(analysis_localizations(analysis_data))
    ))

    career_engine.analyze_row(db, row)

    assert row.status == "ready"
    assert db.query(CareerTarget).filter_by(user_id=1).count() == 0
    assert db.query(CareerTask).filter_by(user_id=1).count() == 0
    assert db.query(Evidence).filter_by(user_id=1).count() == 0

    failed = CareerAnalysis(id="failed-archive-analysis", user_id=1, status="queued", source="archive_uploaded", cv_text="SQL Python")
    preserved = CareerTarget(id="preserved-target", user_id=1, title="Korunacak Hedef", source="ladder", status="active")
    db.add_all([failed, preserved]); db.commit()
    monkeypatch.setattr(career_engine, "_invoke", lambda _prompt, _schema: (_ for _ in ()).throw(career_engine.AIProviderError("AI kapalı")))

    career_engine.analyze_row(db, failed)

    assert failed.status == "failed"
    assert db.query(CareerTarget).filter_by(id="preserved-target").count() == 1
    db.close()


def test_current_analysis_keeps_last_ready_result_while_new_analysis_is_not_ready(client):
    register(client)
    auth = headers(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    ready = CareerAnalysis(
        id="ready-analysis",
        user_id=1,
        status="ready",
        source="upload",
        file_name="working.pdf",
        cv_text="SQL",
        profile={},
        skills=[],
        radar=[],
        career_ladder=[],
        localizations={
            "tr": {"current_role": None, "profile": {}, "skills": [], "radar": [], "career_ladder": []},
            "en": {"current_role": None, "profile": {}, "skills": [], "radar": [], "career_ladder": []},
        },
    )
    failed = CareerAnalysis(id="failed-analysis", user_id=1, status="failed", source="archive_generated", file_name="broken.pdf", cv_text="SQL", profile={}, skills=[], radar=[], career_ladder=[])
    db.add_all([ready, failed]); db.commit(); db.close()

    current = client.get("/api/v1/career/analysis/current", headers=auth)

    assert current.status_code == 200
    assert current.json()["id"] == "ready-analysis"
    assert current.json()["file_name"] == "working.pdf"


def test_target_closes_previous_and_evidence_review_is_confidence_gated(client, monkeypatch):
    register(client)
    monkeypatch.setattr(career_tasks.plan_target_task, "delay", lambda _target_id: None)
    queued_reviews = []
    monkeypatch.setattr(career_tasks.review_evidence_task, "delay", lambda evidence_id: queued_reviews.append(evidence_id))
    auth = headers(client)
    first = client.post("/api/v1/career/targets", json={"title": "Data Analyst"}, headers=auth)
    second = client.post("/api/v1/career/targets", json={"title": "BI Analyst"}, headers=auth)
    assert first.status_code == 202 and second.status_code == 202
    targets_response = client.get("/api/v1/career/targets", headers=auth)
    assert targets_response.status_code == 200, targets_response.text
    targets = targets_response.json()
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


def test_target_status_is_owned_and_closed_target_cannot_publish_stale_ai_plan(client, monkeypatch):
    register(client)
    register(client, "other@example.com")
    auth = headers(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    analysis = CareerAnalysis(
        id="plan-analysis",
        user_id=1,
        status="ready",
        source="text",
        cv_text="SQL analyst",
        current_role="Analyst",
        skills=[{"name": "SQL", "score": 80}],
        radar=[],
        localizations={
            "tr": {"current_role": "Analist", "profile": {}, "skills": [{"name": "SQL", "score": 80}], "radar": [], "career_ladder": []},
            "en": {"current_role": "Analyst", "profile": {}, "skills": [{"name": "SQL", "score": 80}], "radar": [], "career_ladder": []},
        },
    )
    target = CareerTarget(
        id="stale-target",
        user_id=1,
        title="Data Scientist",
        source="ladder",
        status="queued",
        localizations={"tr": {"title": "Veri Bilimci"}, "en": {"title": "Data Scientist"}},
    )
    db.add_all([analysis, target]); db.commit()

    def close_during_ai(_prompt, _schema):
        target.status = "closed"
        db.commit()
        return career_engine.CareerPlanAI.model_validate({"target_title": "Data Scientist", "tasks": [{
            "title": "ML projesi", "hint": "Portfolyoya ekle", "evidence_required": True,
            "evidence_types": ["link"], "skill_impacts": ["ML"],
            "training_queries": [],
        }]})

    monkeypatch.setattr(career_engine, "_invoke", close_during_ai)
    result = career_engine.plan_target(db, target)
    assert result.status == "closed"
    assert db.query(CareerTask).filter(CareerTask.target_id == target.id).count() == 0
    db.close()

    assert client.get("/api/v1/career/targets/stale-target", headers=auth).status_code == 200
    assert client.get("/api/v1/career/targets/stale-target", headers=headers(client, "other@example.com")).status_code == 404


def test_ai_plan_prompt_and_tasks_change_with_new_target(client, monkeypatch):
    register(client)
    override = __import__("app.main", fromlist=["app"]).app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    db.add(CareerAnalysis(id="dynamic-plan-analysis", user_id=1, status="ready", source="text", cv_text="SQL analyst", current_role="Analyst", skills=[{"name": "SQL", "score": 80}], radar=[]))
    first = CareerTarget(id="first-plan", user_id=1, title="BI Analyst", source="ladder", status="closed")
    second = CareerTarget(id="second-plan", user_id=1, title="ML Engineer", source="ladder", status="queued")
    db.add_all([first, second]); db.commit()
    captured = {}

    def dynamic_plan(prompt, schema):
        captured[schema] = prompt
        if schema is career_engine.CareerPlanAI:
            return career_engine.CareerPlanAI.model_validate({"target_title": "ML Engineer", "tasks": [{
                "title": "ML model portfolyosu", "hint": "Hedef role özel", "evidence_required": True,
                "evidence_types": ["link"], "skill_impacts": ["Machine Learning"],
                "training_queries": [{"query": "ML Engineer Python model deployment course certificate", "skill": "Machine Learning", "reason": "Hedef rol boşluğu"}],
            }]})
        return career_engine.CareerPlanLocalizationsAI.model_validate({
            "tr": {"target_title": "ML Mühendisi", "tasks": [{"id": "0", "title": "ML model portfolyosu", "hint": "Hedef role özel", "skill_impacts": ["Machine Learning"], "feedback": None}]},
            "en": {"target_title": "ML Engineer", "tasks": [{"id": "0", "title": "Build an ML model portfolio", "hint": "Tailored to the target role", "skill_impacts": ["Machine Learning"], "feedback": None}]},
        })

    monkeypatch.setattr(career_engine, "_invoke", dynamic_plan)
    monkeypatch.setattr(career_engine, "search_training", lambda query, skill, _limit: [{"catalog_id": "web-course", "title": "ML Course", "provider": "example.com", "url": "https://example.com/ml", "skills": [skill], "rank": 1, "source": "web"}])
    career_engine.plan_target(db, second)
    tasks = db.query(CareerTask).filter(CareerTask.target_id == second.id).all()
    assert second.status == "active"
    assert [task.title for task in tasks] == ["ML model portfolyosu"]
    assert tasks[0].training_suggestions[0]["catalog_id"] == "web-course"
    assert tasks[0].training_suggestions[0]["source"] == "web"
    assert '"target_role": "ML Engineer"' in captured[career_engine.CareerPlanAI]
    assert '"target_role": "BI Analyst"' not in captured[career_engine.CareerPlanAI]
    db.close()


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
    other_analysis = CareerAnalysis(
        id="other-analysis",
        user_id=2,
        status="ready",
        source="text",
        cv_text="CV",
        localizations={
            "tr": {"current_role": None, "profile": {}, "skills": [], "radar": [], "career_ladder": []},
            "en": {"current_role": None, "profile": {}, "skills": [], "radar": [], "career_ladder": []},
        },
    )
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
