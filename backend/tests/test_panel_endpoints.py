"""Panel endpoint kontrat testleri."""

from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


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
        "/api/v1/panel/interview": "interview",
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
