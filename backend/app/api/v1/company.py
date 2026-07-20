from datetime import UTC, datetime
from typing import Annotated, Literal
from uuid import uuid4

from fastapi import APIRouter, Depends, Header, HTTPException, Query, Response, status
from sqlalchemy import func, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.core.company_permissions import (
    COMPANY_PERMISSION_KEYS,
    effective_company_permissions,
    normalize_company_permissions,
)
from app.core.database import get_db
from app.core.security import get_current_user, hash_password, verify_password
from app.models.recruiting import Organization, OrganizationInvitation, OrganizationMembership
from app.models.company_recruiting import RecruitingApplication, RecruitingPosition
from app.models.user import User
from app.schemas.company import (
    CompanyContextResponse,
    CompanyApplicationsResponse,
    CompanyAssessmentsResponse,
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
    CompanyPositionCreate,
    CompanyPositionResponse,
    CompanyPositionsResponse,
    CompanyPositionUpdate,
)
from app.services.company import CompanyInvitationConflict, create_company_invitation, invitation_hash
from app.services.company_recruiting import applications as recruiting_applications
from app.services.company_recruiting import assessments as recruiting_assessments
from app.services.company_recruiting import dashboard as recruiting_dashboard


router = APIRouter()
DB = Annotated[Session, Depends(get_db)]

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
        permissions=effective_company_permissions(membership),
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
    if permission not in effective_company_permissions(context[2]):
        raise HTTPException(status_code=403, detail="Company permission required")
    return context


def _ensure_permission_grant(context, permissions: list[str]) -> None:
    if context[2].role == "owner":
        return
    excess = sorted(set(permissions) - set(effective_company_permissions(context[2])))
    if excess:
        raise HTTPException(
            status_code=403,
            detail="You cannot grant company permissions you do not have",
        )


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
def dashboard(
    db: DB,
    period: Literal["7d", "30d", "90d"] = "30d",
    context=Depends(_context),
) -> CompanyDashboardResponse:
    _, organization, membership = _require_permission(context, "dashboard.view")
    return recruiting_dashboard(db, organization, _summary(organization, membership), period)


def _position_response(db: Session, position: RecruitingPosition) -> CompanyPositionResponse:
    application_count = db.scalar(select(func.count()).select_from(RecruitingApplication).where(
        RecruitingApplication.organization_id == position.organization_id,
        RecruitingApplication.position_id == position.id,
    )) or 0
    return CompanyPositionResponse(
        id=position.id,
        title=position.title,
        department=position.department,
        employment_type=position.employment_type,
        workplace_type=position.workplace_type,
        description=position.description,
        status=position.status,
        application_deadline=position.application_deadline,
        opened_at=position.opened_at,
        closed_at=position.closed_at,
        application_count=application_count,
        created_at=position.created_at,
        updated_at=position.updated_at,
    )


@router.get("/positions", response_model=CompanyPositionsResponse)
def positions(
    db: DB,
    status_filter: Annotated[Literal["draft", "open", "paused", "closed", "archived"] | None, Query(alias="status")] = None,
    context=Depends(_context),
) -> CompanyPositionsResponse:
    _, organization, _ = _require_permission(context, "positions.view")
    statement = select(RecruitingPosition).where(RecruitingPosition.organization_id == organization.id)
    if status_filter is None:
        statement = statement.where(RecruitingPosition.status != "archived")
    else:
        statement = statement.where(RecruitingPosition.status == status_filter)
    rows = db.scalars(statement.order_by(RecruitingPosition.created_at.desc())).all()
    return CompanyPositionsResponse(items=[_position_response(db, row) for row in rows])


@router.post("/positions", response_model=CompanyPositionResponse, status_code=status.HTTP_201_CREATED)
def create_position(
    payload: CompanyPositionCreate,
    db: DB,
    context=Depends(_context),
) -> CompanyPositionResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    now = datetime.now(UTC)
    position = RecruitingPosition(
        id=str(uuid4()),
        organization_id=organization.id,
        created_by_membership_id=membership.id,
        opened_at=now if payload.status == "open" else None,
        **payload.model_dump(),
    )
    db.add(position)
    db.commit()
    db.refresh(position)
    return _position_response(db, position)


@router.patch("/positions/{position_id}", response_model=CompanyPositionResponse)
def update_position(
    position_id: str,
    payload: CompanyPositionUpdate,
    db: DB,
    context=Depends(_context),
) -> CompanyPositionResponse:
    _, organization, _ = _require_permission(context, "positions.write")
    position = db.scalar(select(RecruitingPosition).where(
        RecruitingPosition.id == position_id,
        RecruitingPosition.organization_id == organization.id,
    ))
    if position is None:
        raise HTTPException(status_code=404, detail="Company position not found")
    changes = payload.model_dump(exclude_unset=True)
    next_status = changes.get("status")
    now = datetime.now(UTC)
    if next_status == "open" and position.opened_at is None:
        position.opened_at = now
        position.closed_at = None
    elif next_status in {"closed", "archived"}:
        position.closed_at = now
    for key, value in changes.items():
        setattr(position, key, value)
    db.commit()
    db.refresh(position)
    return _position_response(db, position)


@router.delete("/positions/{position_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_position(position_id: str, db: DB, context=Depends(_context)) -> Response:
    _, organization, _ = _require_permission(context, "positions.delete")
    position = db.scalar(select(RecruitingPosition).where(
        RecruitingPosition.id == position_id,
        RecruitingPosition.organization_id == organization.id,
    ))
    if position is None:
        raise HTTPException(status_code=404, detail="Company position not found")
    position.status = "archived"
    position.closed_at = datetime.now(UTC)
    db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/applications", response_model=CompanyApplicationsResponse)
def applications(
    db: DB,
    queue: Literal["new", "assessment_pending", "technical_review", "scorecard_missing", "retention_due"] | None = None,
    stage: str | None = None,
    position_id: str | None = None,
    context=Depends(_context),
) -> CompanyApplicationsResponse:
    _, organization, _ = _require_permission(context, "applications.view")
    return recruiting_applications(db, organization, queue, stage, position_id)


@router.get("/assessments", response_model=CompanyAssessmentsResponse)
def assessments(db: DB, context=Depends(_context)) -> CompanyAssessmentsResponse:
    _, organization, _ = _require_permission(context, "assessments.view")
    return recruiting_assessments(db, organization)


@router.get("/members", response_model=CompanyMembersResponse)
def members(db: DB, context=Depends(_context)) -> CompanyMembersResponse:
    _require_permission(context, "members.view")
    _, organization, _ = context
    rows = db.execute(select(OrganizationMembership, User).join(User, User.id == OrganizationMembership.user_id).where(OrganizationMembership.organization_id == organization.id).order_by(User.full_name)).all()
    invitations = db.scalars(select(OrganizationInvitation).where(OrganizationInvitation.organization_id == organization.id, OrganizationInvitation.accepted_at.is_(None), OrganizationInvitation.expires_at > datetime.now(UTC)).order_by(OrganizationInvitation.created_at.desc())).all()
    return CompanyMembersResponse(
        permission_keys=list(COMPANY_PERMISSION_KEYS),
        members=[CompanyMemberResponse(membership_id=m.id, user_id=u.id, full_name=u.full_name, email=u.email, role=m.role, permissions=effective_company_permissions(m), status=m.status, created_at=m.created_at) for m, u in rows],
        pending_invitations=[CompanyPendingInviteResponse(id=i.id, email=i.email, role=i.role, permissions=effective_company_permissions(i), expires_at=i.expires_at) for i in invitations],
    )


@router.post("/invitations", response_model=CompanyInviteResponse, status_code=status.HTTP_201_CREATED)
def invite_member(payload: CompanyInviteCreate, db: DB, context=Depends(_context)) -> CompanyInviteResponse:
    current_user, organization, _ = _require_permission(context, "members.invite")
    if payload.role == "owner" and context[2].role != "owner":
        raise HTTPException(status_code=403, detail="Only an owner can invite another owner")
    permissions = normalize_company_permissions(payload.role, payload.permissions)
    _ensure_permission_grant(context, permissions)
    try:
        invitation, token = create_company_invitation(
            db, organization, str(payload.email), payload.role, current_user, permissions
        )
    except CompanyInvitationConflict as exception:
        raise HTTPException(status_code=409, detail=str(exception)) from exception
    return CompanyInviteResponse(token=token, email=invitation.email, role=invitation.role, permissions=effective_company_permissions(invitation), organization_id=organization.id, organization_name=organization.name, expires_at=invitation.expires_at)


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
    changes = payload.model_dump(exclude_unset=True, exclude_none=True)
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
    target_role = changes.get("role", membership.role)
    if "permissions" in changes or target_role == "owner":
        permissions = normalize_company_permissions(
            target_role,
            changes.get("permissions", membership.permissions),
        )
        _ensure_permission_grant(context, permissions)
        changes["permissions"] = permissions
    for key, value in changes.items():
        setattr(membership, key, value)
    db.commit(); db.refresh(membership)
    user = db.get(User, membership.user_id)
    return CompanyMemberResponse(membership_id=membership.id, user_id=user.id, full_name=user.full_name, email=user.email, role=membership.role, permissions=effective_company_permissions(membership), status=membership.status, created_at=membership.created_at)


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
    return CompanyInviteResponse(token=token, email=invitation.email, role=invitation.role, permissions=effective_company_permissions(invitation), organization_id=organization.id, organization_name=organization.name, expires_at=invitation.expires_at)


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
        db.add(OrganizationMembership(id=str(uuid4()), organization_id=invitation.organization_id, user_id=user.id, role=invitation.role, permissions=effective_company_permissions(invitation), status="active"))
        invitation.accepted_at = now
        db.commit()
    except IntegrityError as exception:
        db.rollback()
        raise HTTPException(status_code=409, detail="Company invitation conflicts with an existing account") from exception
    return {"accepted": True}
