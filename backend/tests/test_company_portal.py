from sqlalchemy import event, select
from sqlalchemy.orm import Session

from app.core.company_permissions import COMPANY_PERMISSION_KEYS
from app.core.database import get_db
from app.core.security import hash_password
from app.main import app
from app.models.recruiting import Organization, OrganizationInvitation, OrganizationMembership
from app.models.user import User


PASSWORD = "GucluParola123!"


def _register(client, email: str, name: str = "Kullanıcı") -> None:
    assert client.post("/api/v1/auth/register", json={"full_name": name, "email": email, "password": PASSWORD}).status_code == 201


def _headers(client, email: str) -> dict[str, str]:
    response = client.post("/api/v1/auth/login", data={"username": email, "password": PASSWORD})
    assert response.status_code == 200
    return {"Authorization": f"Bearer {response.json()['access_token']}"}


def _super_admin(email: str) -> None:
    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == email))
        user.is_admin = True
        user.role = "super_admin"
        db.commit()


def _create_org(client, headers, slug="acme") -> dict:
    response = client.post("/api/v1/admin/organizations", headers=headers, json={
        "name": f"{slug.title()} Teknoloji", "slug": slug, "organization_type": "employer",
        "size_band": "smb", "status": "active", "plan_code": "pilot",
        "billing_email": f"billing@{slug}.example.com", "website": f"https://{slug}.example.com",
    })
    assert response.status_code == 201
    return response.json()


def test_owner_invitation_creates_separate_company_account_and_is_single_use(client):
    _register(client, "root@example.com", "Platform Admin")
    _super_admin("root@example.com")
    admin_headers = _headers(client, "root@example.com")
    organization = _create_org(client, admin_headers)

    invited = client.post(
        f"/api/v1/admin/organizations/{organization['id']}/owner-invitations",
        headers=admin_headers,
        json={"email": "owner@acme.example.com", "role": "owner"},
    )
    assert invited.status_code == 201
    assert invited.json()["permissions"] == list(COMPANY_PERMISSION_KEYS)
    first_token = invited.json()["token"]

    replacement = client.post(
        f"/api/v1/admin/organizations/{organization['id']}/owner-invitations",
        headers=admin_headers,
        json={"email": "owner@acme.example.com", "role": "owner"},
    )
    assert replacement.status_code == 201
    token = replacement.json()["token"]
    assert client.get(f"/api/v1/company/invitations/{first_token}").status_code == 404

    details = client.get(f"/api/v1/company/invitations/{token}")
    assert details.status_code == 200
    assert details.json()["organization_name"] == "Acme Teknoloji"

    accepted = client.post(
        f"/api/v1/company/invitations/{token}/accept",
        json={"full_name": "Acme Owner", "password": PASSWORD},
    )
    assert accepted.status_code == 201
    assert client.post(f"/api/v1/company/invitations/{token}/accept", json={"full_name": "Acme Owner", "password": PASSWORD}).status_code == 404

    company_headers = _headers(client, "owner@acme.example.com")
    context = client.get("/api/v1/company/context", headers=company_headers)
    assert context.status_code == 200
    assert context.json()["memberships"][0]["role"] == "owner"
    assert context.json()["memberships"][0]["permissions"] == invited.json()["permissions"]
    for endpoint in ("/api/v1/panel/dashboard", "/api/v1/cv/documents", "/api/v1/career/profile"):
        assert client.get(endpoint, headers=company_headers).status_code == 403

    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == "owner@acme.example.com"))
        assert user.role == "company"
        assert user.is_admin is False


def test_company_context_is_tenant_scoped_and_candidate_is_forbidden(client):
    _register(client, "root@example.com")
    _super_admin("root@example.com")
    admin_headers = _headers(client, "root@example.com")
    first = _create_org(client, admin_headers, "first")
    second = _create_org(client, admin_headers, "second")
    _register(client, "candidate@example.com")

    assert client.get("/api/v1/company/context", headers=_headers(client, "candidate@example.com")).status_code == 403

    with next(app.dependency_overrides[get_db]()) as db:
        user = db.scalar(select(User).where(User.email == "candidate@example.com"))
        user.role = "company"
        db.add(OrganizationMembership(id="member-first", organization_id=first["id"], user_id=user.id, role="viewer", status="active"))
        db.commit()

    company_headers = _headers(client, "candidate@example.com")
    allowed = client.get("/api/v1/company/dashboard", headers={**company_headers, "X-Organization-ID": first["id"]})
    forbidden = client.get("/api/v1/company/dashboard", headers={**company_headers, "X-Organization-ID": second["id"]})
    assert allowed.status_code == 200
    assert allowed.json()["organization"]["organization_id"] == first["id"]
    assert forbidden.status_code == 404


def test_company_admin_cannot_promote_member_to_owner(client):
    _register(client, "root@example.com")
    _super_admin("root@example.com")
    organization = _create_org(client, _headers(client, "root@example.com"))
    _register(client, "company-admin@example.com", "Company Admin")
    _register(client, "viewer@example.com", "Viewer")

    with next(app.dependency_overrides[get_db]()) as db:
        company_admin = db.scalar(select(User).where(User.email == "company-admin@example.com"))
        viewer = db.scalar(select(User).where(User.email == "viewer@example.com"))
        company_admin.role = "company"
        viewer.role = "company"
        db.add_all([
            OrganizationMembership(
                id="member-admin",
                organization_id=organization["id"],
                user_id=company_admin.id,
                role="admin",
                status="active",
            ),
            OrganizationMembership(
                id="member-viewer",
                organization_id=organization["id"],
                user_id=viewer.id,
                role="viewer",
                status="active",
            ),
        ])
        db.commit()

    locked_statements = []

    def capture_for_update(execute_state):
        if getattr(execute_state.statement, "_for_update_arg", None) is not None:
            locked_statements.append(execute_state.statement)

    event.listen(Session, "do_orm_execute", capture_for_update)
    try:
        response = client.patch(
            "/api/v1/company/members/member-viewer",
            headers={
                **_headers(client, "company-admin@example.com"),
                "X-Organization-ID": organization["id"],
            },
            json={"role": "owner", "status": "active"},
        )
    finally:
        event.remove(Session, "do_orm_execute", capture_for_update)

    assert response.status_code == 403
    assert locked_statements


def test_company_permissions_are_persisted_enforced_and_cannot_be_escalated(client):
    _register(client, "root@example.com")
    _super_admin("root@example.com")
    organization = _create_org(client, _headers(client, "root@example.com"))
    _register(client, "manager@example.com", "Manager")
    _register(client, "target@example.com", "Target")

    with next(app.dependency_overrides[get_db]()) as db:
        manager = db.scalar(select(User).where(User.email == "manager@example.com"))
        target = db.scalar(select(User).where(User.email == "target@example.com"))
        manager.role = "company"
        target.role = "company"
        db.add_all([
            OrganizationMembership(
                id="stored-manager",
                organization_id=organization["id"],
                user_id=manager.id,
                role="admin",
                status="active",
                permissions=["dashboard.view", "members.view", "members.manage"],
            ),
            OrganizationMembership(
                id="stored-target",
                organization_id=organization["id"],
                user_id=target.id,
                role="admin",
                status="active",
                permissions=["dashboard.view"],
            ),
        ])
        db.commit()

    manager_headers = {
        **_headers(client, "manager@example.com"),
        "X-Organization-ID": organization["id"],
    }
    target_headers = {
        **_headers(client, "target@example.com"),
        "X-Organization-ID": organization["id"],
    }
    assert client.get("/api/v1/company/members", headers=target_headers).status_code == 403

    updated = client.patch(
        "/api/v1/company/members/stored-target",
        headers=manager_headers,
        json={"permissions": ["members.view"]},
    )
    assert updated.status_code == 200
    assert updated.json()["permissions"] == ["dashboard.view", "members.view"]
    assert client.get("/api/v1/company/members", headers=target_headers).status_code == 200

    forbidden_grant = client.patch(
        "/api/v1/company/members/stored-target",
        headers=manager_headers,
        json={"permissions": ["organization.update"]},
    )
    assert forbidden_grant.status_code == 403
    with next(app.dependency_overrides[get_db]()) as db:
        unchanged = db.get(OrganizationMembership, "stored-target")
        assert unchanged.permissions == ["dashboard.view", "members.view"]


def test_non_owner_cannot_invite_with_permissions_they_do_not_have(client):
    _register(client, "root@example.com")
    _super_admin("root@example.com")
    organization = _create_org(client, _headers(client, "root@example.com"))
    _register(client, "inviter@example.com", "Limited Inviter")
    with next(app.dependency_overrides[get_db]()) as db:
        inviter = db.scalar(select(User).where(User.email == "inviter@example.com"))
        inviter.role = "company"
        db.add(
            OrganizationMembership(
                id="limited-inviter",
                organization_id=organization["id"],
                user_id=inviter.id,
                role="admin",
                status="active",
                permissions=["dashboard.view", "members.invite"],
            )
        )
        db.commit()

    response = client.post(
        "/api/v1/company/invitations",
        headers={
            **_headers(client, "inviter@example.com"),
            "X-Organization-ID": organization["id"],
        },
        json={
            "email": "escalated@acme.example.com",
            "role": "admin",
            "permissions": ["organization.update"],
        },
    )

    assert response.status_code == 403
    with next(app.dependency_overrides[get_db]()) as db:
        pending = db.scalar(
            select(OrganizationInvitation).where(
                OrganizationInvitation.email == "escalated@acme.example.com"
            )
        )
        assert pending is None


def test_company_invitation_rejects_unknown_permission(client):
    _register(client, "root@example.com")
    _super_admin("root@example.com")
    organization = _create_org(client, _headers(client, "root@example.com"))
    owner_invitation = client.post(
        f"/api/v1/admin/organizations/{organization['id']}/owner-invitations",
        headers=_headers(client, "root@example.com"),
        json={"email": "owner@acme.example.com", "role": "owner"},
    ).json()
    client.post(
        f"/api/v1/company/invitations/{owner_invitation['token']}/accept",
        json={"full_name": "Owner", "password": PASSWORD},
    )

    response = client.post(
        "/api/v1/company/invitations",
        headers={
            **_headers(client, "owner@acme.example.com"),
            "X-Organization-ID": organization["id"],
        },
        json={
            "email": "invalid@acme.example.com",
            "role": "recruiter",
            "permissions": ["applications.destroy"],
        },
    )

    assert response.status_code == 422


def test_candidate_email_cannot_receive_company_invitation(client):
    _register(client, "root@example.com")
    _super_admin("root@example.com")
    admin_headers = _headers(client, "root@example.com")
    organization = _create_org(client, admin_headers)
    _register(client, "candidate@example.com")

    response = client.post(
        f"/api/v1/admin/organizations/{organization['id']}/owner-invitations",
        headers=admin_headers,
        json={"email": "candidate@example.com", "role": "owner"},
    )
    assert response.status_code == 409


def test_invitation_cannot_be_consumed_by_an_existing_organization_member(client):
    _register(client, "root@example.com")
    _super_admin("root@example.com")
    admin_headers = _headers(client, "root@example.com")
    organization = _create_org(client, admin_headers)
    invited = client.post(
        f"/api/v1/admin/organizations/{organization['id']}/owner-invitations",
        headers=admin_headers,
        json={"email": "member@acme.example.com", "role": "owner"},
    )
    assert invited.status_code == 201

    with next(app.dependency_overrides[get_db]()) as db:
        user = User(
            full_name="Existing Member",
            email="member@acme.example.com",
            hashed_password=hash_password(PASSWORD),
            role="company",
            is_admin=False,
            admin_permissions=[],
        )
        db.add(user)
        db.flush()
        db.add(
            OrganizationMembership(
                id="existing-member",
                organization_id=organization["id"],
                user_id=user.id,
                role="viewer",
                status="active",
            )
        )
        db.commit()

    response = client.post(
        f"/api/v1/company/invitations/{invited.json()['token']}/accept",
        json={"full_name": "Existing Member", "password": PASSWORD},
    )
    assert response.status_code == 409
    assert response.json()["detail"] == "User is already a member of this organization"
