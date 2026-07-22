from datetime import datetime, timezone
import json

import pytest
from sqlalchemy import select

from app.core.database import get_db
from app.main import app
from app.models.career_engine import CareerAnalysis, JobOpportunity
from app.schemas.career import CvBuilderDraftAI, CvRewriteAI, JobCvSuggestionAI, JobOpportunityAI
from app.services import job_opportunity as service
from app.tasks import career as career_tasks


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
    dispatched = []
    monkeypatch.setattr("app.api.v1.career.analyze_job_task.delay", lambda *args: dispatched.append(args))

    missing_cv = client.post("/api/v1/career/jobs/analyze", json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."}, headers=auth)
    assert missing_cv.status_code == 409

    cv = ready_cv()
    cv.file_name = "click-time.pdf"
    cv.current_role = "Click-time Analyst"
    cv.profile = {"summary": "click-time profile"}
    cv.cv_text = "click-time:" + ("x" * 17000)
    source_id = cv.id
    expected_cv_text = cv.cv_text[:16000]
    db = db_session(); db.add(cv); db.commit(); db.close()
    queued = client.post("/api/v1/career/jobs/analyze", json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."}, headers=auth)
    assert queued.status_code == 202
    assert queued.json()["status"] == "queued"
    assert queued.json()["saved"] is False
    listed = client.get("/api/v1/career/jobs", headers=auth).json()
    assert [row["id"] for row in listed] == [queued.json()["id"]]
    assert listed[0]["saved"] is False
    assert len(dispatched) == 1
    job_id, snapshot = dispatched[0]
    assert job_id == queued.json()["id"]
    assert snapshot == {
        "cv_text": expected_cv_text,
        "current_role": "Click-time Analyst",
        "profile": {"summary": "click-time profile"},
        "skills": [{"name": "SQL", "score": 80}, {"name": "Python", "score": 65}],
    }
    assert queued.json()["source_analysis_id"] == source_id
    assert queued.json()["source_cv_file_name"] == "click-time.pdf"


def test_job_analysis_blocks_older_ready_cv_while_latest_cv_is_pending(client, monkeypatch):
    register(client)
    auth = headers(client)
    dispatched = []
    monkeypatch.setattr("app.api.v1.career.analyze_job_task.delay", lambda *args: dispatched.append(args))
    older = ready_cv()
    older.id = "older-ready-cv"
    older.created_at = datetime(2026, 1, 1, tzinfo=timezone.utc)
    pending = ready_cv()
    pending.id = "latest-pending-cv"
    pending.status = "running"
    pending.created_at = datetime(2026, 1, 2, tzinfo=timezone.utc)
    db = db_session(); db.add_all([older, pending]); db.commit(); db.close()

    response = client.post(
        "/api/v1/career/jobs/analyze",
        json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."},
        headers=auth,
    )

    assert response.status_code == 409
    assert dispatched == []
    db = db_session()
    assert db.scalar(select(JobOpportunity).where(JobOpportunity.user_id == 1)) is None
    db.close()


def test_job_publish_failure_marks_committed_row_failed(client, monkeypatch):
    register(client)
    auth = headers(client)
    db = db_session(); db.add(ready_cv()); db.commit(); db.close()

    def unavailable(*_args):
        raise RuntimeError("broker connection refused")

    monkeypatch.setattr("app.api.v1.career.analyze_job_task.delay", unavailable)
    response = client.post(
        "/api/v1/career/jobs/analyze",
        json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."},
        headers=auth,
    )

    assert response.status_code == 503
    assert response.json()["detail"] == {
        "code": "queue_unavailable",
        "message": "İşlem kuyruğa alınamadı. Lütfen tekrar deneyin.",
    }
    db = db_session()
    row = db.scalar(select(JobOpportunity).where(JobOpportunity.user_id == 1))
    assert row is not None
    assert row.status == "failed"
    assert row.error_code == "queue_unavailable"
    assert row.error_message == "İşlem kuyruğa alınamadı. Lütfen tekrar deneyin."
    db.close()


def test_job_snapshot_source_is_tenant_scoped(client, monkeypatch):
    register(client); auth = headers(client)
    register(client, "other-snapshot@example.com")
    dispatched = []
    monkeypatch.setattr("app.api.v1.career.analyze_job_task.delay", lambda *args: dispatched.append(args))
    mine = ready_cv(1)
    mine.file_name = "mine.pdf"
    mine.cv_text = "MY TENANT CV"
    other = ready_cv(2)
    other.file_name = "other.pdf"
    other.cv_text = "OTHER TENANT CV"
    other.created_at = datetime(2027, 1, 1, tzinfo=timezone.utc)
    db = db_session(); db.add_all([mine, other]); db.commit(); db.close()

    response = client.post(
        "/api/v1/career/jobs/analyze",
        json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."},
        headers=auth,
    )

    assert response.status_code == 202
    assert dispatched[0][1]["cv_text"] == "MY TENANT CV"
    assert response.json()["source_analysis_id"] == "job-analysis-cv-1"
    assert response.json()["source_cv_file_name"] == "mine.pdf"


def test_job_worker_uses_dispatched_snapshot_after_source_analysis_is_replaced(client, monkeypatch):
    register(client)
    auth = headers(client)
    dispatched = []
    monkeypatch.setattr("app.api.v1.career.analyze_job_task.delay", lambda *args: dispatched.append(args))
    source = ready_cv()
    source.file_name = "exact-click.pdf"
    source.cv_text = "EXACT CLICK SNAPSHOT SQL Python"
    source.current_role = "Snapshot Role"
    source.profile = {"summary": "snapshot profile"}
    source_id = source.id
    db = db_session(); db.add(source); db.commit(); db.close()

    queued = client.post(
        "/api/v1/career/jobs/analyze",
        json={"job_text": "Data analyst rolü için SQL ve Python bilen ekip arkadaşı arıyoruz."},
        headers=auth,
    )
    assert queued.status_code == 202
    job_id, snapshot = dispatched[0]

    db = db_session()
    db.delete(db.get(CareerAnalysis, source_id))
    replacement = ready_cv()
    replacement.id = "replacement-analysis"
    replacement.cv_text = "REPLACEMENT CV MUST NOT BE USED"
    replacement.current_role = "Replacement Role"
    db.add(replacement)
    db.commit()

    prompts = []
    monkeypatch.setattr(service, "_invoke", lambda prompt, _schema: prompts.append(json.loads(prompt)) or job_ai())
    monkeypatch.setattr(career_tasks, "SessionLocal", lambda: db)
    career_tasks.analyze_job_task.run(job_id, snapshot)

    assert prompts[0]["cv_text"] == "EXACT CLICK SNAPSHOT SQL Python"
    assert prompts[0]["current_role"] == "Snapshot Role"
    assert prompts[0]["profile"] == {"summary": "snapshot profile"}
    verification_db = db_session()
    row = verification_db.get(JobOpportunity, job_id)
    assert row.status == "ready"
    assert row.source_analysis_id == source_id
    assert row.source_cv_file_name == "exact-click.pdf"
    verification_db.close()


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


def test_job_analysis_runs_listing_and_ai_without_an_active_transaction(client, monkeypatch):
    register(client)
    db = db_session(); cv = ready_cv(); db.add(cv); db.commit()
    row = service.create_job(db, 1, None, "SQL, Python ve Power BI bilen Data Analyst arıyoruz. Güçlü raporlama bekleniyor.")

    def fake_listing(source_url, job_text):
        assert not db.in_transaction()
        assert source_url is None
        return job_text

    def fake_invoke(_prompt, _schema):
        assert not db.in_transaction()
        return job_ai()

    monkeypatch.setattr(service, "_listing_text", fake_listing)
    monkeypatch.setattr(service, "_invoke", fake_invoke)

    assert service.analyze_job(db, row, cv).status == "ready"
    db.close()


def test_valid_pasted_job_text_bypasses_unreadable_url(client, monkeypatch):
    register(client)
    db = db_session(); cv = ready_cv(); db.add(cv); db.commit()
    pasted_text = "SQL, Python ve Power BI bilen Data Analyst arıyoruz. Güçlü raporlama bekleniyor."
    row = service.create_job(db, 1, "https://example.com/redirect", pasted_text)

    class UnexpectedHttpClient:
        def __init__(self, **_kwargs):
            raise AssertionError("valid pasted text must not fetch the URL")

    monkeypatch.setattr(service.httpx, "Client", UnexpectedHttpClient)
    monkeypatch.setattr(service, "_invoke", lambda _prompt, _schema: job_ai())

    result = service.analyze_job(db, row, cv)
    assert result.status == "ready"
    assert result.error_code is None
    db.close()


def test_url_only_unreadable_listing_has_safe_failure(client, monkeypatch):
    register(client)
    db = db_session(); cv = ready_cv(); db.add(cv); db.commit()
    row = service.create_job(db, 1, "https://example.com/unreadable", None)

    class BrokenHttpClient:
        def __init__(self, **_kwargs):
            pass

        def __enter__(self):
            return self

        def __exit__(self, *_args):
            return False

        def get(self, *_args, **_kwargs):
            raise OSError("internal transport detail")

    monkeypatch.setattr(service, "_public_host", lambda _hostname: True)
    monkeypatch.setattr(service.httpx, "Client", BrokenHttpClient)

    result = service.analyze_job(db, row, cv)
    assert result.status == "failed"
    assert result.error_code == "invalid_listing"
    assert result.error_message == "İlan URL'si okunamadı; ilan metnini yapıştırın"
    assert "internal transport detail" not in result.error_message
    db.close()


def test_url_only_redirect_asks_for_pasted_text(client, monkeypatch):
    register(client)
    db = db_session(); cv = ready_cv(); db.add(cv); db.commit()
    row = service.create_job(db, 1, "https://example.com/redirect", None)

    class RedirectResponse:
        status_code = 302

    class RedirectHttpClient:
        def __init__(self, **_kwargs):
            pass

        def __enter__(self):
            return self

        def __exit__(self, *_args):
            return False

        def get(self, *_args, **_kwargs):
            return RedirectResponse()

    monkeypatch.setattr(service, "_public_host", lambda _hostname: True)
    monkeypatch.setattr(service.httpx, "Client", RedirectHttpClient)

    result = service.analyze_job(db, row, cv)
    assert result.status == "failed"
    assert result.error_code == "invalid_listing"
    assert result.error_message == "İlan URL'si yönlendiriliyor; ilan metnini yapıştırın"
    db.close()


def test_unexpected_job_task_failure_is_persisted_and_reraised(client, monkeypatch):
    register(client)
    task_db = db_session()
    task_db.add(JobOpportunity(id="unexpected-job", user_id=1, status="running", job_text="Yeterince uzun ilan metni"))
    task_db.commit()
    monkeypatch.setattr(career_tasks, "SessionLocal", lambda: task_db)
    monkeypatch.setattr(career_tasks, "analyze_job", lambda *_args: (_ for _ in ()).throw(RuntimeError("provider exploded")))

    with pytest.raises(RuntimeError, match="provider exploded"):
        career_tasks.analyze_job_task.run("unexpected-job")

    verification_db = db_session()
    failed = verification_db.get(JobOpportunity, "unexpected-job")
    assert failed.status == "failed"
    assert failed.error_code == "analysis_failed"
    assert failed.error_message == "İlan analizi beklenmeyen bir hata nedeniyle tamamlanamadı. Lütfen tekrar deneyin."
    verification_db.close()


def test_latest_analysis_returns_any_status_and_is_user_scoped(client, monkeypatch):
    register(client); auth = headers(client)
    register(client, "latest-other@example.com"); other_auth = headers(client, "latest-other@example.com")
    db = db_session()
    db.add_all([
        CareerAnalysis(
            id="older-ready", user_id=1, status="ready", source="text", current_role="Raw older",
            cv_text="SQL", skills=[], radar=[], created_at=datetime(2026, 1, 1, tzinfo=timezone.utc),
        ),
        CareerAnalysis(
            id="latest-pending", user_id=1, status="running", source="text", current_role="Raw pending",
            cv_text="SQL", skills=[], radar=[], created_at=datetime(2026, 1, 2, tzinfo=timezone.utc),
        ),
        CareerAnalysis(
            id="other-latest", user_id=2, status="ready", source="text", current_role="Other user",
            cv_text="Python", skills=[], radar=[], created_at=datetime(2026, 1, 3, tzinfo=timezone.utc),
        ),
    ])
    db.commit(); db.close()

    locale_calls = []
    monkeypatch.setattr(
        "app.api.v1.career._localized_locale",
        lambda *_args, **_kwargs: locale_calls.append(_kwargs) or "en",
    )

    pending = client.get("/api/v1/career/analysis/latest", headers=auth)
    assert pending.status_code == 200
    assert pending.json()["id"] == "latest-pending"
    assert pending.json()["current_role"] == "Raw pending"
    assert locale_calls == []
    assert client.get("/api/v1/career/analysis/latest", headers=other_auth).json()["id"] == "other-latest"
    assert len(locale_calls) == 1

    db = db_session()
    ready = db.get(CareerAnalysis, "latest-pending")
    ready.status = "ready"
    ready.localizations = {
        "en": {
            "current_role": "Localized latest",
            "profile": {}, "skills": [], "radar": [], "career_ladder": [],
        }
    }
    db.commit(); db.close()

    localized = client.get("/api/v1/career/analysis/latest", headers=auth)
    assert localized.json()["current_role"] == "Localized latest"
    assert len(locale_calls) == 2


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


def test_approved_job_suggestions_create_editable_non_main_cv_version(client, monkeypatch):
    register(client); auth = headers(client)
    db = db_session(); cv = ready_cv(); db.add(cv)
    job = JobOpportunity(
        id="chat-job-cv", user_id=1, status="ready", title="Data Analyst", company="Acme",
        job_text="SQL ve Python bilen, satış dashboardları geliştirecek Data Analyst arıyoruz.",
        required_skills=["SQL", "Python"], matched_skills=["SQL"], missing_skills=["Python"],
        cv_suggestions=[{
            "id": "safe-1", "action": "rewrite", "section": "experience",
            "title": "SQL etkisini görünür yap", "reason": "İlan SQL bekliyor",
            "suggested_text": "SQL ile satış raporları hazırladım.", "safe_to_apply": True,
        }],
    )
    db.add(job); db.commit(); db.close()
    monkeypatch.setattr(service, "_invoke", lambda _prompt, schema, language="tr": CvBuilderDraftAI(
        personal={"full_name": "Job Student", "email": "jobs@example.com", "summary": "Veri analisti"},
        education=[{"institution": "Example University", "degree": "Statistics"}],
        experience=[{"organization": "Acme", "title": "Analist", "bullets": ["SQL ile satış raporları hazırladım."]}],
        skills=[{"category": "Teknik", "items": "SQL"}],
        projects=[], certificates=[],
    ) if schema is CvBuilderDraftAI else None)

    response = client.post(
        "/api/v1/career/jobs/chat-job-cv/cv-version",
        headers=auth,
        json={"suggestion_ids": ["safe-1"], "source_cv_version_id": None},
    )

    assert response.status_code == 201
    assert response.json()["version_name"] == "Data Analyst için CV"
    assert response.json()["is_main"] is False
    assert response.json()["payload"]["experience"][0]["id"]
    assert response.json()["payload"]["education"][0]["institution"] == "Example University"
    assert response.json()["payload"]["enabledOptional"] == []
    assert response.json()["payload"]["optional"] == {}

    rejected = client.post(
        "/api/v1/career/jobs/chat-job-cv/cv-version",
        headers=auth,
        json={"suggestion_ids": ["missing"], "source_cv_version_id": None},
    )
    assert rejected.status_code == 422

    register(client, "other-source@example.com"); other_auth = headers(client, "other-source@example.com")
    other_version = client.post(
        "/api/v1/cv/versions", headers=other_auth,
        json={"version_name": "Other CV", "language": "tr", "is_main": False, "payload": {"personal": {}}},
    ).json()
    cross_user_source = client.post(
        "/api/v1/career/jobs/chat-job-cv/cv-version",
        headers=auth,
        json={"suggestion_ids": ["safe-1"], "source_cv_version_id": other_version["id"]},
    )
    assert cross_user_source.status_code == 422
