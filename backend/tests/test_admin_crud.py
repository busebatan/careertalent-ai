from uuid import uuid4

from sqlalchemy import select

from app.core.database import get_db
from app.core.security import hash_password
from app.main import app
from app.models.engagement import CareerInterview, CareerInterviewAnswer, JobApplication
from app.models.user import User


PASSWORD = "GucluParola123!"


def _user(email: str, *, role: str = "student", permissions: list[str] | None = None) -> int:
    with next(app.dependency_overrides[get_db]()) as db:
        user = User(
            full_name=email.split("@", 1)[0].replace(".", " ").title(),
            email=email,
            hashed_password=hash_password(PASSWORD),
            is_active=True,
            is_admin=role in {"admin", "super_admin"},
            role=role,
            admin_permissions=permissions or [],
            must_change_password=False,
        )
        db.add(user)
        db.commit()
        db.refresh(user)
        return user.id


def _headers(client, email: str) -> dict[str, str]:
    response = client.post(
        "/api/v1/auth/login",
        data={"username": email, "password": PASSWORD},
    )
    assert response.status_code == 200
    return {"Authorization": f"Bearer {response.json()['access_token']}"}


def _student_payload(email: str = "candidate@example.com") -> dict:
    return {
        "full_name": "Aday Kullanıcı",
        "email": email,
        "temporary_password": "GeciciParola123!",
        "preferred_locale": "tr",
    }


def test_student_view_write_and_delete_permissions_are_independent(client):
    _user("viewer@example.com", role="admin", permissions=["students.view"])
    _user("writer@example.com", role="admin", permissions=["students.write"])
    _user("deleter@example.com", role="admin", permissions=["students.delete"])
    viewer = _headers(client, "viewer@example.com")
    writer = _headers(client, "writer@example.com")
    deleter = _headers(client, "deleter@example.com")

    created = client.post(
        "/api/v1/admin/students",
        headers=writer,
        json=_student_payload(),
    )
    assert created.status_code == 201
    student_id = created.json()["id"]
    with next(app.dependency_overrides[get_db]()) as db:
        db.add(JobApplication(
            id="preserved-application",
            user_id=student_id,
            company="Acme",
            role="Data Analyst",
            stage="applied",
        ))
        db.commit()

    assert client.get("/api/v1/admin/students", headers=writer).status_code == 403
    assert client.get("/api/v1/admin/students", headers=viewer).status_code == 200
    assert client.patch(
        f"/api/v1/admin/students/{student_id}",
        headers=viewer,
        json={"full_name": "Değişemez"},
    ).status_code == 403
    assert client.delete(f"/api/v1/admin/students/{student_id}", headers=viewer).status_code == 403

    updated = client.patch(
        f"/api/v1/admin/students/{student_id}",
        headers=writer,
        json={"full_name": "Güncel Aday"},
    )
    assert updated.status_code == 200
    assert updated.json()["full_name"] == "Güncel Aday"
    assert client.delete(f"/api/v1/admin/students/{student_id}", headers=writer).status_code == 403

    assert client.get("/api/v1/admin/students", headers=deleter).status_code == 403
    assert client.delete(f"/api/v1/admin/students/{student_id}", headers=deleter).status_code == 204
    with next(app.dependency_overrides[get_db]()) as db:
        archived = db.get(User, student_id)
        assert archived is not None
        assert archived.is_active is False
        assert db.get(JobApplication, "preserved-application") is not None


def test_super_admin_manages_application_lifecycle(client):
    _user("root@example.com", role="super_admin")
    student_id = _user("student@example.com")
    root = _headers(client, "root@example.com")

    created = client.post(
        "/api/v1/admin/applications",
        headers=root,
        json={
            "user_id": student_id,
            "company": "Acme",
            "role": "Data Analyst",
            "stage": "applied",
            "next_action": "Portfolyo gönder",
        },
    )
    assert created.status_code == 201
    application_id = created.json()["id"]
    assert created.json()["student_email"] == "student@example.com"

    listed = client.get("/api/v1/admin/applications", headers=root)
    assert listed.status_code == 200
    assert listed.json()["applications"][0]["id"] == application_id

    updated = client.patch(
        f"/api/v1/admin/applications/{application_id}",
        headers=root,
        json={"stage": "interview", "note": "Teknik görüşme planlandı"},
    )
    assert updated.status_code == 200
    assert updated.json()["stage"] == "interview"
    assert updated.json()["note"] == "Teknik görüşme planlandı"

    assert client.delete(f"/api/v1/admin/applications/{application_id}", headers=root).status_code == 204
    with next(app.dependency_overrides[get_db]()) as db:
        assert db.get(JobApplication, application_id) is None


def test_super_admin_starts_updates_and_deletes_ai_interview_with_answers(client, monkeypatch):
    _user("root@example.com", role="super_admin")
    student_id = _user("student@example.com")
    root = _headers(client, "root@example.com")

    def fake_start_interview(db, user_id: int, language: str):
        row = CareerInterview(
            id=str(uuid4()),
            user_id=user_id,
            target_role="Data Analyst",
            status="active",
            language=language,
            questions=[{"id": "q1", "question": "SQL nedir?", "guidance": "Kısa anlat."}],
        )
        db.add(row)
        db.commit()
        db.refresh(row)
        return row

    monkeypatch.setattr("app.api.v1.admin.start_interview", fake_start_interview)
    created = client.post(
        "/api/v1/admin/interviews",
        headers=root,
        json={"user_id": student_id, "language": "tr"},
    )
    assert created.status_code == 201
    interview_id = created.json()["id"]
    assert created.json()["question_count"] == 1

    updated = client.patch(
        f"/api/v1/admin/interviews/{interview_id}",
        headers=root,
        json={"status": "completed"},
    )
    assert updated.status_code == 200
    assert updated.json()["target_role"] == "Data Analyst"
    assert updated.json()["status"] == "completed"

    with next(app.dependency_overrides[get_db]()) as db:
        db.add(CareerInterviewAnswer(
            id=str(uuid4()),
            interview_id=interview_id,
            user_id=student_id,
            question_id="q1",
            answer="Sorgu dili",
            score=80,
            feedback="İyi",
            strengths=[],
            improvements=[],
        ))
        db.commit()

    listed = client.get("/api/v1/admin/interviews", headers=root)
    assert listed.status_code == 200
    assert listed.json()["interviews"][0]["answer_count"] == 1

    assert client.delete(f"/api/v1/admin/interviews/{interview_id}", headers=root).status_code == 204
    with next(app.dependency_overrides[get_db]()) as db:
        assert db.get(CareerInterview, interview_id) is None
        assert db.scalar(select(CareerInterviewAnswer).where(CareerInterviewAnswer.interview_id == interview_id)) is None


def test_admin_account_delete_soft_deactivates_and_invalidates_session(client):
    _user("root@example.com", role="super_admin")
    admin_id = _user("ops@example.com", role="admin", permissions=["students.view"])
    root = _headers(client, "root@example.com")
    ops = _headers(client, "ops@example.com")

    response = client.delete(f"/api/v1/admin/accounts/{admin_id}", headers=root)

    assert response.status_code == 204
    assert client.get("/api/v1/admin/modules/students", headers=ops).status_code == 403
    with next(app.dependency_overrides[get_db]()) as db:
        admin = db.get(User, admin_id)
        assert admin is not None
        assert admin.is_active is False


def test_legacy_manage_permissions_expand_to_canonical_view_write_delete(client):
    _user(
        "legacy@example.com",
        role="admin",
        permissions=["organizations.manage", "career_data.manage"],
    )
    headers = _headers(client, "legacy@example.com")

    response = client.get("/api/v1/auth/me", headers=headers)

    assert response.status_code == 200
    permissions = response.json()["admin_permissions"]
    assert "organizations.manage" not in permissions
    assert "career_data.manage" not in permissions
    assert {"organizations.view", "organizations.write", "organizations.delete"} <= set(permissions)
    assert {"career_data.view", "career_data.write", "career_data.delete"} <= set(permissions)


def test_career_data_view_write_and_delete_permissions_are_independent(client):
    _user("catalog-viewer@example.com", role="admin", permissions=["career_data.view"])
    _user("catalog-writer@example.com", role="admin", permissions=["career_data.write"])
    _user("catalog-deleter@example.com", role="admin", permissions=["career_data.delete"])
    viewer = _headers(client, "catalog-viewer@example.com")
    writer = _headers(client, "catalog-writer@example.com")
    deleter = _headers(client, "catalog-deleter@example.com")
    payload = {
        "slug": "data-analyst",
        "title": "Data Analyst",
        "description": "Analiz rolü",
        "weeks_template": 12,
    }

    created = client.post("/api/v1/admin/career-data/roles", headers=writer, json=payload)
    assert created.status_code == 201
    role_id = created.json()["id"]
    assert client.get("/api/v1/admin/career-data/roles", headers=writer).status_code == 403
    assert client.delete(f"/api/v1/admin/career-data/roles/{role_id}", headers=writer).status_code == 403

    assert client.get("/api/v1/admin/career-data/roles", headers=viewer).status_code == 200
    assert client.post("/api/v1/admin/career-data/roles", headers=viewer, json=payload).status_code == 403
    assert client.delete(f"/api/v1/admin/career-data/roles/{role_id}", headers=viewer).status_code == 403

    assert client.get("/api/v1/admin/career-data/roles", headers=deleter).status_code == 403
    assert client.delete(f"/api/v1/admin/career-data/roles/{role_id}", headers=deleter).status_code == 204


def test_company_accounts_never_appear_as_students_or_in_student_metrics(client):
    _user("root@example.com", role="super_admin")
    _user("student@example.com")
    _user("company@example.com", role="company")
    root = _headers(client, "root@example.com")

    students = client.get("/api/v1/admin/students", headers=root)
    dashboard = client.get("/api/v1/admin/dashboard", headers=root)

    assert students.status_code == 200
    assert students.json()["total"] == 1
    assert [row["email"] for row in students.json()["students"]] == ["student@example.com"]
    assert dashboard.status_code == 200
    assert dashboard.json()["module_counts"]["students"] == 1


def test_admin_student_detail_returns_profile_and_related_records(client):
    _user("root@example.com", role="super_admin")
    student_id = _user("detail@example.com")
    root = _headers(client, "root@example.com")

    response = client.get(f"/api/v1/admin/students/{student_id}", headers=root)

    assert response.status_code == 200
    body = response.json()
    assert body["id"] == student_id
    assert body["email"] == "detail@example.com"
    assert body["cv_documents"] == []
    assert body["analyses"] == []
    assert body["interviews"] == []
    assert body["applications"] == []
    assert body["targets"] == []

    _user("viewer@example.com", role="admin", permissions=["students.view"])
    viewer = _headers(client, "viewer@example.com")
    assert client.get(f"/api/v1/admin/students/{student_id}", headers=viewer).status_code == 200
    assert client.get("/api/v1/admin/students/999999", headers=root).status_code == 404
