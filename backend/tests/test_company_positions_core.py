from datetime import UTC, datetime, timedelta

import pytest
from sqlalchemy import select

from app.core.database import get_db
from app.main import app
from app.models.company_recruiting import CompanyTaskOutbox, RecruitingApplication, RecruitingPosition, RecruitingPositionActivity, RecruitingPositionAiAnalysis, RecruitingPositionCriteriaVersion
from app.models.engagement import CvDocument
from app.models.recruiting import Organization, OrganizationMembership
from app.models.user import User
from app.services.ai_factory import AIProviderError
from app.services.company_outbox import (
    CANDIDATE_ANALYSIS_TASK,
    POSITION_ANALYSIS_TASK,
    claim_outbox_for_processing,
)


PASSWORD = "GucluParola123!"


def _register(client, email: str, name: str) -> User:
    response = client.post("/api/v1/auth/register", json={"full_name": name, "email": email, "password": PASSWORD})
    assert response.status_code == 201
    with next(app.dependency_overrides[get_db]()) as db:
        return db.scalar(select(User).where(User.email == email))


def _token(client, email: str) -> str:
    response = client.post("/api/v1/auth/login", data={"username": email, "password": PASSWORD})
    assert response.status_code == 200
    return response.json()["access_token"]


def _company(client, slug: str = "acme") -> tuple[str, dict[str, str]]:
    user = _register(client, f"owner@{slug}.example.com", "Kurum Sahibi")
    organization_id = f"org-{slug}"
    with next(app.dependency_overrides[get_db]()) as db:
        stored = db.get(User, user.id)
        stored.role = "company"
        db.add(Organization(
            id=organization_id, name="ACME Teknoloji", slug=slug,
            organization_type="employer", size_band="smb", status="active",
            plan_code="growth", billing_email=f"billing@{slug}.example.com", settings={},
        ))
        db.add(OrganizationMembership(
            id=f"membership-{slug}", organization_id=organization_id,
            user_id=user.id, role="owner", status="active",
        ))
        db.commit()
    return organization_id, {
        "Authorization": f"Bearer {_token(client, user.email)}",
        "X-Organization-ID": organization_id,
    }


def _position_payload(status: str = "draft") -> dict:
    return {
        "title": "Backend Developer", "department": "Engineering", "level": "mid",
        "employment_type": "full_time", "workplace_type": "remote", "location": "Istanbul",
        "salary_min": "80000.00", "salary_max": "120000.00", "salary_currency": "TRY",
        "responsibilities": "Laravel servislerini geliştirmek", "must_have_skills": ["Laravel", "SQL"],
        "preferred_skills": ["Redis"], "learnable_skills": ["Kubernetes"],
        "experience_expectation": "3+ yıl backend", "language_work_authorization": "Türkçe, TR çalışma izni",
        "target_start_date": "2026-09-01", "source_text": "Backend ilan metni",
        "ats_terms": ["Screen=Teknik ön eleme", "Phone screen=Telefon görüşmesi"], "ats_notes": "Pozisyona özel teknik eleme",
        "retention_days": 180, "status": status,
    }


def test_position_contract_ats_config_counts_and_canonical_status(client):
    organization_id, headers = _company(client)
    ats = client.patch("/api/v1/company/ats-config", headers=headers, json={
        "provider": "greenhouse", "system_name": "Greenhouse", "terms": ["Screen=İK ön eleme", "Onsite=Ofis görüşmesi"],
        "notes": "Onsite teknik görüşmedir", "candidate_analysis_instructions": "Laravel kanıtlarını öne çıkar",
    })
    assert ats.status_code == 200
    assert ats.json()["provider"] == "greenhouse"
    assert ats.json()["terms"] == ["Screen=İK ön eleme", "Onsite=Ofis görüşmesi"]

    created = client.post("/api/v1/company/positions", headers=headers, json=_position_payload("published"))
    assert created.status_code == 201, created.text
    position = created.json()
    assert position["status"] == "published"
    assert position["public_path"].startswith("/apply/acme/backend-developer-")
    assert position["must_have_skills"] == ["Laravel", "SQL"]

    with next(app.dependency_overrides[get_db]()) as db:
        db.add(RecruitingApplication(
            id="application-count", organization_id=organization_id, position_id=position["id"],
            candidate_name="Aday", candidate_email="aday@example.com", current_stage="shortlisted",
            applied_at=datetime.now(UTC),
        ))
        db.commit()

    listed = client.get("/api/v1/company/positions?status=published", headers=headers)
    assert listed.status_code == 200
    assert listed.json()["items"][0]["application_count"] == 1
    assert listed.json()["items"][0]["shortlisted_count"] == 1

    detail = client.get(f"/api/v1/company/positions/{position['id']}", headers=headers)
    assert detail.status_code == 200
    assert detail.json()["ats_config"]["effective_terms"] == ["Screen=Teknik ön eleme", "Onsite=Ofis görüşmesi", "Phone screen=Telefon görüşmesi"]
    assert detail.json()["counts"]["applications"] == 1
    assert detail.json()["activities"][0]["event_type"] == "position.created"
    assert detail.json()["activities"][0]["actor_name"] == "Kurum Sahibi"

    searched = client.get("/api/v1/company/positions?q=Backend&page=1&page_size=10", headers=headers)
    assert searched.status_code == 200
    assert searched.json()["total"] == 1
    assert searched.json()["status_counts"]["published"] == 1
    copied = client.post(f"/api/v1/company/positions/{position['id']}/copy", headers=headers)
    assert copied.status_code == 201
    assert copied.json()["status"] == "draft"
    assert copied.json()["public_id"] != position["public_id"]
    assert copied.json()["application_count"] == 0

    closed = client.patch(f"/api/v1/company/positions/{position['id']}", headers=headers, json={"status": "closed"})
    assert closed.status_code == 200
    assert client.patch(
        f"/api/v1/company/positions/{position['id']}", headers=headers, json={"status": "published"},
    ).status_code == 409

    _other_org, other_headers = _company(client, "other-assignee")
    other_members = client.get("/api/v1/company/members", headers=other_headers).json()
    assert client.patch(
        f"/api/v1/company/positions/{copied.json()['id']}", headers=headers,
        json={"recruiter_membership_id": other_members["members"][0]["membership_id"]},
    ).status_code == 422


def test_ai_analysis_creates_draft_criteria_and_only_human_approval_activates(client):
    _organization_id, headers = _company(client, "ai")
    position = client.post("/api/v1/company/positions", headers=headers, json=_position_payload()).json()
    response = client.post(f"/api/v1/company/positions/{position['id']}/ai-analysis", headers=headers)
    assert response.status_code == 202
    assert response.json()["status"] == "queued"
    with next(app.dependency_overrides[get_db]()) as db:
        outbox = db.scalar(select(CompanyTaskOutbox).where(CompanyTaskOutbox.aggregate_id == response.json()["id"]))
        assert outbox is not None
        assert outbox.task_name == "company.analyze_position"
        assert outbox.status == "pending"

    detail = client.get(f"/api/v1/company/positions/{position['id']}", headers=headers).json()
    version = detail["criteria_versions"][0]
    assert version["status"] == "draft"
    assert detail["active_criteria_version"] is None

    updated = client.patch(
        f"/api/v1/company/positions/{position['id']}/criteria/{version['id']}", headers=headers,
        json={"criteria": {"must_have": ["Laravel"], "weights": {"Laravel": 60, "SQL": 40}}},
    )
    assert updated.status_code == 200
    approved = client.post(
        f"/api/v1/company/positions/{position['id']}/criteria/{version['id']}/approve", headers=headers,
    )
    assert approved.status_code == 200
    assert approved.json()["status"] == "approved"
    detail = client.get(f"/api/v1/company/positions/{position['id']}", headers=headers).json()
    assert detail["active_criteria_version"]["id"] == version["id"]

    second = client.post(f"/api/v1/company/positions/{position['id']}/ai-analysis", headers=headers).json()
    detail = client.get(f"/api/v1/company/positions/{position['id']}", headers=headers).json()
    second_version = next(item for item in detail["criteria_versions"] if item["id"] == second["criteria_version_id"])
    client.patch(
        f"/api/v1/company/positions/{position['id']}/criteria/{second_version['id']}", headers=headers,
        json={"criteria": {"must_have": ["SQL"], "weights": {"SQL": 100}}},
    )
    assert client.post(
        f"/api/v1/company/positions/{position['id']}/criteria/{second_version['id']}/approve", headers=headers,
    ).status_code == 200
    versions = client.get(f"/api/v1/company/positions/{position['id']}", headers=headers).json()["criteria_versions"]
    assert {item["status"] for item in versions} == {"approved", "superseded"}


def test_public_share_link_and_candidate_application_contract(client, monkeypatch):
    organization_id, headers = _company(client, "public")
    position = client.post("/api/v1/company/positions", headers=headers, json=_position_payload("published")).json()
    link = client.post(f"/api/v1/company/positions/{position['id']}/share-links", headers=headers, json={
        "label": "LinkedIn Temmuz", "channel": "linkedin", "campaign": "backend-temmuz",
    })
    assert link.status_code == 201
    assert link.json()["short_path"].startswith("/a/")
    assert str(position["id"]) not in link.json()["short_path"]

    public = client.get(position["public_path"].replace("/apply", "/api/v1/public/apply"))
    assert public.status_code == 200
    assert public.json()["organization"]["name"] == "ACME Teknoloji"
    renamed = client.patch(f"/api/v1/company/positions/{position['id']}", headers=headers, json={"title": "Senior Backend Developer"})
    assert renamed.status_code == 200
    old_url = position["public_path"].replace("/apply", "/api/v1/public/apply")
    old_readback = client.get(old_url)
    assert old_readback.status_code == 200
    assert old_readback.json()["position"]["public_path"] == renamed.json()["public_path"]
    wrong_organization_url = old_url.replace("/api/v1/public/apply/public/", "/api/v1/public/apply/not-public/")
    assert client.get(wrong_organization_url).status_code == 404
    resolved = client.get(f"/api/v1/public/a/{link.json()['short_code']}")
    assert resolved.status_code == 200
    assert resolved.json()["source"]["channel"] == "linkedin"

    candidate = _register(client, "candidate@example.com", "Aday Kullanıcı")
    candidate_headers = {"Authorization": f"Bearer {_token(client, candidate.email)}"}
    with next(app.dependency_overrides[get_db]()) as db:
        db.add(RecruitingPositionCriteriaVersion(
            id="criteria-public-v1", organization_id=organization_id, position_id=position["id"],
            version_number=1, status="approved",
            criteria={"must_have": ["Laravel", "SQL"], "weights": {"Laravel": 60, "SQL": 40}},
            ai_suggestions={}, approved_by_membership_id="membership-public", approved_at=datetime.now(UTC),
        ))
        db.add(CvDocument(
            id="candidate-cv", user_id=candidate.id, kind="generated", display_name="CV.pdf",
            original_name="CV.pdf", file_path="/tmp/candidate-cv.pdf", file_size=123,
            language="tr", builder_data={"skills": ["Laravel"]}, is_current=True,
        ))
        db.commit()

    other_position = client.post("/api/v1/company/positions", headers=headers, json={**_position_payload("published"), "title": "Other Job"}).json()
    other_link = client.post(f"/api/v1/company/positions/{other_position['id']}/share-links", headers=headers, json={
        "label": "Other LinkedIn", "channel": "linkedin",
    }).json()
    assert client.post(
        f"/api/v1/public/positions/{position['public_id']}/applications", headers=candidate_headers,
        json={
            "cv_document_id": "candidate-cv", "share_link_code": other_link["short_code"],
            "consent": {"accepted": True, "version": "2026-07-20"},
        },
    ).status_code == 422

    submitted = client.post(
        f"/api/v1/public/positions/{position['public_id']}/applications", headers=candidate_headers,
        json={
            "cv_document_id": "candidate-cv", "share_link_code": link.json()["short_code"],
            "consent": {"accepted": True, "version": "2026-07-20", "shared_fields": ["cv", "contact"]},
        },
    )
    assert submitted.status_code == 201, submitted.text
    assert submitted.json()["created"] is True
    with next(app.dependency_overrides[get_db]()) as db:
        outbox = db.scalar(select(CompanyTaskOutbox).where(CompanyTaskOutbox.aggregate_id == submitted.json()["id"]))
        assert outbox is not None
        assert outbox.task_name == "company.analyze_candidate_application"
        assert outbox.status == "pending"
        application = db.get(RecruitingApplication, submitted.json()["id"])
        assert application.original_share_link_id == link.json()["id"]
        assert application.criteria_version_id == "criteria-public-v1"
        assert application.ats_context_snapshot["effective_terms"] == ["Screen=Teknik ön eleme", "Phone screen=Telefon görüşmesi"]
        assert application.application_snapshot["cv"]["id"] == "candidate-cv"
        assert application.application_snapshot["criteria_version"]["id"] == "criteria-public-v1"

        stored_cv = db.get(CvDocument, "candidate-cv")
        stored_criteria = db.get(RecruitingPositionCriteriaVersion, "criteria-public-v1")
        stored_cv.builder_data = {"skills": ["Rust"]}
        stored_criteria.criteria = {"must_have": ["Rust"], "weights": {"Rust": 100}}
        db.commit()
        captured_prompts = []

        def fake_invoke(prompt, schema):
            assert db.in_transaction() is False
            captured_prompts.append(prompt)
            return schema(
                overall_score=74, overall_status="human_review_required",
                criteria_scores=[{"criterion": "Laravel", "score": 80}],
                cv_evidence=[{"criterion": "Laravel", "evidence": "Builder CV project"}],
                uncertainties=["Production scope unclear"], human_review_required=True,
            )

        monkeypatch.setattr("app.services.company_positions._invoke", fake_invoke)
        from app.services.company_positions import analyze_candidate_application
        outbox = db.scalar(select(CompanyTaskOutbox).where(CompanyTaskOutbox.aggregate_id == application.id))
        claim = claim_outbox_for_processing(
            db,
            outbox_id=outbox.id,
            task_name=CANDIDATE_ANALYSIS_TASK,
            aggregate_id=application.id,
            organization_id=organization_id,
        )
        assert claim is not None
        analyze_candidate_application(
            db,
            application,
            outbox_id=claim.id,
            outbox_lock_token=claim.lock_token,
        )
        db.expire_all()
        assert db.get(CompanyTaskOutbox, outbox.id).status == "succeeded"
        assert claim_outbox_for_processing(
            db,
            outbox_id=outbox.id,
            task_name=CANDIDATE_ANALYSIS_TASK,
            aggregate_id=application.id,
            organization_id=organization_id,
        ) is None
        assert application.current_stage == "new"
        assert application.analysis_status == "completed"
        assert application.analysis_result["criteria_version_id"] == "criteria-public-v1"
        assert application.analysis_result["overall_status"] == "human_review_required"
        assert application.analysis_result["criteria_scores"][0]["criterion"] == "Laravel"
        assert "Laravel" in captured_prompts[0]
        assert "Rust" not in captured_prompts[0]
    action = client.patch(
        f"/api/v1/company/positions/{position['id']}/applications/{submitted.json()['id']}",
        headers=headers,
        json={"stage": "shortlisted", "note": "Teknik kanıt güçlü", "decision": "İnsan kısa liste kararı", "idempotency_key": "decision-public-001"},
    )
    assert action.status_code == 200, action.text
    assert action.json()["current_stage"] == "shortlisted"
    repeated_action = client.patch(
        f"/api/v1/company/positions/{position['id']}/applications/{submitted.json()['id']}",
        headers=headers,
        json={"stage": "shortlisted", "note": "Teknik kanıt güçlü", "decision": "İnsan kısa liste kararı", "idempotency_key": "decision-public-001"},
    )
    assert repeated_action.status_code == 200
    detail_after_action = client.get(f"/api/v1/company/positions/{position['id']}", headers=headers).json()
    assert detail_after_action["applications"][0]["stage"] == "shortlisted"
    assert any(item["event_type"] == "application.human_decision" for item in detail_after_action["activities"])
    repeated = client.post(
        f"/api/v1/public/positions/{position['public_id']}/applications", headers=candidate_headers,
        json={"cv_document_id": "candidate-cv", "consent": {"accepted": True, "version": "2026-07-20"}},
    )
    assert repeated.status_code == 200
    assert repeated.json()["created"] is False

    paused = client.patch(f"/api/v1/company/positions/{position['id']}", headers=headers, json={"status": "paused"})
    assert paused.status_code == 200
    assert client.post(
        f"/api/v1/public/positions/{position['public_id']}/applications", headers=candidate_headers,
        json={"cv_document_id": "candidate-cv", "consent": {"accepted": True, "version": "2026-07-20"}},
    ).status_code == 423
    archived = client.delete(f"/api/v1/company/positions/{position['id']}", headers=headers)
    assert archived.status_code == 204
    archived_page = client.get(renamed.json()["public_path"].replace("/apply", "/api/v1/public/apply"))
    assert archived_page.status_code == 200
    assert archived_page.json()["position"]["status"] == "archived"
    assert archived_page.json()["position"]["application_open"] is False


def test_public_position_marketplace_lists_only_open_active_organization_positions(client):
    organization_id, headers = _company(client, "marketplace")

    backend_payload = _position_payload("published")
    backend_payload["title"] = "Backend Developer"
    backend = client.post("/api/v1/company/positions", headers=headers, json=backend_payload).json()

    data_payload = _position_payload("published")
    data_payload.update({
        "title": "Veri Analisti",
        "department": "Data",
        "employment_type": "contract",
        "workplace_type": "onsite",
        "location": "Ankara",
    })
    data_position = client.post("/api/v1/company/positions", headers=headers, json=data_payload).json()

    expired = client.post(
        "/api/v1/company/positions", headers=headers,
        json={**_position_payload("published"), "title": "Süresi Geçen İlan"},
    ).json()
    client.post(
        "/api/v1/company/positions", headers=headers,
        json={**_position_payload("draft"), "title": "Taslak İlan"},
    )

    other_organization_id, other_headers = _company(client, "suspended-marketplace")
    hidden_organization_position = client.post(
        "/api/v1/company/positions", headers=other_headers,
        json={**_position_payload("published"), "title": "Askıdaki Kurum İlanı"},
    ).json()

    with next(app.dependency_overrides[get_db]()) as db:
        now = datetime.now(UTC)
        db.get(RecruitingPosition, backend["id"]).opened_at = now - timedelta(days=2)
        db.get(RecruitingPosition, data_position["id"]).opened_at = now - timedelta(days=1)
        db.get(RecruitingPosition, expired["id"]).application_deadline = now - timedelta(minutes=1)
        db.get(Organization, other_organization_id).status = "suspended"
        db.commit()

    listed = client.get("/api/v1/public/positions")
    assert listed.status_code == 200, listed.text
    assert listed.json()["total"] == 2
    assert [item["position"]["title"] for item in listed.json()["items"]] == ["Veri Analisti", "Backend Developer"]
    assert all(item["position"]["status"] == "published" for item in listed.json()["items"])
    assert all(item["position"]["application_open"] is True for item in listed.json()["items"])
    assert "Askıdaki Kurum İlanı" not in str(listed.json())
    assert hidden_organization_position["public_id"] not in str(listed.json())

    filtered = client.get("/api/v1/public/positions?q=Veri&workplace_type=onsite&employment_type=contract")
    assert filtered.status_code == 200
    assert filtered.json()["total"] == 1
    assert filtered.json()["items"][0]["position"]["public_path"] == data_position["public_path"]

    paged = client.get("/api/v1/public/positions?limit=1&offset=0")
    assert paged.status_code == 200
    assert paged.json()["total"] == 2
    assert paged.json()["has_more"] is True
    assert len(paged.json()["items"]) == 1


def test_candidate_cannot_submit_another_users_cv_or_draft_position(client):
    _organization_id, headers = _company(client, "closed")
    position = client.post("/api/v1/company/positions", headers=headers, json=_position_payload("draft")).json()
    candidate = _register(client, "candidate2@example.com", "İkinci Aday")
    other = _register(client, "other@example.com", "Başka Aday")
    candidate_headers = {"Authorization": f"Bearer {_token(client, candidate.email)}"}
    with next(app.dependency_overrides[get_db]()) as db:
        db.add(CvDocument(
            id="other-cv", user_id=other.id, kind="generated", display_name="CV.pdf",
            original_name="CV.pdf", file_path="/tmp/other-cv.pdf", file_size=123,
            builder_data={}, is_current=True,
        ))
        db.commit()
    assert client.get(f"/api/v1/public/apply/closed/{position['slug']}-{position['public_id']}").status_code == 404
    assert client.post(
        f"/api/v1/public/positions/{position['public_id']}/applications", headers=candidate_headers,
        json={"cv_document_id": "other-cv", "consent": {"accepted": True, "version": "2026-07-20"}},
    ).status_code == 404


def test_admin_identity_cannot_submit_candidate_application(client):
    _organization_id, headers = _company(client, "admin-candidate-boundary")
    position = client.post(
        "/api/v1/company/positions", headers=headers, json=_position_payload("published"),
    ).json()
    admin = _register(client, "admin-candidate@example.com", "Admin Kullanici")
    with next(app.dependency_overrides[get_db]()) as db:
        stored = db.get(User, admin.id)
        stored.role = "admin"
        stored.is_admin = True
        db.commit()
    admin_headers = {"Authorization": f"Bearer {_token(client, admin.email)}"}
    response = client.post(
        f"/api/v1/public/positions/{position['public_id']}/applications",
        headers=admin_headers,
        json={"cv_document_id": "irrelevant", "consent": {"accepted": True, "version": "2026-07-20"}},
    )
    assert response.status_code == 403
    assert response.json()["detail"] == "Candidate account required"


def test_position_ai_invocation_releases_transaction_before_provider_call(client, monkeypatch):
    organization_id, headers = _company(client, "transaction-position")
    position = client.post("/api/v1/company/positions", headers=headers, json=_position_payload()).json()
    response = client.post(f"/api/v1/company/positions/{position['id']}/ai-analysis", headers=headers)
    assert response.status_code == 202
    with next(app.dependency_overrides[get_db]()) as db:
        assert db.scalar(select(CompanyTaskOutbox).where(CompanyTaskOutbox.aggregate_id == response.json()["id"])) is not None

    observed_transactions: list[bool] = []
    active_db = None

    def fake_invoke(_prompt, schema):
        assert active_db is not None
        observed_transactions.append(active_db.in_transaction())
        from app.services.company_positions import PositionAnalysisOutput
        assert schema is PositionAnalysisOutput
        return PositionAnalysisOutput(recommended_weights={"Laravel": 60, "SQL": 40})

    monkeypatch.setattr("app.services.company_positions._invoke", fake_invoke)
    from app.services.company_positions import analyze_position
    with next(app.dependency_overrides[get_db]()) as db:
        active_db = db
        row = db.get(RecruitingPositionAiAnalysis, response.json()["id"])
        outbox = db.scalar(select(CompanyTaskOutbox).where(CompanyTaskOutbox.aggregate_id == row.id))
        claim = claim_outbox_for_processing(
            db,
            outbox_id=outbox.id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id=row.id,
            organization_id=organization_id,
        )
        assert claim is not None
        result = analyze_position(db, row, outbox_id=claim.id, outbox_lock_token=claim.lock_token)
        assert result.status == "completed"
        assert row.status == "completed"
        db.expire_all()
        assert db.get(CompanyTaskOutbox, outbox.id).status == "succeeded"

    assert observed_transactions == [False]
    with next(app.dependency_overrides[get_db]()) as db:
        activities = list(db.scalars(select(RecruitingPositionActivity).where(
            RecruitingPositionActivity.organization_id == organization_id,
            RecruitingPositionActivity.position_id == position["id"],
            RecruitingPositionActivity.event_type == "position.ai_analysis_completed",
        )).all())
        assert len(activities) == 1

    failed_response = client.post(f"/api/v1/company/positions/{position['id']}/ai-analysis", headers=headers)
    assert failed_response.status_code == 202

    def failing_invoke(_prompt, _schema):
        raise RuntimeError("provider unavailable")

    monkeypatch.setattr("app.services.company_positions._invoke", failing_invoke)
    with next(app.dependency_overrides[get_db]()) as db:
        failed_row = db.get(RecruitingPositionAiAnalysis, failed_response.json()["id"])
        failed_result = analyze_position(db, failed_row)
        assert failed_result.status == "failed"
        assert failed_result.error_code == "ai_analysis_failed"

    with next(app.dependency_overrides[get_db]()) as db:
        failed_activities = list(db.scalars(select(RecruitingPositionActivity).where(
            RecruitingPositionActivity.organization_id == organization_id,
            RecruitingPositionActivity.position_id == position["id"],
            RecruitingPositionActivity.event_type == "position.ai_analysis_failed",
        )).all())
        assert len(failed_activities) == 1


def test_provider_network_failure_keeps_outbox_claim_retriable(client, monkeypatch):
    organization_id, headers = _company(client, "provider-retry")
    position = client.post("/api/v1/company/positions", headers=headers, json=_position_payload()).json()
    response = client.post(f"/api/v1/company/positions/{position['id']}/ai-analysis", headers=headers)
    assert response.status_code == 202

    def unavailable(_prompt, _schema):
        raise AIProviderError("provider timeout")

    monkeypatch.setattr("app.services.company_positions._invoke", unavailable)
    from app.services.company_positions import analyze_position
    with next(app.dependency_overrides[get_db]()) as db:
        row = db.get(RecruitingPositionAiAnalysis, response.json()["id"])
        outbox = db.scalar(select(CompanyTaskOutbox).where(CompanyTaskOutbox.aggregate_id == row.id))
        claim = claim_outbox_for_processing(
            db,
            outbox_id=outbox.id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id=row.id,
            organization_id=organization_id,
        )
        assert claim is not None
        with pytest.raises(AIProviderError):
            analyze_position(db, row, outbox_id=claim.id, outbox_lock_token=claim.lock_token)
        db.expire_all()
        assert db.get(CompanyTaskOutbox, outbox.id).status == "processing"
        assert db.get(RecruitingPositionAiAnalysis, row.id).status == "processing"


def test_position_detail_redacts_candidate_data_without_application_permissions(client):
    organization_id, headers = _company(client, "redacted")
    position = client.post("/api/v1/company/positions", headers=headers, json=_position_payload()).json()
    assert client.patch("/api/v1/company/ats-config", headers=headers, json={
        "provider": "custom",
        "notes": "GIZLI ATS NOTU",
        "candidate_analysis_instructions": "GIZLI ANALIZ TALIMATI",
    }).status_code == 200
    with next(app.dependency_overrides[get_db]()) as db:
        db.add(RecruitingApplication(
            id="redacted-application", organization_id=organization_id, position_id=position["id"],
            candidate_name="Gizli Aday", candidate_email="gizli@example.com", current_stage="new",
            analysis_result={"overall_score": 91}, applied_at=datetime.now(UTC),
        ))
        membership = db.get(OrganizationMembership, "membership-redacted")
        membership.role = "viewer"
        membership.permissions = ["dashboard.view", "positions.view"]
        db.commit()

    detail = client.get(f"/api/v1/company/positions/{position['id']}", headers=headers)
    assert detail.status_code == 200
    assert detail.json()["applications"] == []
    assert detail.json()["assessments"] == []
    assert detail.json()["comparison"] == []
    assert detail.json()["ats_config"] is None
    assert detail.json()["members"] == []
    assert "Gizli Aday" not in detail.text
    assert "GIZLI ATS NOTU" not in detail.text
    assert "GIZLI ANALIZ TALIMATI" not in detail.text
