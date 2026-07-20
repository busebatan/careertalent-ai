from datetime import UTC, datetime, timedelta

from sqlalchemy import select

from app.core.database import get_db
from app.core.security import hash_password
from app.main import app
from app.models.company_recruiting import (
    AssessmentUsageLedger,
    RecruitingApplication,
    RecruitingApplicationStageEvent,
    RecruitingAssessment,
    RecruitingScorecard,
)
from app.models.recruiting import Organization, OrganizationMembership
from app.models.user import User


PASSWORD = "GucluParola123!"


def _register(client, email: str, name: str) -> User:
    response = client.post(
        "/api/v1/auth/register",
        json={"full_name": name, "email": email, "password": PASSWORD},
    )
    assert response.status_code == 201
    with next(app.dependency_overrides[get_db]()) as db:
        return db.scalar(select(User).where(User.email == email))


def _headers(client, email: str, organization_id: str) -> dict[str, str]:
    response = client.post(
        "/api/v1/auth/login",
        data={"username": email, "password": PASSWORD},
    )
    assert response.status_code == 200
    return {
        "Authorization": f"Bearer {response.json()['access_token']}",
        "X-Organization-ID": organization_id,
    }


def _company(client, slug: str, email: str) -> tuple[str, int, dict[str, str]]:
    user = _register(client, email, f"{slug.title()} Owner")
    organization_id = f"org-{slug}"
    user_id = user.id
    organization = Organization(
        id=organization_id,
        name=f"{slug.title()} Teknoloji",
        slug=slug,
        organization_type="employer",
        size_band="smb",
        status="active",
        plan_code="growth",
        billing_email=f"billing@{slug}.example.com",
        settings={"recruiting": {"timezone": "Europe/Istanbul", "retention_warning_days": [30, 7, 1]}},
    )
    with next(app.dependency_overrides[get_db]()) as db:
        stored = db.get(User, user_id)
        stored.role = "company"
        db.add(organization)
        db.add(
            OrganizationMembership(
                id=f"membership-{slug}",
                organization_id=organization_id,
                user_id=user_id,
                role="owner",
                status="active",
            )
        )
        db.commit()
    return organization_id, user_id, _headers(client, email, organization_id)


def test_position_crud_is_tenant_scoped_and_dashboard_uses_real_counts(client):
    _first_id, _first_user, first_headers = _company(client, "first", "owner@first.example.com")
    _second_id, _second_user, second_headers = _company(client, "second", "owner@second.example.com")
    tomorrow = (datetime.now(UTC) + timedelta(days=1)).isoformat()

    created = client.post(
        "/api/v1/company/positions",
        headers=first_headers,
        json={
            "title": "Backend Developer",
            "department": "Engineering",
            "employment_type": "full_time",
            "workplace_type": "hybrid",
            "description": "API ekibine katılacak backend geliştirici.",
            "application_deadline": tomorrow,
            "status": "open",
        },
    )
    assert created.status_code == 201
    position_id = created.json()["id"]

    own = client.get("/api/v1/company/positions?status=open", headers=first_headers)
    other = client.get("/api/v1/company/positions?status=open", headers=second_headers)
    assert own.status_code == 200
    assert [item["title"] for item in own.json()["items"]] == ["Backend Developer"]
    assert other.status_code == 200
    assert other.json()["items"] == []

    dashboard = client.get("/api/v1/company/dashboard?period=30d", headers=first_headers)
    assert dashboard.status_code == 200
    assert dashboard.json()["indicators"] == {
        "active_positions": 1,
        "new_applications": 0,
        "assessment_pending": 0,
        "technical_review_pending": 0,
        "shortlisted": 0,
        "assessment_usage": {"used": 0, "quota": None},
    }
    assert dashboard.json()["summary"] == {
        "application_to_assessment_rate": None,
        "assessment_to_interview_rate": None,
        "average_shortlist_hours": None,
        "largest_loss_stage": None,
    }
    assert any(task["type"] == "position_deadline" for task in dashboard.json()["tasks"])

    assert client.patch(
        f"/api/v1/company/positions/{position_id}",
        headers=second_headers,
        json={"title": "Tenant leak"},
    ).status_code == 404
    assert client.delete(
        f"/api/v1/company/positions/{position_id}",
        headers=first_headers,
    ).status_code == 204
    assert client.get("/api/v1/company/dashboard", headers=first_headers).json()["indicators"]["active_positions"] == 0


def test_dashboard_queues_summary_and_usage_are_derived_from_tenant_events(client):
    organization_id, owner_id, headers = _company(client, "acme", "owner@acme.example.com")
    now = datetime.now(UTC)
    position = client.post(
        "/api/v1/company/positions",
        headers=headers,
        json={"title": "QA Engineer", "status": "open"},
    ).json()

    with next(app.dependency_overrides[get_db]()) as db:
        application = RecruitingApplication(
            id="application-1",
            organization_id=organization_id,
            position_id=position["id"],
            candidate_user_id=owner_id,
            candidate_name="Aday Kullanıcı",
            candidate_email="candidate@example.com",
            current_stage="technical_review",
            first_reviewed_at=None,
            applied_at=now - timedelta(days=4),
            retention_expires_at=now + timedelta(days=7),
        )
        db.add(application)
        db.add(
            RecruitingAssessment(
                id="assessment-1",
                organization_id=organization_id,
                application_id=application.id,
                required=True,
                status="completed",
                assigned_at=now - timedelta(days=3),
                completed_at=now - timedelta(days=2),
            )
        )
        db.add(
            RecruitingScorecard(
                id="scorecard-1",
                organization_id=organization_id,
                application_id=application.id,
                reviewer_membership_id="membership-acme",
                scorecard_type="technical",
                status="pending",
                requested_at=now - timedelta(days=2),
            )
        )
        db.add_all([
            RecruitingApplicationStageEvent(
                id="event-assessment",
                organization_id=organization_id,
                position_id=position["id"],
                application_id=application.id,
                from_stage="assessment_in_progress",
                to_stage="technical_review",
                idempotency_key="assessment-complete-1",
                occurred_at=now - timedelta(days=2),
            ),
            AssessmentUsageLedger(
                id="usage-1",
                organization_id=organization_id,
                assessment_id="assessment-1",
                entry_type="consume",
                units=1,
                idempotency_key="consume-assessment-1",
                reason_code="assessment_started",
                occurred_at=now - timedelta(days=3),
            ),
        ])
        db.commit()

    dashboard = client.get("/api/v1/company/dashboard?period=30d", headers=headers)
    assert dashboard.status_code == 200
    payload = dashboard.json()
    assert payload["indicators"]["new_applications"] == 1
    assert payload["indicators"]["technical_review_pending"] == 1
    assert payload["indicators"]["assessment_usage"] == {"used": 1, "quota": None}
    assert payload["summary"]["application_to_assessment_rate"] == 1.0
    assert {task["type"] for task in payload["tasks"]} >= {
        "new_applications",
        "technical_review",
        "scorecard_missing",
        "retention_due",
    }

    candidates = client.get("/api/v1/company/applications?queue=technical_review", headers=headers)
    assert candidates.status_code == 200
    assert candidates.json()["items"][0]["candidate_name"] == "Aday Kullanıcı"
    assert candidates.json()["items"][0]["position_title"] == "QA Engineer"

    usage = client.get("/api/v1/company/assessments", headers=headers)
    assert usage.status_code == 200
    assert usage.json()["usage"] == {"used": 1, "quota": None}
    assert usage.json()["items"][0]["candidate_name"] == "Aday Kullanıcı"


def test_recruiting_read_write_delete_permissions_are_independent(client):
    organization_id, _owner_id, headers = _company(client, "limited", "owner@limited.example.com")
    with next(app.dependency_overrides[get_db]()) as db:
        membership = db.scalar(select(OrganizationMembership).where(
            OrganizationMembership.organization_id == organization_id
        ))
        membership.role = "viewer"
        membership.permissions = ["dashboard.view", "positions.view"]
        db.commit()

    assert client.get("/api/v1/company/positions", headers=headers).status_code == 200
    assert client.post(
        "/api/v1/company/positions",
        headers=headers,
        json={"title": "Forbidden position", "status": "draft"},
    ).status_code == 403
    assert client.get("/api/v1/company/applications", headers=headers).status_code == 403
    assert client.get("/api/v1/company/dashboard?period=invalid", headers=headers).status_code == 422
