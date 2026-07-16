from sqlalchemy import select

from app.core.database import get_db
from app.main import app
from app.models.user import User


def _register(client, email: str, password: str = "GucluParola123!") -> None:
    response = client.post("/api/v1/auth/register", json={"full_name": "Süper Yönetici", "email": email, "password": password})
    assert response.status_code == 201


def _promote_super(email: str) -> None:
    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == email))
        user.is_admin = True
        user.role = "super_admin"
        db.commit()


def _headers(client, email: str, password: str) -> dict[str, str]:
    response = client.post("/api/v1/auth/login", data={"username": email, "password": password})
    assert response.status_code == 200
    return {"Authorization": f"Bearer {response.json()['access_token']}"}


def test_super_admin_creates_scoped_admin_and_first_login_requires_password_change(client):
    _register(client, "root@example.com")
    _promote_super("root@example.com")
    root = _headers(client, "root@example.com", "GucluParola123!")

    created = client.post(
        "/api/v1/admin/accounts",
        headers=root,
        json={
            "full_name": "Operasyon Admini",
            "email": "ops@example.com",
            "temporary_password": "GeciciParola123!",
            "permissions": ["students.view"],
        },
    )

    assert created.status_code == 201
    account = created.json()
    assert account["role"] == "admin"
    assert account["must_change_password"] is True
    assert account["admin_permissions"] == ["dashboard.view", "students.view"]

    ops = _headers(client, "ops@example.com", "GeciciParola123!")
    blocked = client.get("/api/v1/admin/modules/students", headers=ops)
    assert blocked.status_code == 403
    assert blocked.json()["detail"] == "Password change required"

    changed = client.patch(
        "/api/v1/admin/profile",
        headers=ops,
        json={
            "full_name": "Operasyon Admini",
            "email": "ops@example.com",
            "current_password": "GeciciParola123!",
            "new_password": "KaliciParola123!",
        },
    )
    assert changed.status_code == 200
    assert changed.json()["must_change_password"] is False
    assert client.get("/api/v1/admin/profile", headers=ops).status_code == 401
    ops = _headers(client, "ops@example.com", "KaliciParola123!")
    assert client.get("/api/v1/admin/modules/students", headers=ops).status_code == 200
    assert client.get("/api/v1/admin/modules/interviews", headers=ops).status_code == 403
    assert client.get("/api/v1/admin/career-data/roles", headers=ops).status_code == 403
    assert client.get("/api/v1/admin/accounts", headers=ops).status_code == 403
    assert client.post("/api/v1/auth/login", data={"username": "ops@example.com", "password": "GeciciParola123!"}).status_code == 401
    assert client.post("/api/v1/auth/login", data={"username": "ops@example.com", "password": "KaliciParola123!"}).status_code == 200


def test_super_admin_updates_permissions_and_deactivates_admin_without_deleting_it(client):
    _register(client, "root@example.com")
    _promote_super("root@example.com")
    root = _headers(client, "root@example.com", "GucluParola123!")
    created = client.post(
        "/api/v1/admin/accounts",
        headers=root,
        json={"full_name": "Destek Admini", "email": "support@example.com", "temporary_password": "GeciciParola123!", "permissions": ["interviews.view"]},
    ).json()

    updated = client.patch(
        f"/api/v1/admin/accounts/{created['id']}",
        headers=root,
        json={
            "full_name": "Destek Admini",
            "email": "support@example.com",
            "is_active": False,
            "permissions": ["applications.view", "interviews.view"],
        },
    )

    assert updated.status_code == 200
    assert updated.json()["is_active"] is False
    assert updated.json()["admin_permissions"] == ["dashboard.view", "applications.view", "interviews.view"]
    assert client.post("/api/v1/auth/login", data={"username": "support@example.com", "password": "GeciciParola123!"}).status_code == 403
    accounts = client.get("/api/v1/admin/accounts", headers=root).json()["accounts"]
    assert any(item["email"] == "support@example.com" and item["is_active"] is False for item in accounts)


def test_admin_profile_requires_current_password_and_unique_email(client):
    _register(client, "root@example.com")
    _register(client, "other@example.com")
    _promote_super("root@example.com")
    root = _headers(client, "root@example.com", "GucluParola123!")

    wrong = client.patch(
        "/api/v1/admin/profile",
        headers=root,
        json={"full_name": "Yeni İsim", "email": "root@example.com", "current_password": "YanlisParola123!"},
    )
    assert wrong.status_code == 422

    duplicate = client.patch(
        "/api/v1/admin/profile",
        headers=root,
        json={"full_name": "Yeni İsim", "email": "other@example.com", "current_password": "GucluParola123!"},
    )
    assert duplicate.status_code == 409
