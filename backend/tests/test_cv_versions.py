import json
from io import BytesIO

import pytest
from sqlalchemy import select
from app.main import app
from app.core.database import get_db
from app.models.engagement import CandidateCvVersion, CvDocument
from app.models.user import User
from app.models.company_recruiting import (
    RecruitingPosition,
    RecruitingApplication,
    RecruitingApplicationSnapshot,
)
from app.models.career_engine import JobOpportunity
from app.models.recruiting import Organization

PASSWORD = "GucluParola123!"
MINIMAL_PDF = b"%PDF-1.4\n%%EOF"


def test_cv_versions_crud(client):
    # Register and login user
    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Version User", "email": "version@example.com", "password": PASSWORD},
    )
    token = client.post(
        "/api/v1/auth/login",
        data={"username": "version@example.com", "password": PASSWORD},
    ).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}

    # 1. List versions (should be empty)
    response = client.get("/api/v1/cv/versions", headers=headers)
    assert response.status_code == 200
    assert len(response.json()) == 0

    # 2. Create version
    payload = {"personal": {"name": "Test"}}
    response = client.post(
        "/api/v1/cv/versions",
        headers=headers,
        json={
            "version_name": "Backend TR",
            "language": "tr",
            "is_main": True,
            "payload": payload,
        },
    )
    assert response.status_code == 201
    data = response.json()
    assert data["version_name"] == "Backend TR"
    assert data["language"] == "tr"
    assert data["is_main"] is True
    assert data["payload"] == payload
    version_id = data["id"]

    # 3. Create second version (setting is_main=True, which should unset the first one)
    response = client.post(
        "/api/v1/cv/versions",
        headers=headers,
        json={
            "version_name": "Frontend EN",
            "language": "en",
            "is_main": True,
            "payload": payload,
        },
    )
    assert response.status_code == 201
    second_version_id = response.json()["id"]

    # Verify first version is_main is now False
    response = client.get("/api/v1/cv/versions", headers=headers)
    assert response.status_code == 200
    versions = response.json()
    assert len(versions) == 2

    first = next(v for v in versions if v["id"] == version_id)
    second = next(v for v in versions if v["id"] == second_version_id)
    assert first["is_main"] is False
    assert second["is_main"] is True

    # 4. Update version
    response = client.put(
        f"/api/v1/cv/versions/{version_id}",
        headers=headers,
        json={
            "version_name": "Backend TR Updated",
            "is_main": True,
        },
    )
    assert response.status_code == 200
    assert response.json()["version_name"] == "Backend TR Updated"

    # Verify second is now unset
    response = client.get("/api/v1/cv/versions", headers=headers)
    first_updated = next(v for v in response.json() if v["id"] == version_id)
    second_updated = next(v for v in response.json() if v["id"] == second_version_id)
    assert first_updated["is_main"] is True
    assert second_updated["is_main"] is False

    # 5. Delete version
    response = client.delete(f"/api/v1/cv/versions/{second_version_id}", headers=headers)
    assert response.status_code == 204

    # Verify deletion
    response = client.get("/api/v1/cv/versions", headers=headers)
    assert len(response.json()) == 1


def test_uploaded_builder_draft_activation_persists_bilingual_versions_idempotently(client):
    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Import User", "email": "import@example.com", "password": PASSWORD},
    )
    token = client.post(
        "/api/v1/auth/login",
        data={"username": "import@example.com", "password": PASSWORD},
    ).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}

    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == "import@example.com"))
        db.add(CvDocument(
            id="uploaded-builder-1",
            user_id=user.id,
            kind="uploaded",
            display_name="Import User.pdf",
            original_name="Import User.pdf",
            file_path="/tmp/uploaded-builder-1.pdf",
            file_size=321,
            builder_draft_status="ready",
            builder_data={
                "tr": {"personal": {"full_name": "İçe Aktarılan Kullanıcı"}, "skills": []},
                "en": {"personal": {"full_name": "Imported User"}, "skills": []},
                "_meta": {"source_file_name": "Import User.pdf"},
            },
            is_current=True,
        ))
        db.commit()

    response = client.post(
        "/api/v1/cv/documents/uploaded-builder-1/builder-activate",
        headers=headers,
        json={"language": "tr"},
    )
    assert response.status_code == 200
    payload = response.json()
    assert payload["document_id"] == "uploaded-builder-1"
    assert len(payload["versions"]) == 2
    assert next(item for item in payload["versions"] if item["language"] == "tr")["is_main"] is True
    assert next(item for item in payload["versions"] if item["language"] == "en")["is_main"] is False
    assert all(item["source_document_id"] == "uploaded-builder-1" for item in payload["versions"])

    second = client.post(
        "/api/v1/cv/documents/uploaded-builder-1/builder-activate",
        headers=headers,
        json={"language": "en"},
    )
    assert second.status_code == 200
    assert len(second.json()["versions"]) == 2
    assert next(item for item in second.json()["versions"] if item["language"] == "en")["is_main"] is True

    versions = client.get("/api/v1/cv/versions", headers=headers).json()
    assert len(versions) == 2
    assert {item["language"] for item in versions} == {"tr", "en"}
    assert next(item for item in versions if item["language"] == "tr")["payload"]["personal"]["full_name"] == "İçe Aktarılan Kullanıcı"
    assert next(item for item in versions if item["language"] == "en")["payload"]["personal"]["full_name"] == "Imported User"

    documents = client.get("/api/v1/cv/documents", headers=headers).json()
    assert documents[0]["builder_opened"] is True

    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Other User", "email": "other-import@example.com", "password": PASSWORD},
    )
    other_token = client.post(
        "/api/v1/auth/login",
        data={"username": "other-import@example.com", "password": PASSWORD},
    ).json()["access_token"]
    forbidden = client.post(
        "/api/v1/cv/documents/uploaded-builder-1/builder-activate",
        headers={"Authorization": f"Bearer {other_token}"},
        json={"language": "tr"},
    )
    assert forbidden.status_code == 404


def test_generated_builder_draft_save_upserts_one_bilingual_version_group(client, monkeypatch, tmp_path):
    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Draft User", "email": "draft@example.com", "password": PASSWORD},
    )
    token = client.post(
        "/api/v1/auth/login",
        data={"username": "draft@example.com", "password": PASSWORD},
    ).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))

    first_snapshot = {
        "tr": {"personal": {"full_name": "Taslak Kullanıcı"}, "skills": []},
        "en": {"personal": {"full_name": "Draft User"}, "skills": []},
    }
    first = client.post(
        "/api/v1/cv/documents/generated/draft",
        headers=headers,
        files={"file": ("draft.pdf", BytesIO(MINIMAL_PDF), "application/pdf")},
        data={
            "display_name": "Taslak CV.pdf",
            "language": "tr",
            "builder_data": json.dumps(first_snapshot),
        },
    )
    assert first.status_code == 200
    first_payload = first.json()
    assert first_payload["document"]["is_current"] is False
    assert len(first_payload["versions"]) == 2
    assert next(item for item in first_payload["versions"] if item["language"] == "tr")["is_main"] is True
    assert all(
        item["source_document_id"] == first_payload["document"]["id"]
        for item in first_payload["versions"]
    )

    second_snapshot = {
        **first_snapshot,
        "en": {"personal": {"full_name": "Updated Draft User"}, "skills": []},
    }
    second = client.post(
        "/api/v1/cv/documents/generated/draft",
        headers=headers,
        files={"file": ("draft-updated.pdf", BytesIO(MINIMAL_PDF), "application/pdf")},
        data={
            "document_id": first_payload["document"]["id"],
            "active_version_id": first_payload["main_version_id"],
            "display_name": "Taslak CV.pdf",
            "language": "en",
            "builder_data": json.dumps(second_snapshot),
        },
    )
    assert second.status_code == 200
    second_payload = second.json()
    assert second_payload["document"]["id"] == first_payload["document"]["id"]
    assert {item["id"] for item in second_payload["versions"]} == {
        item["id"] for item in first_payload["versions"]
    }
    assert next(item for item in second_payload["versions"] if item["language"] == "en")["is_main"] is True
    assert next(item for item in second_payload["versions"] if item["language"] == "en")["payload"] == second_snapshot["en"]

    versions = client.get("/api/v1/cv/versions", headers=headers).json()
    documents = client.get("/api/v1/cv/documents", headers=headers).json()
    assert len(versions) == 2
    assert len(documents) == 1

    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Other Draft", "email": "other-draft@example.com", "password": PASSWORD},
    )
    other_token = client.post(
        "/api/v1/auth/login",
        data={"username": "other-draft@example.com", "password": PASSWORD},
    ).json()["access_token"]
    forbidden = client.post(
        "/api/v1/cv/documents/generated/draft",
        headers={"Authorization": f"Bearer {other_token}"},
        files={"file": ("forbidden.pdf", BytesIO(MINIMAL_PDF), "application/pdf")},
        data={
            "document_id": first_payload["document"]["id"],
            "display_name": "Forbidden.pdf",
            "language": "tr",
            "builder_data": json.dumps(first_snapshot),
        },
    )
    assert forbidden.status_code == 404


def test_builder_import_notice_dismissal_is_persistent_and_owner_scoped(client):
    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Notice Owner", "email": "notice-owner@example.com", "password": PASSWORD},
    )
    owner_token = client.post(
        "/api/v1/auth/login",
        data={"username": "notice-owner@example.com", "password": PASSWORD},
    ).json()["access_token"]
    owner_headers = {"Authorization": f"Bearer {owner_token}"}

    with next(app.dependency_overrides[get_db]()) as db:
        owner = db.scalar(select(User).where(User.email == "notice-owner@example.com"))
        db.add(CvDocument(
            id="notice-document-1",
            user_id=owner.id,
            kind="uploaded",
            display_name="Notice Owner.pdf",
            original_name="Notice Owner.pdf",
            file_path="/tmp/notice-document-1.pdf",
            file_size=321,
            builder_draft_status="ready",
            builder_data={"tr": {}, "en": {}},
            is_current=True,
        ))
        db.commit()

    listed = client.get("/api/v1/cv/documents", headers=owner_headers)
    assert listed.status_code == 200
    assert listed.json()[0]["builder_import_notice_dismissed"] is False

    dismiss_url = "/api/v1/cv/documents/notice-document-1/builder-import-notice-dismiss"
    dismissed = client.post(dismiss_url, headers=owner_headers)
    assert dismissed.status_code == 200
    assert dismissed.json()["builder_import_notice_dismissed"] is True

    # Repeating the action is safe and keeps the account state dismissed.
    repeated = client.post(dismiss_url, headers=owner_headers)
    assert repeated.status_code == 200
    assert repeated.json()["builder_import_notice_dismissed"] is True
    detail = client.get("/api/v1/cv/documents/notice-document-1", headers=owner_headers)
    assert detail.status_code == 200
    assert detail.json()["builder_import_notice_dismissed"] is True

    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Other Notice User", "email": "notice-other@example.com", "password": PASSWORD},
    )
    other_token = client.post(
        "/api/v1/auth/login",
        data={"username": "notice-other@example.com", "password": PASSWORD},
    ).json()["access_token"]
    forbidden = client.post(
        dismiss_url,
        headers={"Authorization": f"Bearer {other_token}"},
    )
    assert forbidden.status_code == 404


def test_apply_job_creates_snapshot(client, monkeypatch):
    # Setup test user
    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Applicant User", "email": "applicant@example.com", "password": PASSWORD},
    )
    login_res = client.post(
        "/api/v1/auth/login",
        data={"username": "applicant@example.com", "password": PASSWORD},
    ).json()
    token = login_res["access_token"]
    headers = {"Authorization": f"Bearer {token}"}

    # Inject data into database
    with next(app.dependency_overrides[get_db]()) as db:
        # Create organization
        org = Organization(
            id="org-test",
            name="Test Org",
            slug="test-org",
            organization_type="employer",
            size_band="smb",
            billing_email="billing@test.org",
        )
        db.add(org)

        # Create position (B2B RecruitingPosition)
        pos = RecruitingPosition(
            id="pos-test",
            organization_id="org-test",
            title="Backend Developer",
            slug="backend-developer",
            department="IT",
            status="published",
            description="Test description",
        )
        db.add(pos)

        # Create candidate's JobOpportunity matching that position
        job_opp = JobOpportunity(
            id="pos-test",  # match position ID
            user_id=1,  # first user id
            status="ready",
            title="Backend Developer",
            company="Test Org",
            cv_suggestions=[
                {"id": "suggestion-1", "title": "Add FastApi", "action": "add", "reason": "required", "suggested_text": "text", "safe_to_apply": True}
            ],
            apply_status=None,
        )
        db.add(job_opp)
        db.commit()

    # Create a custom CV version for candidate
    cv_ver_response = client.post(
        "/api/v1/cv/versions",
        headers=headers,
        json={
            "version_name": "Developer CV",
            "language": "tr",
            "is_main": False,
            "payload": {"skills": ["Python", "FastAPI"]},
        },
    )
    cv_version_id = cv_ver_response.json()["id"]

    # Mock the suggestions apply celery task
    monkeypatch.setattr("app.api.v1.career.apply_job_suggestions_task.delay", lambda _row_id, _suggestion_ids: None)

    # Apply to the job match passing our CV version ID
    apply_response = client.post(
        "/api/v1/career/jobs/pos-test/apply",
        headers=headers,
        json={
            "suggestion_ids": ["suggestion-1"],
            "cv_version_id": cv_version_id,
        },
    )
    assert apply_response.status_code == 202

    # Verify that the RecruitingApplication and RecruitingApplicationSnapshot are created
    application_id = None
    with next(app.dependency_overrides[get_db]()) as db:
        application = db.scalar(select(RecruitingApplication).where(RecruitingApplication.position_id == "pos-test"))
        assert application is not None
        assert application.candidate_email == "applicant@example.com"
        application_id = application.id

        snapshot = db.scalar(select(RecruitingApplicationSnapshot).where(RecruitingApplicationSnapshot.application_id == application_id))
        assert snapshot is not None
        assert snapshot.payload == {"skills": ["Python", "FastAPI"]}

        # Now test company/B2B reading the snapshot details
        # Let's check company header context needs organization ID
        company_headers = {
            "Authorization": f"Bearer {token}",
            "X-Organization-ID": "org-test",
        }

        # Make a mock owner/membership for organization to bypass company permissions check in tests
        # We need to make sure the user has a membership with the organization
        # Let's inject a membership
        from app.models.recruiting import OrganizationMembership
        membership = OrganizationMembership(
            id="mem-test",
            organization_id="org-test",
            user_id=application.candidate_user_id,
            role="owner",
            permissions=["applications.view"],
            status="active"
        )
        db.add(membership)
        
        # Change user role to company to allow B2B company context authentication
        from app.models.user import User as DBUser
        user = db.get(DBUser, application.candidate_user_id)
        if user:
            user.role = "company"
            
        db.commit()

    # Request snapshot via company API endpoint
    snapshot_response = client.get(
        f"/api/v1/company/applications/{application_id}/snapshot",
        headers=company_headers
    )
    assert snapshot_response.status_code == 200
    assert snapshot_response.json() == {"skills": ["Python", "FastAPI"]}
