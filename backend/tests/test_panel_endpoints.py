"""Panel endpoint kontrat testleri."""

import pytest
from fastapi.testclient import TestClient

from app.core.security import get_current_user
from app.main import app
from app.models.user import User

client = TestClient(app)


def panel_user() -> User:
    return User(
        id=1,
        full_name="Panel Test User",
        email="panel-user@example.test",
        hashed_password="not-used",
        is_active=True,
        is_admin=False,
    )


@pytest.fixture(autouse=True)
def authenticated_panel_user():
    app.dependency_overrides[get_current_user] = panel_user
    yield
    app.dependency_overrides.pop(get_current_user, None)


def test_panel_endpoints_require_authentication():
    app.dependency_overrides.pop(get_current_user, None)

    response = client.get("/api/v1/panel/dashboard")

    assert response.status_code == 401
    assert response.json()["detail"] == "Not authenticated"
    app.dependency_overrides[get_current_user] = panel_user


def test_panel_dashboard_endpoint():
    response = client.get("/api/v1/panel/dashboard")

    assert response.status_code == 200
    body = response.json()
    assert body["stats"] == {
        "readiness": 0,
        "career": "",
        "weekly_tasks_total": 0,
        "weekly_tasks_done": 0,
    }
    assert body["weekly_tasks"] == []
    assert body["learning_resources"] == []


def test_panel_feature_endpoints():
    checks = {
        "/api/v1/panel/skill-passport": {
            "passport": {"score": 0, "verified": 0, "total": 0, "items": [], "gaps": []}
        },
        "/api/v1/panel/interview": {"interview": {"questions": [], "rubric": []}},
        "/api/v1/panel/applications": {
            "applications": {
                "metrics": {"active": 0, "interviews": 0, "offers": 0},
                "columns": [],
            }
        },
        "/api/v1/panel/job-radar": {"radar": {"roles": [], "sources": [], "alerts": []}},
        "/api/v1/panel/chat": {"assistant": {"prompts": []}},
        "/api/v1/panel/career-ladder": {"career_ladder": [], "career_tier_meta": {}},
        "/api/v1/panel/job-matches": {"seed_jobs": [], "user_skills": [], "readiness": 0},
    }

    for path, expected in checks.items():
        response = client.get(path)
        assert response.status_code == 200, path
        assert response.json() == expected, path


def test_removed_expert_support_endpoint_is_not_routable():
    response = client.get("/api/v1/panel/mentors")

    assert response.status_code == 404


def test_panel_openapi_exports_response_models():
    response = client.get("/openapi.json")

    assert response.status_code == 200
    schema = response.json()
    dashboard_schema = schema["paths"]["/api/v1/panel/dashboard"]["get"]["responses"]["200"]["content"]["application/json"]["schema"]
    assert dashboard_schema["$ref"].endswith("/DashboardResponse")
    assert "/api/v1/panel/mentors" not in schema["paths"]
    assert "MentorsResponse" not in schema["components"]["schemas"]
    assert "/api/v1/panel/job-matches/analyze" not in schema["paths"]
    assert "/api/v1/panel/target" not in schema["paths"]
    assert schema["components"]["schemas"]["PanelStats"]["properties"]["readiness"]["maximum"] == 100
