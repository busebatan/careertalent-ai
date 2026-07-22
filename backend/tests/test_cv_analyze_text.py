"""Asenkron CV metin alımı kontratı."""

from sqlalchemy import select

from app.core.database import get_db
from app.main import app
from app.models.career_engine import CareerAnalysis
from app.schemas.engagement import InterviewQuestionAI, InterviewQuestionsAI


def test_analyze_text_endpoint(client, monkeypatch):
    client.post("/api/v1/auth/register", json={"full_name": "Ayşe Yılmaz", "email": "ayse@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "ayse@example.com", "password": "GucluParola123!"}).json()["access_token"]
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)

    response = client.post(
        "/api/v1/cv/analyze-text",
        json={"cv_text": "Ayşe Yılmaz\nData Analyst\nSQL Python Excel experience for 2 years in analytics projects", "file_name": "ayse-builder.json"},
        headers={"Authorization": f"Bearer {token}"},
    )

    assert response.status_code == 202
    body = response.json()
    assert body["analysis_id"]
    assert body["status"] == "queued"


def test_analyze_text_publish_failure_marks_analysis_failed(client, monkeypatch):
    client.post("/api/v1/auth/register", json={"full_name": "Queue Owner", "email": "queue@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "queue@example.com", "password": "GucluParola123!"}).json()["access_token"]
    monkeypatch.setattr(
        "app.api.v1.cv.analyze_cv_task.delay",
        lambda _analysis_id: (_ for _ in ()).throw(RuntimeError("broker offline")),
    )

    response = client.post(
        "/api/v1/cv/analyze-text",
        json={"cv_text": "Data Analyst SQL Python Excel ile üç yıllık analiz ve raporlama deneyimi", "file_name": "queue.json"},
        headers={"Authorization": f"Bearer {token}"},
    )

    assert response.status_code == 503
    assert response.json()["detail"] == {
        "code": "queue_unavailable",
        "message": "İşlem kuyruğa alınamadı. Lütfen tekrar deneyin.",
    }
    db = next(app.dependency_overrides[get_db]())
    row = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == 1))
    assert row.status == "failed"
    assert row.error_code == "queue_unavailable"
    assert row.error_message == "İşlem kuyruğa alınamadı. Lütfen tekrar deneyin."
    db.close()


def test_contact_only_builder_text_is_not_sent_to_ai(client, monkeypatch):
    client.post("/api/v1/auth/register", json={"full_name": "Contact Only", "email": "contact@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "contact@example.com", "password": "GucluParola123!"}).json()["access_token"]
    queued = []
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda analysis_id: queued.append(analysis_id))

    response = client.post(
        "/api/v1/cv/analyze-text",
        json={"cv_text": "Contact Only\ncontact@example.com\n05551234567\nIstanbul", "file_name": "empty-builder.json"},
        headers={"Authorization": f"Bearer {token}"},
    )

    assert response.status_code == 422
    assert "analiz edilebilir" in response.json()["detail"]
    assert queued == []


def test_text_reanalysis_archives_active_interview_and_keeps_it_in_history(client, monkeypatch):
    client.post("/api/v1/auth/register", json={"full_name": "Interview Owner", "email": "interview@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "interview@example.com", "password": "GucluParola123!"}).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)
    monkeypatch.setattr(
        "app.services.engagement._invoke",
        lambda _prompt, _schema, language="tr": InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="Bir üretim sorununu anlat.", competency="Operasyon"),
            InterviewQuestionAI(id="q2", question="Bir tasarım kararını anlat.", competency="Mimari"),
            InterviewQuestionAI(id="q3", question="Bir migration nasıl test edilir?", competency="Kalite"),
        ]),
    )

    interview = client.post("/api/v1/career/interviews", headers=headers)
    assert interview.status_code == 201

    queued = client.post(
        "/api/v1/cv/analyze-text",
        json={"cv_text": "Data Analyst SQL Python Excel ile üç yıllık analiz ve raporlama deneyimi", "file_name": "new-builder.json"},
        headers=headers,
    )

    assert queued.status_code == 202
    assert client.get("/api/v1/career/interviews/current", headers=headers).json() is None
    history = client.get("/api/v1/career/interviews/history", headers=headers).json()["items"]
    assert history[0]["id"] == interview.json()["id"]
    assert history[0]["status"] == "archived"
