"""CV analyze endpoint testleri."""

from io import BytesIO

from fastapi.testclient import TestClient
from sqlalchemy import select, text

from app.main import app
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence
from app.models.engagement import CvDocument, PersonalTask

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


def test_cv_analyze_accepts_pdf_and_tracks_current_upload(client, monkeypatch, tmp_path):
    client.post("/api/v1/auth/register", json={"full_name": "Ayşe Yılmaz", "email": "ayse@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "ayse@example.com", "password": "GucluParola123!"}).json()["access_token"]
    monkeypatch.setattr(
        "app.api.v1.cv.extract_text_from_pdf",
        lambda _data: "SQL Python Excel Pandas ile veri analizi deneyimi",
    )
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))

    response = client.post(
        "/api/v1/cv/analyze",
        files={"file": ("cv.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        headers={"Authorization": f"Bearer {token}"},
    )

    assert response.status_code == 202
    data = response.json()
    assert data["status"] == "queued"
    assert data["analysis_id"]
    documents = client.get("/api/v1/cv/documents", headers={"Authorization": f"Bearer {token}"}).json()
    assert len(documents) == 1
    assert documents[0]["kind"] == "uploaded"
    assert documents[0]["is_current"] is True
    first_id = documents[0]["id"]
    first_analysis = client.get(f"/api/v1/career/analysis/{data['analysis_id']}", headers={"Authorization": f"Bearer {token}"}).json()
    assert first_analysis["cv_document_id"] == first_id
    assert first_analysis["file_name"] == "cv.pdf"
    assert first_analysis["source"] == "upload"

    client.post("/api/v1/auth/register", json={"full_name": "Other Owner", "email": "other@example.com", "password": "GucluParola123!"})
    other_token = client.post("/api/v1/auth/login", data={"username": "other@example.com", "password": "GucluParola123!"}).json()["access_token"]
    other_headers = {"Authorization": f"Bearer {other_token}"}
    other_path = tmp_path / "2" / "cv" / "other-document.pdf"
    other_path.parent.mkdir(parents=True)
    other_path.write_bytes(_MINIMAL_PDF)

    override = app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    target = CareerTarget(id="old-target", user_id=1, title="Financial Analyst", source="ladder", status="active")
    task = CareerTask(id="old-task", user_id=1, target_id=target.id, title="Excel", hint="", status="completed", evidence_types=["link"], skill_impacts=["Excel"])
    evidence = Evidence(id="old-evidence", user_id=1, task_id=task.id, kind="link", url="https://example.com", status="accepted")
    other_target = CareerTarget(
        id="other-target",
        user_id=2,
        title="Backend Developer",
        source="ladder",
        status="active",
        localizations={
            "tr": {"title": "Backend Geliştirici", "task_titles": {"other-task": "FastAPI"}},
            "en": {"title": "Backend Developer", "task_titles": {"other-task": "FastAPI"}},
        },
    )
    other_task = CareerTask(
        id="other-task",
        user_id=2,
        target_id=other_target.id,
        title="FastAPI",
        hint="",
        status="pending",
        evidence_types=["link"],
        skill_impacts=["Python"],
        localizations={
            "tr": {"title": "FastAPI", "hint": "", "skill_impacts": ["Python"], "feedback": None},
            "en": {"title": "FastAPI", "hint": "", "skill_impacts": ["Python"], "feedback": None},
        },
    )
    db.add_all([
        target, task, evidence,
        PersonalTask(id="linked-personal-task", user_id=1, target_id=target.id, title="Portfolyo notunu düzenle", completed=False),
        CareerAnalysis(
            id="other-analysis",
            user_id=2,
            status="ready",
            source="upload",
            cv_text="Python FastAPI",
            current_role="Developer",
            localizations={
                "tr": {"current_role": "Geliştirici", "profile": {}, "skills": [], "radar": [], "career_ladder": []},
                "en": {"current_role": "Developer", "profile": {}, "skills": [], "radar": [], "career_ladder": []},
            },
        ),
        other_target, other_task,
        CvDocument(id="other-document", user_id=2, kind="uploaded", display_name="other.pdf", original_name="other.pdf", file_path=str(other_path), file_size=len(_MINIMAL_PDF), is_current=True),
    ])
    db.commit()
    db.close()

    second = client.post(
        "/api/v1/cv/analyze",
        files={"file": ("job-specific.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        headers={"Authorization": f"Bearer {token}"},
    )
    assert second.status_code == 202
    documents = client.get("/api/v1/cv/documents", headers={"Authorization": f"Bearer {token}"}).json()
    assert len(documents) == 2
    assert [item["display_name"] for item in documents if item["is_current"]] == ["job-specific.pdf"]
    assert next(item for item in documents if item["id"] == first_id)["is_current"] is False
    assert (tmp_path / "1" / "cv" / f"{first_id}.pdf").exists()
    assert client.get(f"/api/v1/career/analysis/{data['analysis_id']}", headers={"Authorization": f"Bearer {token}"}).status_code == 404
    assert client.get("/api/v1/career/targets", headers={"Authorization": f"Bearer {token}"}).json() == []
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    linked_personal_task = db.get(PersonalTask, "linked-personal-task")
    assert linked_personal_task is not None
    assert linked_personal_task.target_id is None
    db.close()
    assert client.get("/api/v1/cv/documents", headers=other_headers).json()[0]["id"] == "other-document"
    assert client.get("/api/v1/career/analysis/other-analysis", headers=other_headers).status_code == 200
    assert client.get("/api/v1/career/targets", headers=other_headers).json()[0]["id"] == "other-target"
    assert other_path.exists()
    profile = client.get("/api/v1/career/profile", headers={"Authorization": f"Bearer {token}"}).json()
    assert profile["uploaded_cv"]["name"] == "job-specific.pdf"
    current_id = next(item["id"] for item in documents if item["is_current"])
    rejected = client.post("/api/v1/cv/analyze", files={"file": ("not-a-cv.txt", b"invalid", "text/plain")}, headers={"Authorization": f"Bearer {token}"})
    assert rejected.status_code == 422
    current_documents = client.get("/api/v1/cv/documents", headers={"Authorization": f"Bearer {token}"}).json()
    assert [item["id"] for item in current_documents if item["is_current"]] == [current_id]
    assert client.get(f"/api/v1/career/analysis/{second.json()['analysis_id']}", headers={"Authorization": f"Bearer {token}"}).status_code == 200
    assert client.patch(f"/api/v1/cv/documents/{current_id}/archive", headers={"Authorization": f"Bearer {token}"}).status_code == 200
    assert client.get("/api/v1/career/profile", headers={"Authorization": f"Bearer {token}"}).json()["uploaded_cv"] is None

    reanalysis = client.post(f"/api/v1/cv/documents/{first_id}/analyze", headers={"Authorization": f"Bearer {token}"})
    assert reanalysis.status_code == 202
    assert reanalysis.json()["status"] == "queued"


def test_reset_all_keeps_uploaded_cv_in_history_without_current_badge(client, monkeypatch, tmp_path):
    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Reset Owner", "email": "reset@example.com", "password": "GucluParola123!"},
    )
    token = client.post(
        "/api/v1/auth/login",
        data={"username": "reset@example.com", "password": "GucluParola123!"},
    ).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr(
        "app.api.v1.cv.extract_text_from_pdf",
        lambda _data: "SQL Python Excel Pandas ile veri analizi deneyimi",
    )
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))

    queued = client.post(
        "/api/v1/cv/analyze",
        files={"file": ("history.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        headers=headers,
    )
    document = client.get("/api/v1/cv/documents", headers=headers).json()[0]
    stored_path = tmp_path / "1" / "cv" / f"{document['id']}.pdf"

    reset = client.post("/api/v1/career/reset", json={"scope": "all"}, headers=headers)

    assert reset.status_code == 200
    assert reset.json()["deleted"]["current_cvs"] == 1
    assert client.get(f"/api/v1/career/analysis/{queued.json()['analysis_id']}", headers=headers).status_code == 404
    documents = client.get("/api/v1/cv/documents", headers=headers).json()
    assert len(documents) == 1
    assert documents[0]["id"] == document["id"]
    assert documents[0]["is_current"] is False
    assert stored_path.exists()
    assert client.get("/api/v1/career/profile", headers=headers).json()["uploaded_cv"] is None


def test_deleting_last_cv_clears_career_flow(client, monkeypatch, tmp_path):
    client.post("/api/v1/auth/register", json={"full_name": "Delete Owner", "email": "delete@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "delete@example.com", "password": "GucluParola123!"}).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.extract_text_from_pdf", lambda _data: "SQL Python Excel Pandas ile veri analizi deneyimi")
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))
    queued = client.post("/api/v1/cv/analyze", files={"file": ("cv.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")}, headers=headers)
    document_id = client.get("/api/v1/cv/documents", headers=headers).json()[0]["id"]

    override = app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    evidence_path = tmp_path / "1" / "delete-evidence.pdf"
    evidence_path.parent.mkdir(parents=True, exist_ok=True)
    evidence_path.write_bytes(_MINIMAL_PDF)
    target = CareerTarget(id="delete-target", user_id=1, title="Data Analyst", source="ladder", status="active")
    task = CareerTask(id="delete-task", user_id=1, target_id=target.id, title="SQL", hint="", status="completed", evidence_types=["link"], skill_impacts=["SQL"])
    db.add_all([target, task, Evidence(id="delete-evidence", user_id=1, task_id=task.id, kind="file", file_path=str(evidence_path), status="accepted")])
    db.commit()
    db.close()

    assert client.patch(f"/api/v1/cv/documents/{document_id}/archive", headers=headers).status_code == 200
    assert client.delete(f"/api/v1/cv/documents/{document_id}", headers=headers).status_code == 204
    assert client.get("/api/v1/cv/documents", headers=headers).json() == []
    assert client.get(f"/api/v1/career/analysis/{queued.json()['analysis_id']}", headers=headers).status_code == 404
    assert client.get("/api/v1/career/targets", headers=headers).json() == []
    assert not evidence_path.exists()

    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    assert db.scalar(select(CareerTask).where(CareerTask.user_id == 1)) is None
    assert db.scalar(select(Evidence).where(Evidence.user_id == 1)) is None
    db.close()


def test_generated_cv_archive_can_start_owner_scoped_active_analysis(client, monkeypatch, tmp_path):
    client.post("/api/v1/auth/register", json={"full_name": "CV Owner", "email": "owner@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "owner@example.com", "password": "GucluParola123!"}).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)

    created = client.post("/api/v1/cv/documents/generated", headers=headers, files={"file": ("ignored.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")}, data={"display_name": "Trendyol Başvuru CV", "language": "tr", "builder_data": '{"tr":{"personal":{"full_name":"CV Owner","summary":"Veri analizi uzmanı"},"experience":[{"title":"Veri Analisti","details":"SQL Python Pandas ile raporlama"}],"skills":[{"category":"Teknik","items":"SQL Python Excel"}]}}'})
    assert created.status_code == 201
    document_id = created.json()["id"]
    assert created.json()["display_name"] == "Trendyol Başvuru CV.pdf"
    detail = client.get(f"/api/v1/cv/documents/{document_id}", headers=headers).json()
    assert detail["builder_data"]["tr"]["personal"]["full_name"] == "CV Owner"
    download = client.get(f"/api/v1/cv/documents/{document_id}/download", headers=headers)
    assert download.status_code == 200
    assert download.content.startswith(b"%PDF")

    queued = client.post(f"/api/v1/cv/documents/{document_id}/analyze", headers=headers)
    assert queued.status_code == 202
    assert queued.json()["status"] == "queued"
    current = client.get(f"/api/v1/career/analysis/{queued.json()['analysis_id']}", headers=headers).json()
    assert current["id"] == queued.json()["analysis_id"]
    assert current["cv_document_id"] == document_id
    assert current["file_name"] == "Trendyol Başvuru CV.pdf"
    assert current["source"] == "archive_generated"

    other = client.post("/api/v1/auth/register", json={"full_name": "Other", "email": "other-cv@example.com", "password": "GucluParola123!"})
    assert other.status_code == 201
    other_token = client.post("/api/v1/auth/login", data={"username": "other-cv@example.com", "password": "GucluParola123!"}).json()["access_token"]
    assert client.get(f"/api/v1/cv/documents/{document_id}", headers={"Authorization": f"Bearer {other_token}"}).status_code == 404
    assert client.post(f"/api/v1/cv/documents/{document_id}/analyze", headers={"Authorization": f"Bearer {other_token}"}).status_code == 404
    assert client.delete(f"/api/v1/cv/documents/{document_id}", headers=headers).status_code == 204
    assert client.get(f"/api/v1/cv/documents/{document_id}", headers=headers).status_code == 404


def test_archived_document_publish_failure_marks_analysis_failed(client, monkeypatch, tmp_path):
    client.post("/api/v1/auth/register", json={"full_name": "Archive Queue", "email": "archive-queue@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "archive-queue@example.com", "password": "GucluParola123!"}).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))
    created = client.post(
        "/api/v1/cv/documents/generated",
        headers=headers,
        files={"file": ("archive.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        data={
            "display_name": "Archive CV.pdf",
            "language": "tr",
            "builder_data": '{"tr":{"summary":"SQL Python Excel ile veri analizi ve raporlama deneyimi"}}',
        },
    )
    assert created.status_code == 201
    monkeypatch.setattr(
        "app.api.v1.cv.analyze_cv_task.delay",
        lambda _analysis_id: (_ for _ in ()).throw(RuntimeError("broker offline")),
    )

    response = client.post(f"/api/v1/cv/documents/{created.json()['id']}/analyze", headers=headers)

    assert response.status_code == 503
    db = next(app.dependency_overrides[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    row = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == 1))
    assert row.status == "failed"
    assert row.error_code == "queue_unavailable"
    db.close()


def test_generated_activation_publish_failure_marks_analysis_failed(client, monkeypatch, tmp_path):
    client.post("/api/v1/auth/register", json={"full_name": "Builder Queue", "email": "builder-queue@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "builder-queue@example.com", "password": "GucluParola123!"}).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))
    monkeypatch.setattr(
        "app.api.v1.cv.analyze_cv_task.delay",
        lambda _analysis_id: (_ for _ in ()).throw(RuntimeError("broker offline")),
    )

    response = client.post(
        "/api/v1/cv/documents/generated/activate",
        headers=headers,
        files={"file": ("builder.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        data={
            "display_name": "Builder Queue CV.pdf",
            "language": "tr",
            "builder_data": '{"tr":{"summary":"SQL Python Excel ile veri analizi deneyimi"}}',
            "cv_text": "Builder Queue SQL Python Excel ile veri analizi ve raporlama deneyimi",
        },
    )

    assert response.status_code == 503
    db = next(app.dependency_overrides[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    row = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == 1))
    assert row.status == "failed"
    assert row.error_code == "queue_unavailable"
    db.close()


def test_builder_save_atomically_replaces_active_cv_and_career_state(client, monkeypatch, tmp_path):
    client.post("/api/v1/auth/register", json={"full_name": "Builder Owner", "email": "builder@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "builder@example.com", "password": "GucluParola123!"}).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))
    monkeypatch.setattr("app.api.v1.cv.analyze_cv_task.delay", lambda _analysis_id: None)

    old_path = tmp_path / "1" / "cv" / "old-upload.pdf"
    old_path.parent.mkdir(parents=True)
    old_path.write_bytes(_MINIMAL_PDF)
    evidence_path = tmp_path / "1" / "old-evidence.pdf"
    evidence_path.write_bytes(_MINIMAL_PDF)

    override = app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    db.execute(text("PRAGMA foreign_keys=ON"))
    target = CareerTarget(id="builder-old-target", user_id=1, title="Eski Rota", source="ladder", status="active")
    task = CareerTask(id="builder-old-task", user_id=1, target_id=target.id, title="Eski Görev", hint="", status="pending", evidence_types=["file"], skill_impacts=["SQL"])
    db.add_all([
        CvDocument(id="old-upload", user_id=1, kind="uploaded", display_name="Eski CV.pdf", original_name="Eski CV.pdf", file_path=str(old_path), file_size=len(_MINIMAL_PDF), is_current=True),
        CareerAnalysis(id="builder-old-analysis", user_id=1, status="ready", source="upload", file_name="Eski CV.pdf", cv_text="SQL Python deneyimi", profile={}, skills=[], radar=[], career_ladder=[]),
        target,
        task,
        Evidence(id="builder-old-evidence", user_id=1, task_id=task.id, kind="file", file_path=str(evidence_path), status="accepted"),
    ])
    db.commit()
    db.close()

    builder_data = '{"tr":{"personal":{"full_name":"Builder Owner","summary":"Veri analizi ve raporlama uzmanı"},"experience":[{"title":"Veri Analisti","bullets":["SQL Python ile raporlama"]}],"education":[],"skills":[{"category":"Teknik","items":"SQL Python Excel"}],"projects":[],"certificates":[]},"en":{"personal":{"full_name":"Builder Owner"},"experience":[],"education":[],"skills":[],"projects":[],"certificates":[]}}'
    response = client.post(
        "/api/v1/cv/documents/generated/activate",
        headers=headers,
        files={"file": ("builder-owner-cv.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        data={
            "display_name": "Builder Owner CV.pdf",
            "language": "tr",
            "builder_data": builder_data,
            "cv_text": "Builder Owner\nÖzet: Veri analizi ve raporlama uzmanı\nVeri Analisti\nSQL Python Excel ile raporlama deneyimi",
        },
    )

    assert response.status_code == 202
    payload = response.json()
    assert payload["status"] == "queued"
    assert payload["file_name"] == "Builder Owner CV.pdf"
    documents = client.get("/api/v1/cv/documents", headers=headers).json()
    current = [item for item in documents if item["is_current"]]
    assert len(current) == 1
    assert current[0]["kind"] == "generated"
    assert current[0]["display_name"] == "Builder Owner CV.pdf"
    assert next(item for item in documents if item["id"] == "old-upload")["is_current"] is False

    analysis = client.get(f"/api/v1/career/analysis/{payload['analysis_id']}", headers=headers).json()
    assert analysis["cv_document_id"] == current[0]["id"]
    assert analysis["source"] == "builder"
    assert client.get("/api/v1/career/targets", headers=headers).json() == []
    assert evidence_path.exists() is False


def test_invalid_builder_save_keeps_existing_active_state(client, monkeypatch, tmp_path):
    client.post("/api/v1/auth/register", json={"full_name": "Safe Owner", "email": "safe-builder@example.com", "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": "safe-builder@example.com", "password": "GucluParola123!"}).json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}
    monkeypatch.setattr("app.api.v1.cv.settings.UPLOAD_DIR", str(tmp_path))
    old_path = tmp_path / "1" / "cv" / "safe-old.pdf"
    old_path.parent.mkdir(parents=True)
    old_path.write_bytes(_MINIMAL_PDF)

    override = app.dependency_overrides
    db = next(override[__import__("app.core.database", fromlist=["get_db"]).get_db]())
    db.add(CvDocument(id="safe-old", user_id=1, kind="uploaded", display_name="Safe Old.pdf", original_name="Safe Old.pdf", file_path=str(old_path), file_size=len(_MINIMAL_PDF), is_current=True))
    db.commit()
    db.close()

    response = client.post(
        "/api/v1/cv/documents/generated/activate",
        headers=headers,
        files={"file": ("bad.pdf", BytesIO(_MINIMAL_PDF), "application/pdf")},
        data={"display_name": "Bad.pdf", "language": "tr", "builder_data": "not-json", "cv_text": "SQL Python Excel ile veri analizi deneyimi ve raporlama"},
    )

    assert response.status_code == 422
    documents = client.get("/api/v1/cv/documents", headers=headers).json()
    assert [item["id"] for item in documents if item["is_current"]] == ["safe-old"]
