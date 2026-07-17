import httpx

from app.services import education_search


def test_tavily_search_normalizes_live_results(monkeypatch):
    monkeypatch.setattr(education_search.settings, "EDUCATION_SEARCH_PROVIDER", "tavily")
    monkeypatch.setattr(education_search.settings, "TAVILY_API_KEY", "configured-test-key")
    monkeypatch.setattr(education_search, "_safe_public_url", lambda url: url.startswith("https://"))
    captured = {}

    def fake_post(url, headers, json, timeout):
        captured.update({"url": url, "headers": headers, "json": json, "timeout": timeout})
        return httpx.Response(200, json={"results": [{"title": "Official SQL Course", "url": "https://learn.example.org/sql", "content": "Certificate and exercises"}]}, request=httpx.Request("POST", url))

    monkeypatch.setattr(education_search.httpx, "post", fake_post)
    results = education_search.search_training("SQL analyst certificate course", "SQL")
    assert captured["url"] == "https://api.tavily.com/search"
    assert captured["json"]["include_raw_content"] is False
    assert results[0]["source"] == "web"
    assert results[0]["skills"] == ["SQL"]
    assert results[0]["provider"] == "learn.example.org"


def test_search_requires_configured_provider_key(monkeypatch):
    monkeypatch.setattr(education_search.settings, "EDUCATION_SEARCH_PROVIDER", "tavily")
    monkeypatch.setattr(education_search.settings, "TAVILY_API_KEY", "")
    try:
        education_search.search_training("Power BI official course", "Power BI")
    except education_search.TrainingSearchUnavailable as exc:
        assert "yapılandırılmamış" in str(exc)
    else:
        raise AssertionError("missing search key must not silently return demo training")


def test_private_or_non_https_results_are_rejected(monkeypatch):
    monkeypatch.setattr(education_search.settings, "EDUCATION_SEARCH_PROVIDER", "brave")
    monkeypatch.setattr(education_search.settings, "BRAVE_SEARCH_API_KEY", "configured-test-key")
    monkeypatch.setattr(education_search, "_brave", lambda *_: [
        {"title": "Local", "url": "http://127.0.0.1/course", "snippet": "private"},
        {"title": "Public", "url": "https://example.com/course", "snippet": "public"},
    ])
    monkeypatch.setattr(education_search, "_safe_public_url", lambda url: url == "https://example.com/course")
    results = education_search.search_training("course", "Skill")
    assert [item["title"] for item in results] == ["Public"]


def test_duckduckgo_adapter_extracts_direct_training_urls(monkeypatch):
    monkeypatch.setattr(education_search.settings, "EDUCATION_SEARCH_PROVIDER", "duckduckgo")
    html = '<a class="result__a" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Flearn.example.org%2Fcourse">Official Data Course</a>'
    monkeypatch.setattr(education_search.httpx, "get", lambda *args, **kwargs: httpx.Response(200, text=html, request=httpx.Request("GET", "https://html.duckduckgo.com/html/")))
    monkeypatch.setattr(education_search, "_safe_public_url", lambda url: url == "https://learn.example.org/course")
    results = education_search.search_training("data course", "Data")
    assert results[0]["title"] == "Official Data Course"
    assert results[0]["url"] == "https://learn.example.org/course"
