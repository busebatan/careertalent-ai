"""CV analyze endpoint testleri."""

from io import BytesIO

from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)

_MINIMAL_PDF = b"""%PDF-1.4
1 0 obj<<>>endobj
2 0 obj<</Length 44>>stream
BT /F1 12 Tf 100 700 Td (SQL Python Excel) Tj ET
endstream
endobj
3 0 obj<</Type/Page/Parent 4 0 R/MediaBox[0 0 612 792]/Contents 2 0 R>>endobj
4 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
5 0 obj<</Type/Catalog/Pages 4 0 R>>endobj
xref
0 6
0000000000 65535 f
0000000009 00000 n
0000000032 00000 n
0000000125 00000 n
0000000224 00000 n
0000000280 00000 n
trailer<</Size 6/Root 5 0 R>>
startxref
338
%%EOF"""


def test_cv_analyze_rejects_non_pdf(client):
    client.post("/api/v1/auth/register", json={"full_name": "Ayşe Yılmaz", "email": "ayse@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "ayse@example.com", "password": "GucluParola123!"}).json()["access_token"]
    response = client.post(
        "/api/v1/cv/analyze",
        files={"file": ("cv.txt", b"hello", "text/plain")},
        headers={"Authorization": f"Bearer {token}"},
    )
    assert response.status_code == 422


def test_cv_analyze_accepts_pdf(client, monkeypatch):
    client.post("/api/v1/auth/register", json={"full_name": "Ayşe Yılmaz", "email": "ayse@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "ayse@example.com", "password": "GucluParola123!"}).json()["access_token"]
    monkeypatch.setattr(
        "app.api.v1.cv.extract_text_from_pdf",
        lambda _data: "SQL Python Excel Pandas ile veri analizi deneyimi",
    )
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)

    response = client.post(
        "/api/v1/cv/analyze",
        files={"file": ("cv.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        headers={"Authorization": f"Bearer {token}"},
    )

    assert response.status_code == 202
    data = response.json()
    assert data["status"] == "queued"
    assert data["analysis_id"]
