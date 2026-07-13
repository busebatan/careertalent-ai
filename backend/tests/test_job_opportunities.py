from app.core.database import get_db
from app.main import app
from app.models.career_engine import CareerAnalysis, JobOpportunity
from app.schemas.career import CvRewriteAI, JobCvSuggestionAI, JobOpportunityAI
from app.services import job_opportunity as service


def register(client, email="jobs@example.com"):
    return client.post("/api/v1/auth/register", json={"full_name": "Job Student", "email": email, "password": "GucluParola123!"})


def headers(client, email="jobs@example.com"):
    token = client.post("/api/v1/auth/login", data={"username": email, "password": "GucluParola123!"}).json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


def db_session():
    return next(app.dependency_overrides[get_db]())


def ready_cv(user_id=1):
    return CareerAnalysis(
        id=f"job-analysis-cv-{user_id}", user_id=user_id, status="ready", source="text",
        cv_text="Data Analyst. SQL ile satış raporları ve Python ile veri temizleme.",
        skills=[{"name": "SQL", "score": 80}, {"name": "Python", "score": 65}], radar=[],
    )


def job_ai():
    return JobOpportunityAI(
        title="Data Analyst", company="Acme", source="Test",
        required_skills=["SQL", "Python", "Power BI"], matched_skills=["SQL", "Python"],
        missing_skills=["Power BI"], match_score=67,
        cv_suggestions=[
            JobCvSuggestionAI(action="rewrite", section="experience", title="SQL etkisini açıkla", reason="İlan SQL arıyor", suggested_text="SQL ile satış raporları hazırladım.", safe_to_apply=True, related_skills=["SQL"]),
            JobCvSuggestionAI(action="develop", section="skills", title="Power BI geliştir", reason="İlanda zorunlu", suggested_text="Power BI eğitimi tamamla.", safe_to_apply=True, related_skills=["Power BI"]),
        ],
    )


def test_job_analysis_requires_current_cv_and_queues_ai(client, monkeypatch):
    register(client)
    auth = headers(client)
    monkeypatch.setattr("app.api.v1.career.analyze_job_task.delay", lambda *_: None)

    missing_cv = client.post("/api/v1/career/jobs/analyze", json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."}, headers=auth)
    assert missing_cv.status_code == 409

    db = db_session(); db.add(ready_cv()); db.commit(); db.close()
    queued = client.post("/api/v1/career/jobs/analyze", json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."}, headers=auth)
    assert queued.status_code == 202
    assert queued.json()["status"] == "queued"
    assert queued.json()["saved"] is False
    assert client.get("/api/v1/career/jobs", headers=auth).json() == []


def test_saved_jobs_are_user_scoped_and_can_be_deleted(client):
    register(client); auth = headers(client)
    register(client, "other@example.com"); other = headers(client, "other@example.com")
    db = db_session()
    db.add_all([
        JobOpportunity(id="mine", user_id=1, status="ready", title="Mine", saved=False),
        JobOpportunity(id="theirs", user_id=2, status="ready", title="Theirs", saved=True),
    ])
    db.commit(); db.close()

    assert client.post("/api/v1/career/jobs/mine/save", headers=auth).json()["saved"] is True
    assert [row["id"] for row in client.get("/api/v1/career/jobs", headers=auth).json()] == ["mine"]
    assert client.get("/api/v1/career/jobs/theirs", headers=auth).status_code == 404
    assert client.delete("/api/v1/career/jobs/theirs", headers=auth).status_code == 404
    assert client.delete("/api/v1/career/jobs/mine", headers=auth).status_code == 204
    assert client.get("/api/v1/career/jobs", headers=auth).json() == []
    assert client.get("/api/v1/career/jobs/theirs", headers=other).status_code == 200


def test_ai_analysis_keeps_skill_contract_and_marks_development_unsafe(client, monkeypatch):
    register(client)
    db = db_session(); cv = ready_cv(); db.add(cv); db.commit()
    row = service.create_job(db, 1, None, "SQL, Python ve Power BI bilen Data Analyst arıyoruz. Güçlü raporlama bekleniyor.")
    monkeypatch.setattr(service, "_invoke", lambda _prompt, _schema: job_ai())
    result = service.analyze_job(db, row, cv)
    assert result.status == "ready"
    assert result.matched_skills == ["SQL", "Python"]
    assert result.missing_skills == ["Power BI"]
    assert result.cv_suggestions[0]["safe_to_apply"] is True
    assert result.cv_suggestions[1]["action"] == "develop"
    assert result.cv_suggestions[1]["safe_to_apply"] is False
    assert all(item["id"] for item in result.cv_suggestions)
    db.close()


def test_apply_rejects_unsafe_and_reanalyzes_new_cv(client, monkeypatch):
    register(client); auth = headers(client)
    db = db_session(); cv = ready_cv(); db.add(cv); db.commit()
    row = service.create_job(db, 1, None, "SQL, Python ve Power BI bilen Data Analyst arıyoruz. Güçlü raporlama bekleniyor.")
    monkeypatch.setattr(service, "_invoke", lambda _prompt, _schema: job_ai())
    service.analyze_job(db, row, cv)
    job_id, cv_id = row.id, cv.id
    safe_id, unsafe_id = row.cv_suggestions[0]["id"], row.cv_suggestions[1]["id"]
    db.close()

    monkeypatch.setattr("app.api.v1.career.apply_job_suggestions_task.delay", lambda *_: None)
    rejected = client.post(f"/api/v1/career/jobs/{job_id}/apply", json={"suggestion_ids": [unsafe_id]}, headers=auth)
    assert rejected.status_code == 422
    accepted = client.post(f"/api/v1/career/jobs/{job_id}/apply", json={"suggestion_ids": [safe_id]}, headers=auth)
    assert accepted.status_code == 202
    assert accepted.json()["apply_status"] == "queued"

    db = db_session(); row = db.get(JobOpportunity, job_id); cv = db.get(CareerAnalysis, cv_id)
    calls = []
    def fake_invoke(_prompt, schema):
        calls.append(schema)
        return CvRewriteAI(revised_cv_text="Data Analyst. SQL ile satış raporları hazırladım ve Python ile veri temizledim.") if schema is CvRewriteAI else job_ai()
    def fake_analyze(_db, analysis, _evidence=None):
        analysis.status = "ready"; analysis.skills = [{"name": "SQL", "score": 85}]; analysis.radar = []
        _db.commit(); _db.refresh(analysis); return analysis
    monkeypatch.setattr(service, "_invoke", fake_invoke)
    monkeypatch.setattr(service, "analyze_row", fake_analyze)
    result = service.apply_suggestions(db, row, [safe_id])
    assert result.apply_status == "ready"
    assert result.result_analysis_id and result.result_analysis_id != cv.id
    assert result.applied_suggestion_ids == [safe_id]
    assert calls == [CvRewriteAI, JobOpportunityAI]
    db.close()


def test_private_listing_url_is_blocked(client, monkeypatch):
    register(client)
    db = db_session(); cv = ready_cv(); db.add(cv); db.commit()
    row = service.create_job(db, 1, "http://127.0.0.1/internal", None)
    monkeypatch.setattr(service, "_invoke", lambda *_: job_ai())
    result = service.analyze_job(db, row, cv)
    assert result.status == "failed"
    assert result.error_code == "invalid_listing"
    db.close()
