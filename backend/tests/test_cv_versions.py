import pytest
from sqlalchemy import select
from app.main import app
from app.core.database import get_db
from app.models.engagement import CandidateCvVersion
from app.models.company_recruiting import (
    RecruitingPosition,
    RecruitingApplication,
    RecruitingApplicationSnapshot,
)
from app.models.career_engine import JobOpportunity
from app.models.recruiting import Organization

PASSWORD = "GucluParola123!"


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
            department="IT",
            status="open",
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

