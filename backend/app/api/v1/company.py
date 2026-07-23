from datetime import UTC, datetime
from typing import Annotated, Literal
from uuid import uuid4

from fastapi import APIRouter, Depends, Header, HTTPException, Query, Response, status
from pydantic import BaseModel
from sqlalchemy import case, func, or_, select, update
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
from app.models.company_recruiting import (
    OrganizationAtsConfiguration,
    RecruitingApplication,
    RecruitingApplicationSnapshot,
    RecruitingApplicationStageEvent,
    RecruitingAssessment,
    RecruitingPosition,
    RecruitingPositionActivity,
    RecruitingPositionAiAnalysis,
    RecruitingPositionCriteriaVersion,
    RecruitingPositionQuestion,
    RecruitingShareLink,
    new_public_id,
    new_short_code,
)
from app.models.user import User
from app.schemas.company import (
    CompanyContextResponse,
    CompanyAtsConfigResponse,
    CompanyAtsConfigUpdate,
    CompanyApplicationsResponse,
    CompanyApplicationAction,
    CompanyApplicationActionResponse,
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
    CompanyPositionDetailResponse,
    CompanyPositionCounts,
    CompanyPositionResponse,
    CompanyPositionsResponse,
    CompanyPositionUpdate,
    CompanyCriteriaVersionResponse,
    CompanyCriteriaVersionUpdate,
    CompanyPositionAiAnalysisResponse,
    CompanyPositionMemberResponse,
    CompanyPositionQuestionCreate,
    CompanyPositionQuestionResponse,
    CompanyPositionQuestionUpdate,
    CompanyShareLinkCreate,
    CompanyShareLinkResponse,
    CompanyShareLinkUpdate,
)
from app.services.company import CompanyInvitationConflict, create_company_invitation, invitation_hash
from app.services.company_recruiting import applications as recruiting_applications
from app.services.company_recruiting import assessments as recruiting_assessments
from app.services.company_recruiting import dashboard as recruiting_dashboard
from app.services.company_positions import (
    activity_response,
    add_activity,
    analysis_response,
    ats_config_response,
    criteria_response,
    effective_ats_config,
    position_counts,
    position_response,
    request_position_analysis,
    share_link_response,
    slugify,
)
from app.services.company_outbox import POSITION_ANALYSIS_TASK, enqueue_company_task


router = APIRouter()
DB = Annotated[Session, Depends(get_db)]
_POSITION_TRANSITIONS = {
    "draft": {"published", "archived"},
    "published": {"paused", "closed", "archived"},
    "paused": {"published", "closed", "archived"},
    "closed": {"archived"},
    "archived": set(),
}


def _response_payload(model: BaseModel) -> dict:
    return model.model_dump(mode="python")

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


def _position(db: Session, organization_id: str, position_id: str) -> RecruitingPosition:
    row = db.scalar(select(RecruitingPosition).where(
        RecruitingPosition.id == position_id,
        RecruitingPosition.organization_id == organization_id,
    ))
    if row is None:
        raise HTTPException(status_code=404, detail="Company position not found")
    return row


def _validate_position_assignees(db: Session, organization_id: str, values: dict) -> None:
    for key in ("recruiter_membership_id", "technical_manager_membership_id"):
        membership_id = values.get(key)
        if membership_id is None:
            continue
        membership = db.scalar(select(OrganizationMembership).where(
            OrganizationMembership.id == membership_id,
            OrganizationMembership.organization_id == organization_id,
            OrganizationMembership.status == "active",
        ))
        if membership is None:
            raise HTTPException(status_code=422, detail=f"{key} must belong to the active organization")


@router.get("/positions", response_model=CompanyPositionsResponse)
def positions(
    db: DB,
    status_filter: Annotated[Literal["draft", "published", "paused", "closed", "archived"] | None, Query(alias="status")] = None,
    q: Annotated[str | None, Query(max_length=120)] = None,
    search: Annotated[str | None, Query(max_length=120)] = None,
    page: Annotated[int, Query(ge=1)] = 1,
    page_size: Annotated[int, Query(ge=1, le=100)] = 25,
    context=Depends(_context),
) -> CompanyPositionsResponse:
    _, organization, _ = _require_permission(context, "positions.view")
    base_filters = [RecruitingPosition.organization_id == organization.id]
    search_value = (q or search or "").strip()
    if search_value:
        needle = f"%{search_value}%"
        base_filters.append(or_(
            RecruitingPosition.title.ilike(needle), RecruitingPosition.department.ilike(needle),
            RecruitingPosition.location.ilike(needle),
        ))
    status_counts = {key: 0 for key in ("draft", "published", "paused", "closed", "archived")}
    for key, count in db.execute(
        select(RecruitingPosition.status, func.count())
        .where(*base_filters)
        .group_by(RecruitingPosition.status)
    ):
        status_counts[key] = int(count)
    statement = select(RecruitingPosition).where(*base_filters)
    if status_filter is None:
        statement = statement.where(RecruitingPosition.status != "archived")
    else:
        statement = statement.where(RecruitingPosition.status == status_filter)
    total = db.scalar(select(func.count()).select_from(statement.subquery())) or 0
    rows = db.scalars(statement.order_by(RecruitingPosition.created_at.desc()).offset((page - 1) * page_size).limit(page_size)).all()
    ids = [row.id for row in rows]
    counts_by_id: dict[str, dict[str, int]] = {row.id: {"applications": 0, "shortlisted": 0, "completed": 0} for row in rows}
    if ids:
        for position_id, application_count, shortlisted_count in db.execute(
            select(
                RecruitingApplication.position_id, func.count(RecruitingApplication.id),
                func.sum(case((RecruitingApplication.current_stage == "shortlisted", 1), else_=0)),
            )
            .where(RecruitingApplication.organization_id == organization.id, RecruitingApplication.position_id.in_(ids))
            .group_by(RecruitingApplication.position_id)
        ):
            counts_by_id[position_id]["applications"] = int(application_count or 0)
            counts_by_id[position_id]["shortlisted"] = int(shortlisted_count or 0)
        for position_id, completed_count in db.execute(
            select(RecruitingApplication.position_id, func.count(func.distinct(RecruitingAssessment.application_id)))
            .join(RecruitingAssessment, RecruitingAssessment.application_id == RecruitingApplication.id)
            .where(
                RecruitingApplication.organization_id == organization.id,
                RecruitingApplication.position_id.in_(ids), RecruitingAssessment.status == "completed",
            )
            .group_by(RecruitingApplication.position_id)
        ):
            counts_by_id[position_id]["completed"] = int(completed_count or 0)
    member_ids = {value for row in rows for value in (row.recruiter_membership_id, row.technical_manager_membership_id) if value}
    member_names = dict(db.execute(
        select(OrganizationMembership.id, User.full_name)
        .join(User, User.id == OrganizationMembership.user_id)
        .where(OrganizationMembership.organization_id == organization.id, OrganizationMembership.id.in_(member_ids))
    ).all()) if member_ids else {}
    return CompanyPositionsResponse.model_validate({
        "items": [
            _response_payload(position_response(
                db, organization, row,
                counts=CompanyPositionCounts(
                    applications=counts_by_id[row.id]["applications"],
                    assessment_completed=counts_by_id[row.id]["completed"],
                    shortlisted=counts_by_id[row.id]["shortlisted"],
                ),
                recruiter_name=member_names.get(row.recruiter_membership_id),
                technical_manager_name=member_names.get(row.technical_manager_membership_id),
                resolve_members=False,
            ))
            for row in rows
        ],
        "total": total,
        "page": page,
        "page_size": page_size,
        "status_counts": status_counts,
    })


@router.post("/positions", response_model=CompanyPositionResponse, status_code=status.HTTP_201_CREATED)
def create_position(
    payload: CompanyPositionCreate,
    db: DB,
    context=Depends(_context),
) -> CompanyPositionResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    now = datetime.now(UTC)
    values = payload.model_dump()
    _validate_position_assignees(db, organization.id, values)
    if payload.status == "published" and payload.application_deadline is not None:
        deadline = payload.application_deadline if payload.application_deadline.tzinfo is not None else payload.application_deadline.replace(tzinfo=UTC)
        if deadline <= now:
            raise HTTPException(status_code=422, detail="Published position deadline must be in the future")
    position = RecruitingPosition(
        id=str(uuid4()),
        organization_id=organization.id,
        created_by_membership_id=membership.id,
        slug=slugify(payload.title),
        opened_at=now if payload.status == "published" else None,
        **values,
    )
    db.add(position)
    db.flush()
    add_activity(db, position, "position.created", membership_id=membership.id, details={"status": position.status})
    db.commit()
    db.refresh(position)
    return position_response(db, organization, position)


@router.patch("/positions/{position_id}", response_model=CompanyPositionResponse)
def update_position(
    position_id: str,
    payload: CompanyPositionUpdate,
    db: DB,
    context=Depends(_context),
) -> CompanyPositionResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    position = _position(db, organization.id, position_id)
    changes = payload.model_dump(exclude_unset=True)
    _validate_position_assignees(db, organization.id, changes)
    salary_min = changes.get("salary_min", position.salary_min)
    salary_max = changes.get("salary_max", position.salary_max)
    if salary_min is not None and salary_max is not None and salary_min > salary_max:
        raise HTTPException(status_code=422, detail="salary_min cannot exceed salary_max")
    next_status = changes.get("status")
    now = datetime.now(UTC)
    if next_status and next_status != position.status and next_status not in _POSITION_TRANSITIONS[position.status]:
        raise HTTPException(status_code=409, detail=f"Position cannot transition from {position.status} to {next_status}")
    deadline = changes.get("application_deadline", position.application_deadline)
    if next_status == "published" and deadline is not None:
        comparable_deadline = deadline if deadline.tzinfo is not None else deadline.replace(tzinfo=UTC)
        if comparable_deadline <= now:
            raise HTTPException(status_code=422, detail="Published position deadline must be in the future")
    previous_status = position.status
    if next_status == "published" and position.opened_at is None:
        position.opened_at = now
        position.closed_at = None
    elif next_status in {"closed", "archived"}:
        position.closed_at = now
    if "title" in changes:
        position.slug = slugify(changes["title"])
    for key, value in changes.items():
        setattr(position, key, value)
    add_activity(
        db, position, "position.status_changed" if next_status and next_status != previous_status else "position.updated",
        membership_id=membership.id, details={"changed_fields": sorted(changes), "from_status": previous_status, "to_status": position.status},
    )
    db.commit()
    db.refresh(position)
    return position_response(db, organization, position)


@router.delete("/positions/{position_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_position(position_id: str, db: DB, context=Depends(_context)) -> Response:
    _, organization, membership = _require_permission(context, "positions.delete")
    position = _position(db, organization.id, position_id)
    position.status = "archived"
    position.closed_at = datetime.now(UTC)
    add_activity(db, position, "position.archived", membership_id=membership.id)
    db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.post("/positions/{position_id}/copy", response_model=CompanyPositionResponse, status_code=status.HTTP_201_CREATED)
def copy_position(position_id: str, db: DB, context=Depends(_context)) -> CompanyPositionResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    source = _position(db, organization.id, position_id)
    excluded = {"id", "public_id", "status", "opened_at", "closed_at", "created_at", "updated_at", "created_by_membership_id"}
    values = {
        column.name: getattr(source, column.name)
        for column in RecruitingPosition.__table__.columns
        if column.name not in excluded
    }
    copy = RecruitingPosition(
        id=str(uuid4()), public_id=new_public_id(), status="draft", opened_at=None, closed_at=None,
        created_by_membership_id=membership.id, **values,
    )
    db.add(copy)
    db.flush()
    add_activity(db, copy, "position.created_from_copy", membership_id=membership.id, details={"source_position_id": source.id})
    db.commit()
    db.refresh(copy)
    return position_response(db, organization, copy)


@router.get("/ats-config", response_model=CompanyAtsConfigResponse)
def get_ats_config(db: DB, context=Depends(_context)) -> CompanyAtsConfigResponse:
    _, organization, _ = _require_permission(context, "ats_config.view")
    return ats_config_response(db.get(OrganizationAtsConfiguration, organization.id), organization.id)


@router.patch("/ats-config", response_model=CompanyAtsConfigResponse)
def update_ats_config(
    payload: CompanyAtsConfigUpdate,
    db: DB,
    context=Depends(_context),
) -> CompanyAtsConfigResponse:
    _, organization, membership = _require_permission(context, "ats_config.write")
    row = db.get(OrganizationAtsConfiguration, organization.id)
    if row is None:
        row = OrganizationAtsConfiguration(organization_id=organization.id)
        db.add(row)
    for key, value in payload.model_dump(exclude_unset=True).items():
        setattr(row, key, value)
    row.updated_by_membership_id = membership.id
    db.commit()
    db.refresh(row)
    return ats_config_response(row, organization.id)


@router.get("/positions/{position_id}", response_model=CompanyPositionDetailResponse)
def position_detail(position_id: str, db: DB, context=Depends(_context)) -> CompanyPositionDetailResponse:
    _, organization, membership = _require_permission(context, "positions.view")
    permissions = set(effective_company_permissions(membership))
    position = _position(db, organization.id, position_id)
    counts = position_counts(db, position)
    criteria_rows = db.scalars(select(RecruitingPositionCriteriaVersion).where(
        RecruitingPositionCriteriaVersion.organization_id == organization.id,
        RecruitingPositionCriteriaVersion.position_id == position.id,
    ).order_by(RecruitingPositionCriteriaVersion.version_number.desc())).all()
    active = next((row for row in criteria_rows if row.status == "approved"), None)
    analyses = db.scalars(select(RecruitingPositionAiAnalysis).where(
        RecruitingPositionAiAnalysis.organization_id == organization.id,
        RecruitingPositionAiAnalysis.position_id == position.id,
    ).order_by(RecruitingPositionAiAnalysis.created_at.desc()).limit(20)).all()
    links = db.scalars(select(RecruitingShareLink).where(
        RecruitingShareLink.organization_id == organization.id,
        RecruitingShareLink.position_id == position.id,
    ).order_by(RecruitingShareLink.created_at.desc())).all()
    application_rows = db.scalars(select(RecruitingApplication).where(
        RecruitingApplication.organization_id == organization.id,
        RecruitingApplication.position_id == position.id,
    ).order_by(RecruitingApplication.applied_at.desc()).limit(200)).all() if "applications.view" in permissions else []
    application_ids = [row.id for row in application_rows]
    assessment_rows = db.scalars(select(RecruitingAssessment).where(
        RecruitingAssessment.organization_id == organization.id,
        RecruitingAssessment.application_id.in_(application_ids),
    ).order_by(RecruitingAssessment.assigned_at.desc())).all() if application_ids and "assessments.view" in permissions else []
    activity_statement = select(RecruitingPositionActivity).where(
        RecruitingPositionActivity.organization_id == organization.id,
        RecruitingPositionActivity.position_id == position.id,
    )
    if "applications.view" not in permissions:
        activity_statement = activity_statement.where(RecruitingPositionActivity.entity_type != "application")
    activities = db.scalars(activity_statement.order_by(RecruitingPositionActivity.occurred_at.desc()).limit(100)).all()
    actor_membership_ids = {row.actor_membership_id for row in activities if row.actor_membership_id}
    actor_user_ids = {row.actor_user_id for row in activities if row.actor_user_id}
    actor_membership_names = dict(db.execute(
        select(OrganizationMembership.id, User.full_name)
        .join(User, User.id == OrganizationMembership.user_id)
        .where(OrganizationMembership.organization_id == organization.id, OrganizationMembership.id.in_(actor_membership_ids))
    ).all()) if actor_membership_ids else {}
    actor_user_names = dict(db.execute(select(User.id, User.full_name).where(User.id.in_(actor_user_ids))).all()) if actor_user_ids else {}
    member_rows = db.execute(
        select(OrganizationMembership, User)
        .join(User, User.id == OrganizationMembership.user_id)
        .where(OrganizationMembership.organization_id == organization.id, OrganizationMembership.status == "active")
        .order_by(User.full_name)
    ).all() if "members.view" in permissions else []
    applications_payload = [{
        "id": row.id, "candidate_user_id": row.candidate_user_id,
        "candidate_name": row.candidate_name, "candidate_email": row.candidate_email,
        "stage": row.current_stage, "completion_status": row.analysis_status,
        "missing_documents": [] if row.cv_document_id else ["cv"],
        "last_action_at": row.updated_at, "applied_at": row.applied_at,
        "analysis_result": row.analysis_result or {},
    } for row in application_rows]
    assessments_payload = [{
        "id": row.id, "application_id": row.application_id, "title": row.title,
        "status": row.status, "required": row.required, "assigned_at": row.assigned_at,
        "started_at": row.started_at, "completed_at": row.completed_at, "expires_at": row.expires_at,
    } for row in assessment_rows]
    comparison = [{
        "application_id": row.id, "candidate_name": row.candidate_name,
        "criteria_version_id": row.criteria_version_id,
        "stage": row.current_stage, "score": (row.analysis_result or {}).get("overall_score"),
        "evidence": (row.analysis_result or {}).get("cv_evidence", []),
        "uncertainties": (row.analysis_result or {}).get("uncertainties", []),
    } for row in application_rows if active is not None and row.criteria_version_id == active.id]
    return CompanyPositionDetailResponse.model_validate({
        "position": _response_payload(position_response(db, organization, position)),
        "counts": _response_payload(counts),
        "ats_config": _response_payload(effective_ats_config(db, position)) if "ats_config.view" in permissions else None,
        "criteria_versions": [_response_payload(criteria_response(row)) for row in criteria_rows],
        "active_criteria_version": _response_payload(criteria_response(active)) if active else None,
        "ai_analyses": [_response_payload(analysis_response(row)) for row in analyses],
        "share_links": [_response_payload(share_link_response(db, row)) for row in links],
        "applications": applications_payload,
        "assessments": assessments_payload,
        "comparison": comparison,
        "activities": [
            _response_payload(activity_response(
                row,
                actor_membership_names.get(row.actor_membership_id) or actor_user_names.get(row.actor_user_id),
            ))
            for row in activities
        ],
        "members": [
            _response_payload(CompanyPositionMemberResponse(
                membership_id=membership.id,
                full_name=user.full_name,
                role=membership.role,
            ))
            for membership, user in member_rows
        ],
    })


@router.post("/positions/{position_id}/ai-analysis", response_model=CompanyPositionAiAnalysisResponse, status_code=status.HTTP_202_ACCEPTED)
def queue_position_ai_analysis(position_id: str, db: DB, context=Depends(_context)) -> CompanyPositionAiAnalysisResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    position = _position(db, organization.id, position_id)
    analysis = request_position_analysis(db, position, membership)
    enqueue_company_task(
        db,
        organization_id=organization.id,
        task_name=POSITION_ANALYSIS_TASK,
        aggregate_type="position_ai_analysis",
        aggregate_id=analysis.id,
    )
    db.commit()
    db.refresh(analysis)
    return analysis_response(analysis)


@router.get("/positions/{position_id}/ai-analyses/{analysis_id}", response_model=CompanyPositionAiAnalysisResponse)
def get_position_ai_analysis(position_id: str, analysis_id: str, db: DB, context=Depends(_context)) -> CompanyPositionAiAnalysisResponse:
    _, organization, _ = _require_permission(context, "positions.view")
    analysis = db.scalar(select(RecruitingPositionAiAnalysis).where(
        RecruitingPositionAiAnalysis.id == analysis_id,
        RecruitingPositionAiAnalysis.position_id == position_id,
        RecruitingPositionAiAnalysis.organization_id == organization.id,
    ))
    if analysis is None:
        raise HTTPException(status_code=404, detail="Position AI analysis not found")
    return analysis_response(analysis)


@router.patch("/positions/{position_id}/criteria/{version_id}", response_model=CompanyCriteriaVersionResponse)
def update_position_criteria(
    position_id: str,
    version_id: str,
    payload: CompanyCriteriaVersionUpdate,
    db: DB,
    context=Depends(_context),
) -> CompanyCriteriaVersionResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    position = _position(db, organization.id, position_id)
    version = db.scalar(select(RecruitingPositionCriteriaVersion).where(
        RecruitingPositionCriteriaVersion.id == version_id,
        RecruitingPositionCriteriaVersion.position_id == position.id,
        RecruitingPositionCriteriaVersion.organization_id == organization.id,
    ))
    if version is None:
        raise HTTPException(status_code=404, detail="Position criteria version not found")
    if version.status != "draft":
        raise HTTPException(status_code=409, detail="Only draft criteria can be changed")
    version.criteria = payload.criteria
    add_activity(db, position, "position.criteria_updated", membership_id=membership.id,
                 entity_type="criteria_version", entity_id=version.id, details={"version": version.version_number})
    db.commit()
    db.refresh(version)
    return criteria_response(version)


@router.post("/positions/{position_id}/criteria/{version_id}/approve", response_model=CompanyCriteriaVersionResponse)
def approve_position_criteria(position_id: str, version_id: str, db: DB, context=Depends(_context)) -> CompanyCriteriaVersionResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    position = db.scalar(select(RecruitingPosition).where(
        RecruitingPosition.id == position_id,
        RecruitingPosition.organization_id == organization.id,
    ).with_for_update())
    if position is None:
        raise HTTPException(status_code=404, detail="Position not found")
    version = db.scalar(select(RecruitingPositionCriteriaVersion).where(
        RecruitingPositionCriteriaVersion.id == version_id,
        RecruitingPositionCriteriaVersion.position_id == position.id,
        RecruitingPositionCriteriaVersion.organization_id == organization.id,
    ))
    if version is None:
        raise HTTPException(status_code=404, detail="Position criteria version not found")
    if version.status != "draft":
        raise HTTPException(status_code=409, detail="Only draft criteria can be approved")
    if not version.criteria:
        raise HTTPException(status_code=422, detail="Criteria cannot be empty")
    db.execute(
        update(RecruitingPositionCriteriaVersion)
        .where(
            RecruitingPositionCriteriaVersion.organization_id == organization.id,
            RecruitingPositionCriteriaVersion.position_id == position.id,
            RecruitingPositionCriteriaVersion.status == "approved",
        )
        .values(status="superseded")
    )
    version.status = "approved"
    version.approved_by_membership_id = membership.id
    version.approved_at = datetime.now(UTC)
    add_activity(db, position, "position.criteria_approved", membership_id=membership.id,
                 entity_type="criteria_version", entity_id=version.id, details={"version": version.version_number})
    try:
        db.commit()
    except IntegrityError as exception:
        db.rollback()
        raise HTTPException(status_code=409, detail="Another criteria version was approved concurrently") from exception
    db.refresh(version)
    return criteria_response(version)


@router.post("/positions/{position_id}/share-links", response_model=CompanyShareLinkResponse, status_code=status.HTTP_201_CREATED)
def create_share_link(
    position_id: str,
    payload: CompanyShareLinkCreate,
    db: DB,
    context=Depends(_context),
) -> CompanyShareLinkResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    position = _position(db, organization.id, position_id)
    code = new_short_code()
    while db.scalar(select(RecruitingShareLink.id).where(RecruitingShareLink.short_code == code)):
        code = new_short_code()
    row = RecruitingShareLink(
        id=str(uuid4()), organization_id=organization.id, position_id=position.id,
        short_code=code, created_by_membership_id=membership.id, **payload.model_dump(),
    )
    db.add(row)
    db.flush()
    add_activity(db, position, "position.share_link_created", membership_id=membership.id,
                 entity_type="share_link", entity_id=row.id, details={"channel": row.channel, "label": row.label})
    db.commit()
    db.refresh(row)
    return share_link_response(db, row)


@router.patch("/positions/{position_id}/share-links/{link_id}", response_model=CompanyShareLinkResponse)
def update_share_link(
    position_id: str,
    link_id: str,
    payload: CompanyShareLinkUpdate,
    db: DB,
    context=Depends(_context),
) -> CompanyShareLinkResponse:
    _, organization, membership = _require_permission(context, "positions.write")
    position = _position(db, organization.id, position_id)
    row = db.scalar(select(RecruitingShareLink).where(
        RecruitingShareLink.id == link_id, RecruitingShareLink.position_id == position.id,
        RecruitingShareLink.organization_id == organization.id,
    ))
    if row is None:
        raise HTTPException(status_code=404, detail="Position share link not found")
    changes = payload.model_dump(exclude_unset=True)
    for key, value in changes.items():
        setattr(row, key, value)
    add_activity(db, position, "position.share_link_updated", membership_id=membership.id,
                 entity_type="share_link", entity_id=row.id, details={"changed_fields": sorted(changes)})
    db.commit()
    db.refresh(row)
    return share_link_response(db, row)


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


@router.patch("/positions/{position_id}/applications/{application_id}", response_model=CompanyApplicationActionResponse)
def update_position_application(
    position_id: str,
    application_id: str,
    payload: CompanyApplicationAction,
    db: DB,
    context=Depends(_context),
) -> CompanyApplicationActionResponse:
    _, organization, membership = _require_permission(context, "applications.write")
    position = _position(db, organization.id, position_id)
    application = db.scalar(select(RecruitingApplication).where(
        RecruitingApplication.id == application_id,
        RecruitingApplication.position_id == position.id,
        RecruitingApplication.organization_id == organization.id,
    ).with_for_update())
    if application is None:
        raise HTTPException(status_code=404, detail="Application not found")
    prior_actions = db.scalars(select(RecruitingPositionActivity).where(
        RecruitingPositionActivity.organization_id == organization.id,
        RecruitingPositionActivity.position_id == position.id,
        RecruitingPositionActivity.entity_type == "application",
        RecruitingPositionActivity.entity_id == application.id,
    )).all()
    if any((row.details or {}).get("idempotency_key") == payload.idempotency_key for row in prior_actions):
        return CompanyApplicationActionResponse(
            id=application.id, current_stage=application.current_stage,
            first_reviewed_at=application.first_reviewed_at, updated_at=application.updated_at,
        )
    now = datetime.now(UTC)
    if payload.stage is not None and payload.stage != application.current_stage:
        previous_stage = application.current_stage
        db.add(RecruitingApplicationStageEvent(
            id=str(uuid4()), organization_id=organization.id, position_id=position.id,
            application_id=application.id, from_stage=previous_stage, to_stage=payload.stage,
            reason_code=payload.decision, actor_membership_id=membership.id,
            idempotency_key=payload.idempotency_key, occurred_at=now,
        ))
        application.current_stage = payload.stage
        add_activity(db, position, "application.stage_changed", membership_id=membership.id,
                     entity_type="application", entity_id=application.id,
                     details={"from_stage": previous_stage, "to_stage": payload.stage, "idempotency_key": payload.idempotency_key})
    if (payload.note or "").strip():
        add_activity(db, position, "application.note_added", membership_id=membership.id,
                     entity_type="application", entity_id=application.id,
                     details={"note": payload.note.strip(), "idempotency_key": payload.idempotency_key})
    if (payload.decision or "").strip():
        add_activity(db, position, "application.human_decision", membership_id=membership.id,
                     entity_type="application", entity_id=application.id,
                     details={"decision": payload.decision.strip(), "idempotency_key": payload.idempotency_key})
    if application.first_reviewed_at is None:
        application.first_reviewed_at = now
    try:
        db.commit()
    except IntegrityError as exception:
        db.rollback()
        raise HTTPException(status_code=409, detail="Application action was already processed") from exception
    db.refresh(application)
    return CompanyApplicationActionResponse(
        id=application.id, current_stage=application.current_stage,
        first_reviewed_at=application.first_reviewed_at, updated_at=application.updated_at,
    )


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


@router.get("/applications/{application_id}/snapshot")
def get_application_snapshot(
    application_id: str,
    db: DB,
    context=Depends(_context),
):
    _, organization, _ = _require_permission(context, "applications.view")
    application = db.scalar(
        select(RecruitingApplication).where(
            RecruitingApplication.id == application_id,
            RecruitingApplication.organization_id == organization.id,
        )
    )
    if application is None:
        raise HTTPException(status_code=404, detail="Application not found")
    snapshot = db.scalar(
        select(RecruitingApplicationSnapshot).where(
            RecruitingApplicationSnapshot.application_id == application_id
        )
    )
    if snapshot is None:
        raise HTTPException(status_code=404, detail="Application snapshot not found")
    return snapshot.payload


@router.get("/positions/{position_id}/questions", response_model=list[CompanyPositionQuestionResponse])
def get_position_questions(
    position_id: str,
    db: DB,
    context=Depends(_context),
):
    _, organization, _ = _require_permission(context, "positions.view")
    questions = db.scalars(
        select(RecruitingPositionQuestion)
        .where(
            RecruitingPositionQuestion.organization_id == organization.id,
            RecruitingPositionQuestion.position_id == position_id,
        )
        .order_by(RecruitingPositionQuestion.sort_order.asc(), RecruitingPositionQuestion.created_at.asc())
    ).all()
    return [CompanyPositionQuestionResponse.model_validate(q, from_attributes=True) for q in questions]


@router.post("/positions/{position_id}/questions", response_model=CompanyPositionQuestionResponse, status_code=201)
def create_position_question(
    position_id: str,
    payload: CompanyPositionQuestionCreate,
    db: DB,
    context=Depends(_context),
):
    _, organization, _ = _require_permission(context, "positions.write")
    position = db.scalar(
        select(RecruitingPosition).where(
            RecruitingPosition.id == position_id,
            RecruitingPosition.organization_id == organization.id,
        )
    )
    if position is None:
        raise HTTPException(status_code=404, detail="Position not found")
    
    question = RecruitingPositionQuestion(
        id=str(uuid4()),
        organization_id=organization.id,
        position_id=position.id,
        question_text=payload.question_text.strip(),
        question_type=payload.question_type,
        options=payload.options if payload.question_type == "single_choice" else [],
        is_required=payload.is_required,
        sort_order=payload.sort_order,
    )
    db.add(question)
    db.commit()
    db.refresh(question)
    return CompanyPositionQuestionResponse.model_validate(question, from_attributes=True)


@router.put("/positions/{position_id}/questions/{question_id}", response_model=CompanyPositionQuestionResponse)
def update_position_question(
    position_id: str,
    question_id: str,
    payload: CompanyPositionQuestionUpdate,
    db: DB,
    context=Depends(_context),
):
    _, organization, _ = _require_permission(context, "positions.write")
    question = db.scalar(
        select(RecruitingPositionQuestion).where(
            RecruitingPositionQuestion.id == question_id,
            RecruitingPositionQuestion.position_id == position_id,
            RecruitingPositionQuestion.organization_id == organization.id,
        )
    )
    if question is None:
        raise HTTPException(status_code=404, detail="Position question not found")

    if payload.question_text is not None:
        question.question_text = payload.question_text.strip()
    if payload.question_type is not None:
        question.question_type = payload.question_type
    if payload.options is not None:
        question.options = payload.options if question.question_type == "single_choice" else []
    if payload.is_required is not None:
        question.is_required = payload.is_required
    if payload.sort_order is not None:
        question.sort_order = payload.sort_order

    db.commit()
    db.refresh(question)
    return CompanyPositionQuestionResponse.model_validate(question, from_attributes=True)


@router.delete("/positions/{position_id}/questions/{question_id}", status_code=204)
def delete_position_question(
    position_id: str,
    question_id: str,
    db: DB,
    context=Depends(_context),
):
    _, organization, _ = _require_permission(context, "positions.write")
    question = db.scalar(
        select(RecruitingPositionQuestion).where(
            RecruitingPositionQuestion.id == question_id,
            RecruitingPositionQuestion.position_id == position_id,
            RecruitingPositionQuestion.organization_id == organization.id,
        )
    )
    if question is None:
        raise HTTPException(status_code=404, detail="Position question not found")

    db.delete(question)
    db.commit()
    return Response(status_code=204)

