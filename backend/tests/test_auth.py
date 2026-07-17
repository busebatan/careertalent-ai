from collections.abc import Generator

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker
from sqlalchemy.pool import StaticPool

from app.core.database import Base, get_db
from app.main import app
from app.models.user import User


@pytest.fixture()
def client() -> Generator[TestClient, None, None]:
    engine = create_engine(
        "sqlite://",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    testing_session = sessionmaker(bind=engine)
    Base.metadata.create_all(engine)

    def override_db() -> Generator[Session, None, None]:
        db = testing_session()
        try:
            yield db
        finally:
            db.close()

    app.dependency_overrides[get_db] = override_db
    with TestClient(app) as test_client:
        yield test_client
    app.dependency_overrides.clear()
    Base.metadata.drop_all(engine)


def register(client: TestClient, email: str = "ogrenci@example.com"):
    return client.post(
        "/api/v1/auth/register",
        json={
            "full_name": "  Ayşe Yılmaz  ",
            "email": email,
            "password": "GucluParola123!",
        },
    )


def login(client: TestClient, email: str = "ogrenci@example.com", password: str = "GucluParola123!"):
    return client.post(
        "/api/v1/auth/login",
        data={"username": email, "password": password},
    )


def test_register_login_and_me_contract(client: TestClient):
    response = register(client, "OGRENCI@Example.COM")

    assert response.status_code == 201
    assert response.json() == {
        "id": 1,
        "full_name": "Ayşe Yılmaz",
        "email": "ogrenci@example.com",
        "is_active": True,
        "is_admin": False,
        "role": "student",
        "admin_permissions": [],
        "must_change_password": False,
        "preferred_locale": "tr",
    }
    assert "password" not in response.json()

    token_response = login(client)
    assert token_response.status_code == 200
    assert token_response.json()["token_type"] == "bearer"
    assert token_response.json()["access_token"]

    me = client.get(
        "/api/v1/auth/me",
        headers={"Authorization": f"Bearer {token_response.json()['access_token']}"},
    )
    assert me.status_code == 200
    assert me.json()["email"] == "ogrenci@example.com"
    assert me.json()["is_admin"] is False
    assert me.json()["preferred_locale"] == "tr"


@pytest.mark.parametrize(
    ("payload", "field"),
    [
        ({"full_name": "A", "email": "user@example.com", "password": "GucluParola123!"}, "full_name"),
        ({"full_name": "Ayşe Yılmaz", "email": "bozuk", "password": "GucluParola123!"}, "email"),
        ({"full_name": "Ayşe Yılmaz", "email": "user@example.com", "password": "kisa"}, "password"),
    ],
)
def test_register_rejects_invalid_input(client: TestClient, payload: dict, field: str):
    response = client.post("/api/v1/auth/register", json=payload)

    assert response.status_code == 422
    assert any(error["loc"][-1] == field for error in response.json()["detail"])


def test_register_rejects_duplicate_email_case_insensitively(client: TestClient):
    assert register(client, "ogrenci@example.com").status_code == 201

    duplicate = register(client, "OGRENCI@example.com")

    assert duplicate.status_code == 409
    assert duplicate.json()["detail"] == "Email already registered"


@pytest.mark.parametrize(
    ("email", "password"),
    [
        ("missing@example.com", "GucluParola123!"),
        ("ogrenci@example.com", "YanlisParola123!"),
    ],
)
def test_login_rejects_invalid_credentials_without_account_disclosure(client: TestClient, email: str, password: str):
    register(client)

    response = login(client, email, password)

    assert response.status_code == 401
    assert response.json()["detail"] == "Invalid email or password"


def test_login_rejects_inactive_user(client: TestClient):
    register(client)
    with next(app.dependency_overrides[get_db]()) as db:
        user = db.query(User).filter(User.email == "ogrenci@example.com").one()
        user.is_active = False
        db.commit()

    response = login(client)

    assert response.status_code == 403
    assert response.json()["detail"] == "Account is inactive"


def test_me_rejects_missing_and_invalid_tokens(client: TestClient):
    assert client.get("/api/v1/auth/me").status_code == 401
    invalid = client.get("/api/v1/auth/me", headers={"Authorization": "Bearer invalid"})
    assert invalid.status_code == 401
    assert invalid.json()["detail"] == "Could not validate credentials"


def test_career_role_mutations_require_admin(client: TestClient):
    register(client)
    payload = {
        "slug": "veri-analisti",
        "title": "Veri Analisti",
        "description": "Veriyi karara dönüştürür.",
        "required_skills": ["SQL", "Python"],
        "weeks_template": 12,
    }

    assert client.post("/api/v1/career-roles/", json=payload).status_code == 401

    user_token = login(client).json()["access_token"]
    forbidden = client.post(
        "/api/v1/career-roles/",
        json=payload,
        headers={"Authorization": f"Bearer {user_token}"},
    )
    assert forbidden.status_code == 403
    assert forbidden.json()["detail"] == "Admin privileges required"

    with next(app.dependency_overrides[get_db]()) as db:
        user = db.query(User).filter(User.email == "ogrenci@example.com").one()
        user.is_admin = True
        db.commit()

    admin_token = login(client).json()["access_token"]
    created = client.post(
        "/api/v1/career-roles/",
        json=payload,
        headers={"Authorization": f"Bearer {admin_token}"},
    )
    assert created.status_code == 200
    assert created.json()["slug"] == "veri-analisti"
