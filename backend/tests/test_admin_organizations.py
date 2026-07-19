import pytest
from sqlalchemy import func, select
from sqlalchemy.exc import IntegrityError

from app.core.database import get_db
from app.main import app
from app.models.recruiting import Organization, OrganizationMembership
from app.models.user import User


PASSWORD = "GucluParola123!"


def _register(client, email: str) -> None:
    response = client.post(
        "/api/v1/auth/register",
        json={"full_name": "Yönetici", "email": email, "password": PASSWORD},
    )
    assert response.status_code == 201


def _promote(email: str, permissions: list[str] | None = None) -> None:
    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == email))
        assert user is not None
        user.is_admin = True
        if permissions is not None:
            user.role = "admin"
            user.admin_permissions = permissions
        db.commit()


def _headers(client, email: str) -> dict[str, str]:
    response = client.post(
        "/api/v1/auth/login",
        data={"username": email, "password": PASSWORD},
    )
    assert response.status_code == 200
    return {"Authorization": f"Bearer {response.json()['access_token']}"}


def _payload(**overrides) -> dict:
    payload = {
        "name": "Acme Teknoloji",
        "slug": "acme-teknoloji",
        "organization_type": "employer",
        "size_band": "smb",
        "status": "onboarding",
        "plan_code": "pilot",
        "billing_email": "billing@acme.example.com",
        "website": "https://acme.test",
        "description": "Teknoloji ekipleri için sürdürülebilir işe alım.",
        "logo_url": "https://cdn.acme.test/logo.svg",
    }
    payload.update(overrides)
    return payload


def test_organization_admin_endpoints_require_explicit_permission(client):
    _register(client, "limited@example.com")
    _promote("limited@example.com", ["dashboard.view"])

    response = client.get(
        "/api/v1/admin/organizations",
        headers=_headers(client, "limited@example.com"),
    )

    assert response.status_code == 403
    assert response.json()["detail"] == "Admin permission required"


def test_admin_can_create_list_and_update_tenant_organizations(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")

    created = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json=_payload(),
    )

    assert created.status_code == 201
    organization_id = created.json()["id"]
    assert created.json() == {
        "id": organization_id,
        "name": "Acme Teknoloji",
        "slug": "acme-teknoloji",
        "organization_type": "employer",
        "size_band": "smb",
        "status": "onboarding",
        "plan_code": "pilot",
        "billing_email": "billing@acme.example.com",
        "website": "https://acme.test/",
        "description": "Teknoloji ekipleri için sürdürülebilir işe alım.",
        "logo_url": "https://cdn.acme.test/logo.svg",
        "members_count": 0,
        "created_at": created.json()["created_at"],
        "updated_at": created.json()["updated_at"],
    }

    listed = client.get("/api/v1/admin/organizations", headers=headers)

    assert listed.status_code == 200
    assert listed.json()["total"] == 1
    assert listed.json()["organizations"][0]["id"] == organization_id

    updated = client.patch(
        f"/api/v1/admin/organizations/{organization_id}",
        headers=headers,
        json={"status": "active", "plan_code": "growth", "size_band": "mid_market"},
    )

    assert updated.status_code == 200
    assert updated.json()["status"] == "active"
    assert updated.json()["plan_code"] == "growth"
    assert updated.json()["size_band"] == "mid_market"


def test_organization_slug_is_normalized_and_unique(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")

    first = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json=_payload(slug="  ACME-TEKNOLOJI  "),
    )
    duplicate = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json=_payload(name="Acme İkinci", slug="acme-teknoloji"),
    )

    assert first.status_code == 201
    assert first.json()["slug"] == "acme-teknoloji"
    assert duplicate.status_code == 409
    assert duplicate.json()["detail"] == "Organization slug already exists"


def test_organization_slug_is_generated_from_name_and_collision_safe(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")
    payload = _payload(name="Büşe Kurum")
    payload.pop("slug")

    first = client.post("/api/v1/admin/organizations", headers=headers, json=payload)
    second = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json={**payload, "billing_email": "second@acme.example.com"},
    )

    assert first.status_code == 201
    assert first.json()["slug"] == "buse-kurum"
    assert second.status_code == 201
    assert second.json()["slug"] == "buse-kurum-2"


def test_public_company_profile_exposes_branding_only_for_available_organization(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")
    organization = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json=_payload(status="active"),
    ).json()

    profile = client.get("/api/v1/company/organizations/acme-teknoloji")

    assert profile.status_code == 200
    assert profile.json() == {
        "name": "Acme Teknoloji",
        "slug": "acme-teknoloji",
        "website": "https://acme.test/",
        "description": "Teknoloji ekipleri için sürdürülebilir işe alım.",
        "logo_url": "https://cdn.acme.test/logo.svg",
    }
    assert "billing_email" not in profile.json()

    client.patch(
        f"/api/v1/admin/organizations/{organization['id']}",
        headers=headers,
        json={"status": "suspended"},
    )
    assert client.get("/api/v1/company/organizations/acme-teknoloji").status_code == 404


def test_organization_contract_rejects_unknown_enum_values(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")

    response = client.post(
        "/api/v1/admin/organizations",
        headers=_headers(client, "admin@example.com"),
        json=_payload(organization_type="bootcamp", status="deleted"),
    )

    assert response.status_code == 422


@pytest.mark.parametrize("reserved_slug", ["admin", "up", "cikis", "livewire-update"])
def test_organization_contract_rejects_reserved_slug_and_insecure_logo(client, reserved_slug):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")

    reserved = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json=_payload(slug=reserved_slug),
    )
    insecure_logo = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json=_payload(slug="guvenli-kurum", logo_url="http://cdn.acme.test/logo.svg"),
    )

    assert reserved.status_code == 422
    assert reserved.json()["detail"] == "Organization slug is reserved"
    assert insecure_logo.status_code == 422


def test_user_can_join_multiple_tenants_but_only_once_per_organization(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")
    first = client.post(
        "/api/v1/admin/organizations", headers=headers, json=_payload()
    ).json()
    second = client.post(
        "/api/v1/admin/organizations",
        headers=headers,
        json=_payload(
            name="Beta Ajans",
            slug="beta-ajans",
            organization_type="agency",
            plan_code="agency",
            billing_email="billing@beta.example.com",
        ),
    ).json()

    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == "admin@example.com"))
        assert user is not None
        db.add_all(
            [
                OrganizationMembership(
                    id="membership-1",
                    organization_id=first["id"],
                    user_id=user.id,
                    role="owner",
                    status="active",
                ),
                OrganizationMembership(
                    id="membership-2",
                    organization_id=second["id"],
                    user_id=user.id,
                    role="recruiter",
                    status="active",
                ),
            ]
        )
        db.commit()
        count = db.scalar(
            select(func.count())
            .select_from(OrganizationMembership)
            .where(OrganizationMembership.user_id == user.id)
        )
        assert count == 2

        db.add(
            OrganizationMembership(
                id="membership-duplicate",
                organization_id=first["id"],
                user_id=user.id,
                role="viewer",
                status="active",
            )
        )
        with pytest.raises(IntegrityError):
            db.commit()


def test_organization_delete_closes_without_removing_tenant_data(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")
    organization = client.post(
        "/api/v1/admin/organizations", headers=headers, json=_payload()
    ).json()

    response = client.delete(
        f"/api/v1/admin/organizations/{organization['id']}", headers=headers
    )

    assert response.status_code == 204
    with next(app.dependency_overrides[get_db]()) as db:
        stored = db.get(Organization, organization["id"])
        assert stored is not None
        assert stored.status == "closed"


def test_admin_organization_detail_returns_members_and_invitations(client):
    _register(client, "admin@example.com")
    _promote("admin@example.com")
    headers = _headers(client, "admin@example.com")
    organization = client.post(
        "/api/v1/admin/organizations", headers=headers, json=_payload()
    ).json()

    response = client.get(
        f"/api/v1/admin/organizations/{organization['id']}", headers=headers
    )

    assert response.status_code == 200
    body = response.json()
    assert body["id"] == organization["id"]
    assert body["name"] == "Acme Teknoloji"
    assert body["members"] == []
    assert body["invitations"] == []
    assert client.get("/api/v1/admin/organizations/missing-id", headers=headers).status_code == 404
