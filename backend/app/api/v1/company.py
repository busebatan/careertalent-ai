from datetime import UTC, datetime
from typing import Annotated
from uuid import uuid4

from fastapi import APIRouter, Depends, Header, HTTPException, status
from sqlalchemy import func, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.security import get_current_user, hash_password, verify_password
from app.models.recruiting import Organization, OrganizationInvitation, OrganizationMembership
from app.models.user import User
from app.schemas.company import (
    CompanyContextResponse,
    CompanyDashboardResponse,
    CompanyInviteAccept,
    CompanyInviteCreate,
    CompanyInviteResponse,
    CompanyMemberResponse,
    CompanyMembersResponse,
    CompanyMemberUpdate,
    CompanyMembershipSummary,
    CompanyOrganizationUpdate,
    CompanyOrganizationProfile,
    CompanyPendingInviteResponse,
)
from app.services.company import CompanyInvitationConflict, create_company_invitation, invitation_hash


router = APIRouter()
DB = Annotated[Session, Depends(get_db)]

ROLE_PERMISSIONS = {
    "owner": ["dashboard.view", "organization.update", "members.view", "members.invite", "members.manage"],
    "admin": ["dashboard.view", "organization.update", "members.view", "members.invite", "members.manage"],
    "recruiter": ["dashboard.view", "members.view"],
    "hiring_manager": ["dashboard.view", "members.view"],
    "viewer": ["dashboard.view", "members.view"],
}


def require_company_user(current_user: User = Depends(get_current_user)) -> User:
    if current_user.role != "company" or current_user.is_admin:
        raise HTTPException(status_code=403, detail="Company account required")
    return current_user


def _summary(organization: Organization, membership: OrganizationMembership) -> CompanyMembershipSummary:
    return CompanyMembershipSummary(
        organization_id=organization.id,
        organization_name=organization.name,
        organization_slug=organization.slug,
        organization_type=organization.organization_type,
        organization_status=organization.status,
        plan_code=organization.plan_code,
        billing_email=organization.billing_email,
        website=organization.website,
        role=membership.role,
        permissions=ROLE_PERMISSIONS[membership.role],
    )


def _context(
    db: DB,
    organization_id: Annotated[str, Header(alias="X-Organization-ID")],
    current_user: User = Depends(require_company_user),
) -> tuple[User, Organization, OrganizationMembership]:
    row = db.execute(
        select(Organization, OrganizationMembership)
        .join(OrganizationMembership, OrganizationMembership.organization_id == Organization.id)
        .where(
            Organization.id == organization_id,
            OrganizationMembership.user_id == current_user.id,
            OrganizationMembership.status == "active",
            Organization.status.in_(["onboarding", "active"]),
        )
    ).one_or_none()
    if row is None:
        raise HTTPException(status_code=404, detail="Company organization not found")
    return current_user, row[0], row[1]


def _require_permission(context, permission: str):
    if permission not in ROLE_PERMISSIONS[context[2].role]:
        raise HTTPException(status_code=403, detail="Company permission required")
    return context


def _expired(value: datetime) -> bool:
    comparable = value if value.tzinfo is not None else value.replace(tzinfo=UTC)
    return comparable.astimezone(UTC) <= datetime.now(UTC)


@router.get("/organizations/{slug}", response_model=CompanyOrganizationProfile)
def organization_profile(slug: str, db: DB) -> CompanyOrganizationProfile:
    organization = db.scalar(
        select(Organization).where(
            Organization.slug == slug.lower(),
            Organization.status.in_(["onboarding", "active"]),
        )
    )
    if organization is None:
        raise HTTPException(status_code=404, detail="Company organization not found")
    settings = organization.settings if isinstance(organization.settings, dict) else {}
    return CompanyOrganizationProfile(
        name=organization.name,
        slug=organization.slug,
        website=organization.website,
        description=settings.get("description"),
        logo_url=settings.get("logo_url"),
    )


@router.get("/context", response_model=CompanyContextResponse)
def context(db: DB, current_user: User = Depends(require_company_user)) -> CompanyContextResponse:
    rows = db.execute(
        select(Organization, OrganizationMembership)
        .join(OrganizationMembership, OrganizationMembership.organization_id == Organization.id)
        .where(
            OrganizationMembership.user_id == current_user.id,
            OrganizationMembership.status == "active",
            Organization.status.in_(["onboarding", "active"]),
        )
        .order_by(Organization.name)
    ).all()
    return CompanyContextResponse(memberships=[_summary(org, membership) for org, membership in rows])


@router.get("/dashboard", response_model=CompanyDashboardResponse)
def dashboard(db: DB, context=Depends(_context)) -> CompanyDashboardResponse:
    _, organization, membership = context
    members_total = db.scalar(select(func.count()).select_from(OrganizationMembership).where(OrganizationMembership.organization_id == organization.id)) or 0
    members_active = db.scalar(select(func.count()).select_from(OrganizationMembership).where(OrganizationMembership.organization_id == organization.id, OrganizationMembership.status == "active")) or 0
    invitations_pending = db.scalar(select(func.count()).select_from(OrganizationInvitation).where(OrganizationInvitation.organization_id == organization.id, OrganizationInvitation.accepted_at.is_(None), OrganizationInvitation.expires_at > datetime.now(UTC))) or 0
    return CompanyDashboardResponse(organization=_summary(organization, membership), members_total=members_total, members_active=members_active, invitations_pending=invitations_pending)


@router.get("/members", response_model=CompanyMembersResponse)
def members(db: DB, context=Depends(_context)) -> CompanyMembersResponse:
    _require_permission(context, "members.view")
    _, organization, _ = context
    rows = db.execute(select(OrganizationMembership, User).join(User, User.id == OrganizationMembership.user_id).where(OrganizationMembership.organization_id == organization.id).order_by(User.full_name)).all()
    invitations = db.scalars(select(OrganizationInvitation).where(OrganizationInvitation.organization_id == organization.id, OrganizationInvitation.accepted_at.is_(None), OrganizationInvitation.expires_at > datetime.now(UTC)).order_by(OrganizationInvitation.created_at.desc())).all()
    return CompanyMembersResponse(
        members=[CompanyMemberResponse(membership_id=m.id, user_id=u.id, full_name=u.full_name, email=u.email, role=m.role, status=m.status, created_at=m.created_at) for m, u in rows],
        pending_invitations=[CompanyPendingInviteResponse(id=i.id, email=i.email, role=i.role, expires_at=i.expires_at) for i in invitations],
    )


@router.post("/invitations", response_model=CompanyInviteResponse, status_code=status.HTTP_201_CREATED)
def invite_member(payload: CompanyInviteCreate, db: DB, context=Depends(_context)) -> CompanyInviteResponse:
    current_user, organization, _ = _require_permission(context, "members.invite")
    if payload.role == "owner" and context[2].role != "owner":
        raise HTTPException(status_code=403, detail="Only an owner can invite another owner")
    try:
        invitation, token = create_company_invitation(
            db, organization, str(payload.email), payload.role, current_user
        )
    except CompanyInvitationConflict as exception:
        raise HTTPException(status_code=409, detail=str(exception)) from exception
    return CompanyInviteResponse(token=token, email=invitation.email, role=invitation.role, organization_id=organization.id, organization_name=organization.name, expires_at=invitation.expires_at)


@router.patch("/members/{membership_id}", response_model=CompanyMemberResponse)
def update_member(membership_id: str, payload: CompanyMemberUpdate, db: DB, context=Depends(_context)) -> CompanyMemberResponse:
    current_user, organization, _ = _require_permission(context, "members.manage")
    db.execute(
        select(Organization.id)
        .where(Organization.id == organization.id)
        .with_for_update()
    ).scalar_one()
    membership = db.scalar(select(OrganizationMembership).where(OrganizationMembership.id == membership_id, OrganizationMembership.organization_id == organization.id))
    if membership is None:
        raise HTTPException(status_code=404, detail="Company member not found")
    if membership.user_id == current_user.id:
        raise HTTPException(status_code=422, detail="You cannot change your own membership")
    changes = payload.model_dump(exclude_unset=True)
    if (
        (membership.role == "owner" or changes.get("role") == "owner")
        and context[2].role != "owner"
    ):
        raise HTTPException(status_code=403, detail="Only an owner can manage another owner")
    removing_active_owner = membership.role == "owner" and (
        changes.get("role", "owner") != "owner" or changes.get("status", membership.status) != "active"
    )
    if removing_active_owner:
        active_owners = db.scalar(select(func.count()).select_from(OrganizationMembership).where(
            OrganizationMembership.organization_id == organization.id,
            OrganizationMembership.role == "owner",
            OrganizationMembership.status == "active",
        )) or 0
        if active_owners <= 1:
            raise HTTPException(status_code=422, detail="Organization must keep an active owner")
    for key, value in changes.items():
        setattr(membership, key, value)
    db.commit(); db.refresh(membership)
    user = db.get(User, membership.user_id)
    return CompanyMemberResponse(membership_id=membership.id, user_id=user.id, full_name=user.full_name, email=user.email, role=membership.role, status=membership.status, created_at=membership.created_at)


@router.patch("/organization", response_model=CompanyMembershipSummary)
def update_organization(payload: CompanyOrganizationUpdate, db: DB, context=Depends(_context)) -> CompanyMembershipSummary:
    _, organization, membership = _require_permission(context, "organization.update")
    organization.name = " ".join(payload.name.split())
    organization.billing_email = str(payload.billing_email).lower()
    organization.website = payload.website.strip() if payload.website else None
    db.commit(); db.refresh(organization)
    return _summary(organization, membership)


@router.get("/invitations/{token}", response_model=CompanyInviteResponse)
def invitation_details(token: str, db: DB) -> CompanyInviteResponse:
    invitation = db.scalar(select(OrganizationInvitation).where(OrganizationInvitation.token_hash == invitation_hash(token)))
    if invitation is None or invitation.accepted_at is not None or _expired(invitation.expires_at):
        raise HTTPException(status_code=404, detail="Company invitation not found")
    organization = db.get(Organization, invitation.organization_id)
    if organization is None or organization.status not in {"onboarding", "active"}:
        raise HTTPException(status_code=404, detail="Company invitation not found")
    return CompanyInviteResponse(token=token, email=invitation.email, role=invitation.role, organization_id=organization.id, organization_name=organization.name, expires_at=invitation.expires_at)


@router.post("/invitations/{token}/accept", status_code=status.HTTP_201_CREATED)
def accept_invitation(token: str, payload: CompanyInviteAccept, db: DB):
    invitation = db.scalar(
        select(OrganizationInvitation)
        .where(OrganizationInvitation.token_hash == invitation_hash(token))
        .with_for_update()
    )
    now = datetime.now(UTC)
    if invitation is None or invitation.accepted_at is not None or _expired(invitation.expires_at):
        raise HTTPException(status_code=404, detail="Company invitation not found")
    organization = db.get(Organization, invitation.organization_id)
    if organization is None or organization.status not in {"onboarding", "active"}:
        raise HTTPException(status_code=404, detail="Company invitation not found")
    try:
        user = db.scalar(select(User).where(func.lower(User.email) == invitation.email))
        if user is None:
            user = User(full_name=" ".join(payload.full_name.split()), email=invitation.email, hashed_password=hash_password(payload.password), role="company", is_admin=False, admin_permissions=[])
            db.add(user); db.flush()
        elif user.role != "company" or not user.is_active or not verify_password(payload.password, user.hashed_password):
            raise HTTPException(status_code=409, detail="Email belongs to another account or password is invalid")
        existing = db.scalar(select(OrganizationMembership).where(OrganizationMembership.organization_id == invitation.organization_id, OrganizationMembership.user_id == user.id))
        if existing is not None:
            raise HTTPException(status_code=409, detail="User is already a member of this organization")
        db.add(OrganizationMembership(id=str(uuid4()), organization_id=invitation.organization_id, user_id=user.id, role=invitation.role, status="active"))
        invitation.accepted_at = now
        db.commit()
    except IntegrityError as exception:
        db.rollback()
        raise HTTPException(status_code=409, detail="Company invitation conflicts with an existing account") from exception
    return {"accepted": True}
