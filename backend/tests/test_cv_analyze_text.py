"""Asenkron CV metin alımı kontratı."""


def test_analyze_text_endpoint(client, monkeypatch):
    client.post("/api/v1/auth/register", json={"full_name": "Ayşe Yılmaz", "email": "ayse@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "ayse@example.com", "password": "GucluParola123!"}).json()["access_token"]
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)

    response = client.post(
        "/api/v1/cv/analyze-text",
        json={"cv_text": "Ayşe Yılmaz\nData Analyst\nSQL Python Excel experience for 2 years in analytics projects", "file_name": "ayse-builder.json"},
        headers={"Authorization": f"Bearer {token}"},
    )

    assert response.status_code == 202
    body = response.json()
    assert body["analysis_id"]
    assert body["status"] == "queued"


def test_contact_only_builder_text_is_not_sent_to_ai(client, monkeypatch):
    client.post("/api/v1/auth/register", json={"full_name": "Contact Only", "email": "contact@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "contact@example.com", "password": "GucluParola123!"}).json()["access_token"]
    queued = []
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda analysis_id: queued.append(analysis_id))

    response = client.post(
        "/api/v1/cv/analyze-text",
        json={"cv_text": "Contact Only\ncontact@example.com\n05551234567\nIstanbul", "file_name": "empty-builder.json"},
        headers={"Authorization": f"Bearer {token}"},
    )

    assert response.status_code == 422
    assert "analiz edilebilir" in response.json()["detail"]
    assert queued == []
