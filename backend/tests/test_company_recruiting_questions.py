import pytest
from fastapi.testclient import TestClient
from sqlalchemy import select

from app.core.database import get_db
from app.main import app
from app.models.company_recruiting import RecruitingPosition, RecruitingPositionQuestion
from app.models.recruiting import Organization, OrganizationMembership
from app.models.user import User

PASSWORD = "GucluParola123!"


def _register_user(client: TestClient, email: str, name: str) -> User:
    res = client.post("/api/v1/auth/register", json={"full_name": name, "email": email, "password": PASSWORD})
    assert res.status_code == 201
    with next(app.dependency_overrides[get_db]()) as db:
        return db.scalar(select(User).where(User.email == email))


def test_position_questions_crud(client: TestClient):
    user = _register_user(client, "company_q@example.com", "Soru Yöneticisi")
    org_id = "org-q-test"

    with next(app.dependency_overrides[get_db]()) as db:
        stored = db.get(User, user.id)
        stored.role = "company"
        org = Organization(
            id=org_id, name="Soru Corp", slug="soru-corp", organization_type="employer",
            size_band="smb", status="active", plan_code="growth", billing_email="billing@soru-corp.example.com"
        )
        membership = OrganizationMembership(
            id="mem-q-1", organization_id=org.id, user_id=user.id, role="owner", permissions=["positions.view", "positions.write"], status="active"
        )
        position = RecruitingPosition(
            id="pos-q-1", organization_id=org.id, title="Frontend Dev", slug="frontend-dev", public_id="FE100", status="published"
        )
        db.add_all([stored, org, membership, position])
        db.commit()

    # Login
    login_res = client.post("/api/v1/auth/login", data={"username": "company_q@example.com", "password": PASSWORD})
    token = login_res.json()["access_token"]
    headers = {"Authorization": f"Bearer {token}", "X-Organization-ID": org_id}

    # 1. Create Question (Text)
    res = client.post(
        f"/api/v1/company/positions/pos-q-1/questions",
        headers=headers,
        json={
            "question_text": "Kaç yıl Vue/React tecrübeniz var?",
            "question_type": "number",
            "is_required": True,
            "sort_order": 1,
        },
    )
    assert res.status_code == 201
    q_data = res.json()
    assert q_data["question_text"] == "Kaç yıl Vue/React tecrübeniz var?"
    assert q_data["question_type"] == "number"
    q_id = q_data["id"]

    # 2. Create Single Choice Question
    res_sc = client.post(
        f"/api/v1/company/positions/pos-q-1/questions",
        headers=headers,
        json={
            "question_text": "İngilizce seviyeniz nedir?",
            "question_type": "single_choice",
            "options": ["B1", "B2", "C1", "C2"],
            "is_required": True,
            "sort_order": 2,
        },
    )
    assert res_sc.status_code == 201
    assert res_sc.json()["options"] == ["B1", "B2", "C1", "C2"]

    # 3. Get Questions
    res_list = client.get(f"/api/v1/company/positions/pos-q-1/questions", headers=headers)
    assert res_list.status_code == 200
    items = res_list.json()
    assert len(items) == 2

    # 4. Update Question
    res_up = client.put(
        f"/api/v1/company/positions/pos-q-1/questions/{q_id}",
        headers=headers,
        json={"question_text": "Toplam kaç yıl Frontend tecrübeniz var?"},
    )
    assert res_up.status_code == 200
    assert res_up.json()["question_text"] == "Toplam kaç yıl Frontend tecrübeniz var?"

    # 5. Delete Question
    res_del = client.delete(f"/api/v1/company/positions/pos-q-1/questions/{q_id}", headers=headers)
    assert res_del.status_code == 204

    res_list2 = client.get(f"/api/v1/company/positions/pos-q-1/questions", headers=headers)
    assert len(res_list2.json()) == 1
