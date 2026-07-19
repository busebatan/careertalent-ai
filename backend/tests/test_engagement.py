import json

from sqlalchemy import select

from app.core.database import get_db
from app.main import app
from app.models.career_engine import JobOpportunity
from app.models.engagement import JobApplication
from app.models.user import User
from app.schemas.engagement import ChatReplyAI, InterviewEvaluationAI, InterviewQuestionAI, InterviewQuestionsAI


def register_and_headers(client, email="engagement@example.com"):
    client.post("/api/v1/auth/register", json={"full_name": "Gerçek Kullanıcı", "email": email, "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": email, "password": "GucluParola123!"}).json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


def db_session():
    return next(app.dependency_overrides[get_db]())


def test_profile_is_account_backed_and_never_returns_demo_identity(client):
    auth = register_and_headers(client)
    empty = client.get("/api/v1/career/profile", headers=auth)
    assert empty.status_code == 200
    assert empty.json()["full_name"] == "Gerçek Kullanıcı"
    assert empty.json()["phone"] is None
    assert "Ayşe" not in str(empty.json())

    updated = client.put("/api/v1/career/profile", headers=auth, json={
        "full_name": "Yeni Kullanıcı", "phone": "+90 555 111 22 33", "location": "Ankara",
        "headline": "Ürün Tasarımcısı", "linkedin": "https://linkedin.com/in/yeni",
        "social_links": [{"platform": "Behance", "url": "https://behance.net/yeni"}],
    })
    assert updated.status_code == 200
    assert updated.json()["social_links"][0]["platform"] == "Behance"
    assert client.get("/api/v1/auth/me", headers=auth).json()["full_name"] == "Yeni Kullanıcı"


def test_chat_uses_ai_and_persists_user_and_assistant_messages(client, monkeypatch):
    auth = register_and_headers(client)
    monkeypatch.setattr("app.services.engagement._invoke", lambda _prompt, schema: ChatReplyAI(reply="Hedefine göre ilk SQL görevini tamamla.", suggested_actions=["Görevlere git"]))

    response = client.post("/api/v1/career/chat", headers=auth, json={"message": "Bugün ne yapmalıyım?"})
    assert response.status_code == 201
    assert response.json()["role"] == "assistant"
    assert response.json()["meta"]["suggested_actions"] == ["Görevlere git"]
    history = client.get("/api/v1/career/chat", headers=auth).json()
    assert [item["role"] for item in history] == ["user", "assistant"]
    assert history[0]["content"] == "Bugün ne yapmalıyım?"

    assert client.delete("/api/v1/career/chat", headers=auth).status_code == 204
    assert client.get("/api/v1/career/chat", headers=auth).json() == []


def test_interview_questions_and_scoring_are_ai_backed(client, monkeypatch):
    auth = register_and_headers(client)
    prompts = []
    languages = []

    def fake_invoke(_prompt, schema, language="tr"):
        prompts.append(json.loads(_prompt))
        languages.append(language)
        if schema is InterviewQuestionsAI:
            return InterviewQuestionsAI(questions=[
                InterviewQuestionAI(id="q1", question="SQL optimizasyon örneğin nedir?", competency="SQL", guidance="STAR kullan"),
                InterviewQuestionAI(id="q2", question="Bir paydaş çatışmasını nasıl yönettin?", competency="İletişim", guidance="Somut ol"),
                InterviewQuestionAI(id="q3", question="Bir dashboard başarısını nasıl ölçersin?", competency="Analiz", guidance="Metrik ver"),
            ])
        return InterviewEvaluationAI(score=82, feedback="Somut örnek güçlü.", strengths=["Yapı"], improvements=["Etki metriği ekle"])

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)
    interview = client.post("/api/v1/career/interviews", headers=auth)
    assert interview.status_code == 201
    assert len(interview.json()["questions"]) == 3
    answer = client.post(f"/api/v1/career/interviews/{interview.json()['id']}/answers", headers=auth, json={"question_id": "q1", "answer": "Yavaş sorguyu execution plan ile inceledim, indeks ekleyip süreyi düşürdüm."})
    assert answer.status_code == 201
    assert answer.json()["score"] == 82
    assert answer.json()["improvements"] == ["Etki metriği ekle"]
    assert len(client.get("/api/v1/career/interviews/current", headers=auth).json()["answers"]) == 1
    assert prompts[0]["system_constraint"].startswith("[SISTEM KISITI]")
    assert "Türkçe" in " ".join(prompts[0]["rules"])
    assert "Mentör" in " ".join(prompts[1]["rules"])
    assert languages == ["tr", "tr"]


def test_interview_uses_saved_panel_language_without_request_body(client, monkeypatch):
    auth = register_and_headers(client, "english-panel@example.com")
    db = db_session()
    user = db.scalar(select(User).where(User.email == "english-panel@example.com"))
    assert user is not None
    user.preferred_locale = "en"
    db.commit()
    db.close()
    prompts = []
    languages = []

    def fake_invoke(prompt, _schema, language="tr"):
        prompts.append(json.loads(prompt))
        languages.append(language)
        return InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="Describe a production incident.", competency="Operations", guidance="Use STAR"),
            InterviewQuestionAI(id="q2", question="Explain a design tradeoff.", competency="Architecture", guidance="Be specific"),
            InterviewQuestionAI(id="q3", question="How do you test a migration?", competency="Quality", guidance="Cover failure paths"),
        ])

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)

    response = client.post("/api/v1/career/interviews", headers=auth)

    assert response.status_code == 201
    assert prompts[0]["system_constraint"].startswith("[SYSTEM CONSTRAINT]")
    assert "English" in " ".join(prompts[0]["rules"])
    assert languages == ["en"]


def test_personal_tasks_persist_and_are_user_scoped(client):
    auth = register_and_headers(client)
    created = client.post("/api/v1/career/personal-tasks", headers=auth, json={"title": "Portfolyo notunu düzenle"})
    assert created.status_code == 201
    task_id = created.json()["id"]
    updated = client.patch(f"/api/v1/career/personal-tasks/{task_id}", headers=auth, json={"note": "Cuma günü", "completed": True})
    assert updated.json()["completed"] is True
    assert updated.json()["note"] == "Cuma günü"
    assert client.get("/api/v1/career/personal-tasks", headers=auth).json()[0]["id"] == task_id
    other = register_and_headers(client, "other@example.com")
    assert client.patch(f"/api/v1/career/personal-tasks/{task_id}", headers=other, json={"completed": False}).status_code == 404


def test_ai_task_status_can_be_toggled_without_evidence(client):
    auth = register_and_headers(client)
    db = db_session()
    target = __import__("app.models.career_engine", fromlist=["CareerTarget"]).CareerTarget(
        id="target-toggle",
        user_id=1,
        title="CFO",
        source="ladder",
        status="active",
        localizations={
            "tr": {"title": "CFO", "task_titles": {"task-toggle": "Finansal analiz eğitimi"}},
            "en": {"title": "CFO", "task_titles": {"task-toggle": "Financial analysis course"}},
        },
    )
    task = __import__("app.models.career_engine", fromlist=["CareerTask"]).CareerTask(
        id="task-toggle", user_id=1, target_id=target.id, title="Financial analysis course", hint="Enroll",
        status="pending", evidence_required=True, evidence_types=["link"], skill_impacts=["Finance"],
        training_suggestions=[], localizations={
            "tr": {"title": "Finansal analiz eğitimi", "hint": "Kaydol", "skill_impacts": ["Finans"], "feedback": None},
            "en": {"title": "Financial analysis course", "hint": "Enroll", "skill_impacts": ["Finance"], "feedback": None},
        },
    )
    db.add_all([target, task])
    db.commit()
    db.close()

    completed = client.patch("/api/v1/career/tasks/task-toggle", headers=auth, json={"status": "completed"})
    assert completed.status_code == 200
    assert completed.json()["status"] == "completed"

    reopened = client.patch("/api/v1/career/tasks/task-toggle", headers=auth, json={"status": "pending"})
    assert reopened.status_code == 200
    assert reopened.json()["status"] == "pending"


def test_skill_evidence_can_be_submitted_for_radar_skill_without_existing_task(client, monkeypatch):
    auth = register_and_headers(client)
    db = db_session()
    target = __import__("app.models.career_engine", fromlist=["CareerTarget"]).CareerTarget(
        id="target-skill-ev",
        user_id=1,
        title="CFO",
        source="ladder",
        status="active",
        localizations={
            "tr": {"title": "CFO", "task_titles": {}},
            "en": {"title": "CFO", "task_titles": {}},
        },
    )
    db.add(target)
    db.commit()
    db.close()
    monkeypatch.setattr("app.api.v1.engagement.review_evidence_task.delay", lambda _id: None)

    response = client.post(
        "/api/v1/career/skill-evidence/link",
        headers=auth,
        json={"skill": "ERP Systems", "target_id": "target-skill-ev", "url": "https://github.com/example/erp"},
    )
    assert response.status_code == 201
    body = response.json()
    assert body["skill"] == "ERP Systems"
    assert body["task"]["status"] == "pending"
    assert "ERP Systems" in body["task"]["skill_impacts"]


def test_saved_job_requires_explicit_applied_action(client):
    auth = register_and_headers(client)
    db = db_session()
    job = JobOpportunity(id="job-apply-1", user_id=1, status="ready", title="Data Analyst", company="Acme", saved=True)
    db.add(job); db.commit(); db.close()
    assert client.get("/api/v1/career/applications", headers=auth).json() == []

    applied = client.post("/api/v1/career/jobs/job-apply-1/application", headers=auth)
    assert applied.status_code == 201
    assert applied.json()["stage"] == "applied"
    assert applied.json()["job_id"] == "job-apply-1"
    assert client.post("/api/v1/career/jobs/job-apply-1/application", headers=auth).json()["id"] == applied.json()["id"]

    moved = client.patch(f"/api/v1/career/applications/{applied.json()['id']}", headers=auth, json={"stage": "interview", "next_action": "Salı teknik görüşme"})
    assert moved.json()["stage"] == "interview"
    assert moved.json()["next_action"] == "Salı teknik görüşme"
    db = db_session(); assert db.query(JobApplication).count() == 1; db.close()
