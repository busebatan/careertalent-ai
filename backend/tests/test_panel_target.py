"""Panel hedef rol kalıcılığı ve ilan parse testleri."""

from fastapi.testclient import TestClient

from app.main import app
from app.services import panel_target_store

client = TestClient(app)


def test_panel_target_persists_to_backend_store(tmp_path, monkeypatch):
    monkeypatch.setattr(panel_target_store, "STORE_FILE", tmp_path / "panel_targets.json")

    response = client.put(
        "/api/v1/panel/target",
        json={
            "source": "custom",
            "role_id": "custom-product-manager",
            "title": "Product Manager",
            "readiness": 35,
            "gap_count": 4,
            "gaps_summary": "Roadmap, stakeholder, metrics",
            "required_skills": ["Roadmap", "Metrics"],
        },
    )

    assert response.status_code == 200
    assert response.json()["target"]["title"] == "Product Manager"

    get_response = client.get("/api/v1/panel/target")
    assert get_response.status_code == 200
    assert get_response.json()["target"]["role_id"] == "custom-product-manager"


def test_job_listing_parse_uses_html_title(monkeypatch):
    class DummyResponse:
        status_code = 200
        text = """<html><head><meta property='og:title' content='Junior Product Analyst - Acme'></head></html>"""

    monkeypatch.setattr("app.services.job_listing_parser.httpx.get", lambda *args, **kwargs: DummyResponse())

    response = client.post(
        "/api/v1/panel/job-listings/parse",
        json={"url": "https://jobs.example.com/view/junior-product-analyst-123"},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["title"] == "Junior Product Analyst"
    assert body["parsed_from"] == "html"
    assert body["role_id"].startswith("job-")


def test_job_listing_parse_falls_back_to_url(monkeypatch):
    import httpx

    monkeypatch.setattr("app.services.job_listing_parser.httpx.get", lambda *args, **kwargs: (_ for _ in ()).throw(httpx.ConnectError("blocked")))

    response = client.post(
        "/api/v1/panel/job-listings/parse",
        json={"url": "https://jobs.example.com/view/senior-data-analyst-987654"},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["title"] == "senior data analyst"
    assert body["parsed_from"] == "url"


def test_job_listing_parse_extracts_skills_from_html_body(monkeypatch):
    class DummyResponse:
        status_code = 200
        text = """
        <html><head><title>Product Analyst</title><meta name='description' content='Own funnel and retention metrics'></head>
        <body>We need SQL, Python, Power BI, A/B testing, cohort analysis and stakeholder communication.</body></html>
        """

    monkeypatch.setattr("app.services.job_listing_parser.httpx.get", lambda *args, **kwargs: DummyResponse())
    monkeypatch.setattr("app.services.job_listing_parser.ai_configured", lambda: False)

    response = client.post(
        "/api/v1/panel/job-listings/parse",
        json={"url": "https://jobs.example.com/product-analyst"},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["title"] == "Product Analyst"
    assert body["parsed_from"] == "html"
    assert "SQL" in body["required_skills"]
    assert "Power BI" in body["required_skills"]
    assert "A/B Test" in body["required_skills"]
    assert "Product Analytics" in body["required_skills"]


def test_job_listing_parse_extracts_json_ld_job_posting(monkeypatch):
    class DummyResponse:
        status_code = 200
        text = """
        <html><head><title>Backend Developer</title></head>
        <body>
        <script type="application/ld+json">
        {"@type":"JobPosting","title":"Backend Developer","description":"FastAPI, Docker, REST API and PostgreSQL required."}
        </script>
        </body></html>
        """

    monkeypatch.setattr("app.services.job_listing_parser.httpx.get", lambda *args, **kwargs: DummyResponse())
    monkeypatch.setattr("app.services.job_listing_parser.ai_configured", lambda: False)

    response = client.post(
        "/api/v1/panel/job-listings/parse",
        json={"url": "https://jobs.example.com/backend-developer"},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["title"] == "Backend Developer"
    assert body["parsed_from"] == "html"
    assert "FastAPI" in body["required_skills"]
    assert "Docker" in body["required_skills"]
    assert "REST API" in body["required_skills"]
