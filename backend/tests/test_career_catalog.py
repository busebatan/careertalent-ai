from fastapi.testclient import TestClient

from app.core.database import get_db
from app.main import app
from app.models.user import User


def admin_headers(client: TestClient) -> dict[str, str]:
    email = "catalog-admin@example.com"
    password = "CatalogTestPassword123!"
    registered = client.post(
        "/api/v1/auth/register",
        json={"full_name": "Catalog Admin", "email": email, "password": password},
    )
    assert registered.status_code == 201
    with next(app.dependency_overrides[get_db]()) as db:
        user = db.query(User).filter(User.email == email).one()
        user.is_admin = True
        db.commit()
    login = client.post("/api/v1/auth/login", data={"username": email, "password": password})
    assert login.status_code == 200
    return {"Authorization": f"Bearer {login.json()['access_token']}"}


def create_role(client: TestClient, headers: dict[str, str], slug: str = "veri-analisti") -> dict:
    response = client.post(
        "/api/v1/admin/career-data/roles",
        headers=headers,
        json={"slug": slug, "title": "Veri Analisti", "description": "Karar için veri kullanır.", "weeks_template": 12},
    )
    assert response.status_code == 201
    return response.json()


def create_skill(client: TestClient, headers: dict[str, str], slug: str = "sql", name: str = "SQL") -> dict:
    response = client.post(
        "/api/v1/admin/career-data/skills",
        headers=headers,
        json={"slug": slug, "name": name, "skill_type": "technical", "description": "Sorgulama dili", "is_active": True},
    )
    assert response.status_code == 201
    return response.json()


def test_catalog_endpoints_require_admin_and_do_not_leak_records_to_students(client: TestClient):
    assert client.get("/api/v1/admin/career-data/roles").status_code == 401

    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Student", "email": "student@example.com", "password": "StudentTestPassword123!"},
    )
    student = client.post("/api/v1/auth/login", data={"username": "student@example.com", "password": "StudentTestPassword123!"})

    response = client.get(
        "/api/v1/admin/career-data/roles",
        headers={"Authorization": f"Bearer {student.json()['access_token']}"},
    )

    assert response.status_code == 403
    assert response.json()["detail"] == "Admin privileges required"


def test_catalog_requirement_is_the_canonical_source_for_legacy_role_reads(client: TestClient):
    headers = admin_headers(client)
    role = create_role(client, headers)
    skill = create_skill(client, headers)
    source = client.post(
        "/api/v1/admin/career-data/sources",
        headers=headers,
        json={"slug": "onet", "name": "O*NET", "source_type": "official", "url": "https://example.test/onet", "status": "active"},
    )
    assert source.status_code == 201

    requirement = client.post(
        "/api/v1/admin/career-data/requirements",
        headers=headers,
        json={
            "career_role_id": role["id"],
            "career_skill_id": skill["id"],
            "data_source_id": source.json()["id"],
            "requirement_type": "required",
            "expected_level": "advanced",
            "weight": 85,
            "notes": "İş ilanı ve kaynak doğrulandı.",
        },
    )
    assert requirement.status_code == 201
    assert requirement.json()["career_skill_name"] == "SQL"
    assert requirement.json()["data_source_name"] == "O*NET"

    legacy_read = client.get(f"/api/v1/career-roles/{role['id']}")
    assert legacy_read.status_code == 200
    assert legacy_read.json()["required_skills"] == ["SQL"]

    updated_skill = client.put(
        f"/api/v1/admin/career-data/skills/{skill['id']}",
        headers=headers,
        json={"slug": "sql", "name": "İleri SQL", "skill_type": "technical", "description": "Güncel ad", "is_active": True},
    )
    assert updated_skill.status_code == 200
    assert client.get(f"/api/v1/career-roles/{role['id']}").json()["required_skills"] == ["İleri SQL"]

    cleared_source = client.delete(f"/api/v1/admin/career-data/sources/{source.json()['id']}", headers=headers)
    assert cleared_source.status_code == 204
    listed_requirement = client.get("/api/v1/admin/career-data/requirements", headers=headers).json()
    assert listed_requirement == [{**requirement.json(), "career_skill_name": "İleri SQL", "data_source_id": None, "data_source_name": None}]


def test_legacy_role_writes_create_and_preserve_catalog_requirements(client: TestClient):
    headers = admin_headers(client)
    legacy_role = client.post(
        "/api/v1/career-roles/",
        headers=headers,
        json={
            "slug": "finansal-analist",
            "title": "Financial Analyst",
            "description": "Finansal modelleme yapar.",
            "required_skills": ["Excel", "SQL", "Excel"],
            "weeks_template": 10,
        },
    )
    assert legacy_role.status_code == 200

    skills = client.get("/api/v1/admin/career-data/skills", headers=headers)
    assert [(row["name"], row["requirement_count"]) for row in skills.json()] == [("Excel", 1), ("SQL", 1)]
    requirements = client.get("/api/v1/admin/career-data/requirements", headers=headers).json()
    sql_requirement = next(row for row in requirements if row["career_skill_name"] == "SQL")

    changed_requirement = client.put(
        f"/api/v1/admin/career-data/requirements/{sql_requirement['id']}",
        headers=headers,
        json={
            "career_role_id": legacy_role.json()["id"],
            "career_skill_id": sql_requirement["career_skill_id"],
            "requirement_type": "preferred",
            "expected_level": "expert",
            "weight": 40,
            "notes": "Tercih edilir.",
        },
    )
    assert changed_requirement.status_code == 200

    legacy_update = client.put(
        f"/api/v1/career-roles/{legacy_role.json()['id']}",
        headers=headers,
        json={
            "slug": "finansal-analist",
            "title": "Financial Analyst",
            "description": "Finansal modelleme yapar.",
            "required_skills": ["SQL"],
            "weeks_template": 10,
        },
    )
    assert legacy_update.status_code == 200
    assert legacy_update.json()["required_skills"] == ["SQL"]
    retained = client.get("/api/v1/admin/career-data/requirements", headers=headers).json()
    assert retained == [{**changed_requirement.json(), "data_source_id": None, "data_source_name": None}]


def test_catalog_rejects_duplicate_links_and_protects_referenced_skills(client: TestClient):
    headers = admin_headers(client)
    role = create_role(client, headers)
    skill = create_skill(client, headers)
    payload = {"career_role_id": role["id"], "career_skill_id": skill["id"], "requirement_type": "required", "expected_level": "intermediate", "weight": 100}

    assert client.post("/api/v1/admin/career-data/requirements", headers=headers, json=payload).status_code == 201
    duplicate = client.post("/api/v1/admin/career-data/requirements", headers=headers, json=payload)
    assert duplicate.status_code == 409
    assert duplicate.json()["detail"] == "This skill is already linked to the career role"

    protected_skill = client.delete(f"/api/v1/admin/career-data/skills/{skill['id']}", headers=headers)
    assert protected_skill.status_code == 409
    assert protected_skill.json()["detail"] == "Skill is still required by career roles"

    invalid_weight = client.post(
        "/api/v1/admin/career-data/requirements",
        headers=headers,
        json={**payload, "career_skill_id": skill["id"] + 10, "weight": 101},
    )
    assert invalid_weight.status_code == 422
