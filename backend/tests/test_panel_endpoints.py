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
        full_name="Ayşe Yılmaz",
        email="ayse@example.com",
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
    assert body["stats"]["career"] == "Veri Analisti"
    assert body["weekly_tasks"]
    assert body["learning_resources"]


def test_panel_feature_endpoints():
    checks = {
        "/api/v1/panel/skill-passport": "passport",
        "/api/v1/panel/applications": "applications",
        "/api/v1/panel/job-radar": "radar",
        "/api/v1/panel/mentors": "mentors",
        "/api/v1/panel/chat": "assistant",
        "/api/v1/panel/career-ladder": "career_ladder",
        "/api/v1/panel/job-matches": "seed_jobs",
    }

    for path, key in checks.items():
        response = client.get(path)
        assert response.status_code == 200, path
        assert response.json()[key], path


def test_legacy_panel_interview_endpoint_is_removed():
    response = client.get("/api/v1/panel/interview")

    assert response.status_code == 404
    assert "/api/v1/panel/interview" not in client.get("/openapi.json").json()["paths"]


def test_panel_job_match_analyze_endpoint():
    response = client.post(
        "/api/v1/panel/job-matches/analyze",
        json={"url": "https://www.linkedin.com/jobs/view/data-analyst-remote-123456"},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["job"]["match_score"] >= 50
    assert body["job"]["matched_skills"]


def test_panel_openapi_exports_response_models():
    response = client.get("/openapi.json")

    assert response.status_code == 200
    schema = response.json()
    dashboard_schema = schema["paths"]["/api/v1/panel/dashboard"]["get"]["responses"]["200"]["content"]["application/json"]["schema"]
    analyze_schema = schema["paths"]["/api/v1/panel/job-matches/analyze"]["post"]["responses"]["200"]["content"]["application/json"]["schema"]

    assert dashboard_schema["$ref"].endswith("/DashboardResponse")
    assert analyze_schema["$ref"].endswith("/JobMatchAnalyzeResponse")
    assert "JobMatch" in schema["components"]["schemas"]
    assert schema["components"]["schemas"]["PanelStats"]["properties"]["readiness"]["maximum"] == 100
