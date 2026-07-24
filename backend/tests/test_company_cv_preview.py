from datetime import datetime, UTC
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import select
from uuid import uuid4

from app.core.database import get_db
from app.main import app
from app.models.company_recruiting import RecruitingApplication, RecruitingPosition
from app.models.recruiting import Organization, OrganizationMembership
from app.models.user import User

PASSWORD = "GucluParola123!"


def _register_user(client: TestClient, email: str, name: str) -> User:
    res = client.post("/api/v1/auth/register", json={"full_name": name, "email": email, "password": PASSWORD})
    assert res.status_code == 201
    with next(app.dependency_overrides[get_db]()) as db:
        return db.scalar(select(User).where(User.email == email))


def test_company_application_cv_preview(client: TestClient):
    user = _register_user(client, "cv_preview_user@example.com", "CV Test User")
    org_id = "org-cv-preview-test"

    with next(app.dependency_overrides[get_db]()) as db:
        stored = db.get(User, user.id)
        stored.role = "company"
        org = Organization(
            id=org_id, name="CV Corp", slug="cv-corp", organization_type="employer",
            size_band="smb", status="active", plan_code="growth", billing_email="billing@cv-corp.example.com"
        )
        membership = OrganizationMembership(
            id="mem-cv-preview", organization_id=org.id, user_id=user.id, role="owner", permissions=["applications.view"], status="active"
        )
        position = RecruitingPosition(
            id="pos-cv-1", organization_id=org.id, title="QA Lead", slug="qa-lead", public_id="QA100", status="published"
        )
        app_id = str(uuid4())
        application = RecruitingApplication(
            id=app_id,
            organization_id=org.id,
            position_id=position.id,
            candidate_user_id=user.id,
            candidate_name="Mustafa Yılmaz",
            candidate_email="mustafa@example.com",
            current_stage="new",
            analysis_status="completed",
            applied_at=datetime.now(UTC),
            application_snapshot={
                "cv": {
                    "display_name": "Mustafa Yilmaz CV.pdf",
                    "language": "tr",
                    "summary": "5 yıl Senior QA tecrübesi."
                }
            }
        )
        db.add(org)
        db.flush()
        db.add_all([membership, position])
        db.flush()
        db.add(application)
        db.commit()

    login_res = client.post("/api/v1/auth/login", data={"username": "cv_preview_user@example.com", "password": PASSWORD})
    token = login_res.json()["access_token"]
    headers = {"Authorization": f"Bearer {token}", "X-Organization-ID": org_id}

    # Test cv-preview HTML snapshot fallback
    res = client.get(f"/api/v1/company/applications/{app_id}/cv-preview", headers=headers)
    assert res.status_code == 200
    assert "Mustafa Yilmaz CV.pdf" in res.text
    assert "5 yıl Senior QA tecrübesi." in res.text
