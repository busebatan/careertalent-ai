from sqlalchemy import select

from app.core.database import get_db
from app.main import app
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence, JobOpportunity
from app.models.engagement import CareerInterview, CvDocument, JobApplication
from app.models.user import User


def _register(client, email: str, name: str) -> None:
    response = client.post(
        "/api/v1/auth/register",
        json={
            "full_name": name,
            "email": email,
            "password": "GucluParola123!",
        },
    )
    assert response.status_code == 201


def _promote_to_admin(email: str) -> None:
    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == email))
        assert user is not None
        user.is_admin = True
        db.commit()


def _headers(client, email: str) -> dict[str, str]:
    response = client.post(
        "/api/v1/auth/login",
        data={"username": email, "password": "GucluParola123!"},
    )
    assert response.status_code == 200
    return {"Authorization": f"Bearer {response.json()['access_token']}"}


def _seed_student_records() -> None:
    with next(app.dependency_overrides[get_db]()) as db:
        student = db.scalar(select(User).where(User.email == "ogrenci@example.com"))
        assert student is not None
        target = CareerTarget(
            id="target-1",
            user_id=student.id,
            title="Veri Analisti",
            source="custom",
            status="ready",
            plan={},
        )
        task = CareerTask(
            id="task-1",
            user_id=student.id,
            target_id=target.id,
            title="SQL portföy projesi",
            hint="Gerçek veri seti kullan",
            status="pending",
            evidence_required=True,
            evidence_types=["link"],
            skill_impacts=[],
            training_suggestions=[],
        )
        db.add_all(
            [
                CvDocument(
                    id="cv-1",
                    user_id=student.id,
                    kind="uploaded",
                    display_name="Ayse-CV.pdf",
                    original_name="Ayse-CV.pdf",
                    file_path="/isolated/Ayse-CV.pdf",
                    file_size=1024,
                    is_current=True,
                ),
                CareerAnalysis(
                    id="analysis-1",
                    user_id=student.id,
                    status="ready",
                    source="pdf",
                    file_name="Ayse-CV.pdf",
                    current_role="Veri Analisti",
                    profile={},
                    skills=["SQL", "Python"],
                    radar=[],
                    career_ladder=[],
                ),
                target,
                task,
                Evidence(
                    id="evidence-1",
                    user_id=student.id,
                    task_id=task.id,
                    kind="link",
                    url="https://example.test/portfolio",
                    status="accepted",
                    confidence=0.92,
                ),
                JobOpportunity(
                    id="job-1",
                    user_id=student.id,
                    status="ready",
                    title="Junior Data Analyst",
                    company="Örnek Şirket",
                    source="Kariyer sitesi",
                    required_skills=["SQL"],
                    matched_skills=["SQL"],
                    missing_skills=[],
                    match_score=86,
                    cv_suggestions=[],
                    saved=True,
                    applied_suggestion_ids=[],
                ),
                JobApplication(
                    id="application-1",
                    user_id=student.id,
                    company="Örnek Şirket",
                    role="Junior Data Analyst",
                    stage="interview",
                    next_action="Teknik görüşmeye hazırlan",
                ),
                CareerInterview(
                    id="interview-1",
                    user_id=student.id,
                    target_role="Junior Data Analyst",
                    status="active",
                    questions=[{"id": "q-1"}, {"id": "q-2"}],
                ),
            ]
        )
        db.commit()


def test_admin_endpoints_require_admin_authentication(client):
    assert client.get("/api/v1/admin/dashboard").status_code == 401

    _register(client, "ogrenci@example.com", "Ayşe Yılmaz")
    response = client.get(
        "/api/v1/admin/dashboard",
        headers=_headers(client, "ogrenci@example.com"),
    )

    assert response.status_code == 403
    assert response.json()["detail"] == "Admin privileges required"


def test_admin_dashboard_and_modules_expose_only_real_student_records(client):
    _register(client, "admin@example.com", "Yönetici Hesabı")
    _register(client, "ogrenci@example.com", "Ayşe Yılmaz")
    _promote_to_admin("admin@example.com")
    _seed_student_records()
    headers = _headers(client, "admin@example.com")

    dashboard = client.get("/api/v1/admin/dashboard", headers=headers)

    assert dashboard.status_code == 200
    assert dashboard.json()["stats"] == [
        {"label": "Aktif öğrenci", "value": 1, "detail": "Admin hesapları hariç"},
        {"label": "Mevcut CV", "value": 1, "detail": "Aktif CV kaydı"},
        {"label": "Hazır analiz", "value": 1, "detail": "Analizi tamamlanan CV"},
        {"label": "Aktif başvuru", "value": 1, "detail": "Reddedilenler hariç"},
    ]
    assert dashboard.json()["module_counts"] == {
        "students": 1,
        "readiness": 1,
        "skill-passport": 1,
        "job-radar": 1,
        "applications": 1,
        "interviews": 1,
    }
    assert dashboard.json()["recent_students"][0]["name"] == "Ayşe Yılmaz"
    assert dashboard.json()["recent_students"][0]["email"] == "ogrenci@example.com"
    assert all(item["email"] != "admin@example.com" for item in dashboard.json()["recent_students"])

    expected_rows = {
        "students": ("Ayşe Yılmaz", "ogrenci@example.com", "CV yüklendi", "ready"),
        "readiness": ("Veri Analisti", "Ayşe Yılmaz", "2 yetenek", "ready"),
        "skill-passport": ("SQL portföy projesi", "Ayşe Yılmaz", "link", "accepted"),
        "job-radar": ("Junior Data Analyst", "Örnek Şirket", "%86", "ready"),
        "applications": ("Örnek Şirket · Junior Data Analyst", "Öğrenci: Ayşe Yılmaz", None, "interview"),
        "interviews": ("Junior Data Analyst", "Öğrenci: Ayşe Yılmaz", "2 soru", "active"),
    }
    for module, (name, meta, score, status) in expected_rows.items():
        response = client.get(f"/api/v1/admin/modules/{module}", headers=headers)

        assert response.status_code == 200
        assert response.json()["total"] == 1
        assert response.json()["rows"][0]["name"] == name
        assert response.json()["rows"][0]["meta"] == meta
        if score is None:
            assert response.json()["rows"][0]["score"]
        else:
            assert response.json()["rows"][0]["score"] == score
        assert response.json()["rows"][0]["status"] == status


def test_admin_empty_modules_return_zero_without_demo_rows(client):
    _register(client, "admin@example.com", "Yönetici Hesabı")
    _promote_to_admin("admin@example.com")
    headers = _headers(client, "admin@example.com")

    dashboard = client.get("/api/v1/admin/dashboard", headers=headers)
    module = client.get("/api/v1/admin/modules/interviews", headers=headers)

    assert dashboard.status_code == 200
    assert [item["value"] for item in dashboard.json()["stats"]] == [0, 0, 0, 0]
    assert dashboard.json()["module_counts"] == {
        "students": 0,
        "readiness": 0,
        "skill-passport": 0,
        "job-radar": 0,
        "applications": 0,
        "interviews": 0,
    }
    assert dashboard.json()["recent_students"] == []
    assert module.status_code == 200
    assert module.json()["total"] == 0
    assert module.json()["rows"] == []


def test_admin_module_reports_true_total_while_limiting_visible_rows(client):
    _register(client, "admin@example.com", "Yönetici Hesabı")
    _promote_to_admin("admin@example.com")
    with next(app.dependency_overrides[get_db]()) as db:
        db.add_all(
            [
                User(
                    full_name=f"Öğrenci {index}",
                    email=f"ogrenci-{index}@example.com",
                    hashed_password="not-used-by-this-read-test",
                    is_active=True,
                    is_admin=False,
                )
                for index in range(51)
            ]
        )
        db.commit()

    response = client.get(
        "/api/v1/admin/modules/students",
        headers=_headers(client, "admin@example.com"),
    )

    assert response.status_code == 200
    assert response.json()["total"] == 51
    assert len(response.json()["rows"]) == 50
    assert all(row["name"].startswith("Öğrenci") for row in response.json()["rows"])


def test_admin_module_name_is_constrained_by_api_contract(client):
    _register(client, "admin@example.com", "Yönetici Hesabı")
    _promote_to_admin("admin@example.com")

    response = client.get(
        "/api/v1/admin/modules/demo-module",
        headers=_headers(client, "admin@example.com"),
    )

    assert response.status_code == 422
