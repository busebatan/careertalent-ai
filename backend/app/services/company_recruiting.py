"""Kurum işe alım dashboard sorguları."""

from datetime import UTC, datetime, timedelta
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from sqlalchemy import and_, func, or_, select
from sqlalchemy.orm import Session

from app.models.company_recruiting import (
    AssessmentUsageLedger,
    RecruitingApplication,
    RecruitingApplicationStageEvent,
    RecruitingAssessment,
    RecruitingPosition,
    RecruitingScorecard,
)
from app.models.recruiting import Organization, OrganizationInvitation, OrganizationMembership
from app.schemas.company import (
    CompanyApplicationResponse,
    CompanyApplicationsResponse,
    CompanyAssessmentResponse,
    CompanyAssessmentsResponse,
    CompanyAssessmentUsage,
    CompanyDashboardIndicators,
    CompanyDashboardPeriod,
    CompanyDashboardResponse,
    CompanyDashboardSummary,
    CompanyDashboardTask,
    CompanyDashboardTaskPosition,
    CompanyLargestLossStage,
    CompanyMembershipSummary,
)


ACTIVE_APPLICATION_STAGES = {
    "new", "assessment_pending", "assessment_in_progress", "technical_review",
    "shortlisted", "interview", "offer",
}


def _aware(value: datetime) -> datetime:
    return value if value.tzinfo is not None else value.replace(tzinfo=UTC)


def _timezone(organization: Organization) -> ZoneInfo:
    settings = organization.settings if isinstance(organization.settings, dict) else {}
    recruiting = settings.get("recruiting") if isinstance(settings.get("recruiting"), dict) else {}
    name = recruiting.get("timezone", "Europe/Istanbul")
    try:
        return ZoneInfo(name)
    except (ZoneInfoNotFoundError, TypeError):
        return ZoneInfo("Europe/Istanbul")


def _month_bounds(organization: Organization, now: datetime) -> tuple[datetime, datetime]:
    local = now.astimezone(_timezone(organization))
    start = local.replace(day=1, hour=0, minute=0, second=0, microsecond=0)
    next_month = start.replace(year=start.year + 1, month=1) if start.month == 12 else start.replace(month=start.month + 1)
    return start.astimezone(UTC), next_month.astimezone(UTC)


def assessment_usage(db: Session, organization: Organization, now: datetime) -> CompanyAssessmentUsage:
    start, end = _month_bounds(organization, now)
    used = db.scalar(
        select(func.coalesce(func.sum(AssessmentUsageLedger.units), 0)).where(
            AssessmentUsageLedger.organization_id == organization.id,
            AssessmentUsageLedger.occurred_at >= start,
            AssessmentUsageLedger.occurred_at < end,
        )
    ) or 0
    settings = organization.settings if isinstance(organization.settings, dict) else {}
    recruiting = settings.get("recruiting") if isinstance(settings.get("recruiting"), dict) else {}
    quota = recruiting.get("assessment_quota")
    return CompanyAssessmentUsage(used=max(0, int(used)), quota=quota if isinstance(quota, int) else None)


def _summary(db: Session, organization_id: str, start: datetime, now: datetime) -> CompanyDashboardSummary:
    cohort_ids = list(db.scalars(select(RecruitingApplication.id).where(
        RecruitingApplication.organization_id == organization_id,
        RecruitingApplication.applied_at >= start,
        RecruitingApplication.applied_at <= now,
    )))
    required_ids: set[str] = set()
    completed_ids: set[str] = set()
    if cohort_ids:
        required_ids = set(db.scalars(select(RecruitingAssessment.application_id).where(
            RecruitingAssessment.organization_id == organization_id,
            RecruitingAssessment.application_id.in_(cohort_ids),
            RecruitingAssessment.required.is_(True),
        )))
        if required_ids:
            completed_ids = set(db.scalars(select(RecruitingAssessment.application_id).where(
                RecruitingAssessment.organization_id == organization_id,
                RecruitingAssessment.application_id.in_(required_ids),
                RecruitingAssessment.required.is_(True),
                RecruitingAssessment.status == "completed",
                RecruitingAssessment.completed_at <= now,
            )))
    application_rate = round(len(completed_ids) / len(required_ids), 4) if required_ids else None

    completed_in_period = set(db.scalars(select(RecruitingAssessment.application_id).where(
        RecruitingAssessment.organization_id == organization_id,
        RecruitingAssessment.status == "completed",
        RecruitingAssessment.completed_at >= start,
        RecruitingAssessment.completed_at <= now,
    )))
    interviewed: set[str] = set()
    if completed_in_period:
        interviewed = set(db.scalars(select(RecruitingApplicationStageEvent.application_id).where(
            RecruitingApplicationStageEvent.organization_id == organization_id,
            RecruitingApplicationStageEvent.application_id.in_(completed_in_period),
            RecruitingApplicationStageEvent.to_stage.in_(["interview", "offer", "hired"]),
            RecruitingApplicationStageEvent.occurred_at <= now,
        )))
    interview_rate = round(len(interviewed) / len(completed_in_period), 4) if completed_in_period else None

    shortlist_rows = db.execute(
        select(RecruitingApplicationStageEvent.occurred_at, RecruitingApplication.applied_at)
        .join(RecruitingApplication, RecruitingApplication.id == RecruitingApplicationStageEvent.application_id)
        .where(
            RecruitingApplicationStageEvent.organization_id == organization_id,
            RecruitingApplicationStageEvent.to_stage == "shortlisted",
            RecruitingApplicationStageEvent.occurred_at >= start,
            RecruitingApplicationStageEvent.occurred_at <= now,
        )
    ).all()
    durations = [(_aware(event_at) - _aware(applied_at)).total_seconds() / 3600 for event_at, applied_at in shortlist_rows]
    average_shortlist = round(sum(durations) / len(durations), 1) if durations else None

    loss = db.execute(
        select(RecruitingApplicationStageEvent.from_stage, func.count())
        .where(
            RecruitingApplicationStageEvent.organization_id == organization_id,
            RecruitingApplicationStageEvent.to_stage.in_(["rejected", "withdrawn"]),
            RecruitingApplicationStageEvent.occurred_at >= start,
            RecruitingApplicationStageEvent.occurred_at <= now,
        )
        .group_by(RecruitingApplicationStageEvent.from_stage)
        .order_by(func.count().desc(), RecruitingApplicationStageEvent.from_stage)
    ).first()
    largest_loss = CompanyLargestLossStage(stage=loss[0] or "new", count=loss[1]) if loss else None

    return CompanyDashboardSummary(
        application_to_assessment_rate=application_rate,
        assessment_to_interview_rate=interview_rate,
        average_shortlist_hours=average_shortlist,
        largest_loss_stage=largest_loss,
    )


def dashboard(
    db: Session,
    organization: Organization,
    membership_summary: CompanyMembershipSummary,
    period: str,
) -> CompanyDashboardResponse:
    now = datetime.now(UTC)
    days = {"7d": 7, "30d": 30, "90d": 90}[period]
    start = now - timedelta(days=days)
    active_positions = db.scalar(select(func.count()).select_from(RecruitingPosition).where(
        RecruitingPosition.organization_id == organization.id,
        RecruitingPosition.status == "open",
        or_(RecruitingPosition.application_deadline.is_(None), RecruitingPosition.application_deadline >= now),
    )) or 0
    new_applications = db.scalar(select(func.count()).select_from(RecruitingApplication).where(
        RecruitingApplication.organization_id == organization.id,
        RecruitingApplication.first_reviewed_at.is_(None),
        RecruitingApplication.current_stage.in_(ACTIVE_APPLICATION_STAGES),
    )) or 0
    assessment_pending = db.scalar(select(func.count(func.distinct(RecruitingAssessment.application_id))).where(
        RecruitingAssessment.organization_id == organization.id,
        RecruitingAssessment.required.is_(True),
        RecruitingAssessment.status.in_(["assigned", "in_progress"]),
    )) or 0
    technical_review = db.scalar(select(func.count()).select_from(RecruitingApplication).where(
        RecruitingApplication.organization_id == organization.id,
        RecruitingApplication.current_stage == "technical_review",
    )) or 0
    shortlisted = db.scalar(select(func.count()).select_from(RecruitingApplication).where(
        RecruitingApplication.organization_id == organization.id,
        RecruitingApplication.current_stage == "shortlisted",
    )) or 0
    usage = assessment_usage(db, organization, now)

    tasks: list[CompanyDashboardTask] = []
    new_rows = db.execute(
        select(RecruitingPosition.id, RecruitingPosition.title, func.count(RecruitingApplication.id))
        .join(RecruitingApplication, RecruitingApplication.position_id == RecruitingPosition.id)
        .where(
            RecruitingPosition.organization_id == organization.id,
            RecruitingApplication.first_reviewed_at.is_(None),
            RecruitingApplication.current_stage.in_(ACTIVE_APPLICATION_STAGES),
        )
        .group_by(RecruitingPosition.id, RecruitingPosition.title)
        .order_by(func.count(RecruitingApplication.id).desc())
    ).all()
    for position_id, title, count in new_rows:
        tasks.append(CompanyDashboardTask(
            type="new_applications", priority=60, count=count,
            position=CompanyDashboardTaskPosition(id=position_id, title=title),
            target=f"/{organization.slug}/pozisyonlar/{position_id}/adaylar?queue=new",
        ))
    if technical_review:
        tasks.append(CompanyDashboardTask(
            type="technical_review", priority=70, count=technical_review,
            target=f"/{organization.slug}/adaylar?queue=technical_review",
        ))
    missing_scorecards = db.scalar(
        select(func.count(func.distinct(RecruitingScorecard.application_id))).where(
            RecruitingScorecard.organization_id == organization.id,
            RecruitingScorecard.scorecard_type == "technical",
            RecruitingScorecard.status.in_(["pending", "in_progress"]),
        )
    ) or 0
    if missing_scorecards:
        tasks.append(CompanyDashboardTask(
            type="scorecard_missing", priority=80, count=missing_scorecards,
            target=f"/{organization.slug}/adaylar?queue=scorecard_missing",
        ))
    deadline_rows = db.execute(select(RecruitingPosition).where(
        RecruitingPosition.organization_id == organization.id,
        RecruitingPosition.status == "open",
        RecruitingPosition.application_deadline >= now,
        RecruitingPosition.application_deadline <= now + timedelta(days=2),
    ).order_by(RecruitingPosition.application_deadline)).scalars().all()
    for position in deadline_rows:
        tasks.append(CompanyDashboardTask(
            type="position_deadline", priority=90, count=1,
            position=CompanyDashboardTaskPosition(id=position.id, title=position.title),
            target=f"/{organization.slug}/pozisyonlar?focus={position.id}",
        ))
    retention_settings = organization.settings if isinstance(organization.settings, dict) else {}
    recruiting_settings = retention_settings.get("recruiting") if isinstance(retention_settings.get("recruiting"), dict) else {}
    warning_days = recruiting_settings.get("retention_warning_days", [30, 7, 1])
    warning_max = max((day for day in warning_days if isinstance(day, int)), default=30)
    retention_due = db.scalar(select(func.count()).select_from(RecruitingApplication).where(
        RecruitingApplication.organization_id == organization.id,
        RecruitingApplication.current_stage.in_(ACTIVE_APPLICATION_STAGES),
        RecruitingApplication.retention_expires_at >= now,
        RecruitingApplication.retention_expires_at <= now + timedelta(days=warning_max),
    )) or 0
    if retention_due:
        tasks.append(CompanyDashboardTask(
            type="retention_due", priority=100, count=retention_due,
            target=f"/{organization.slug}/adaylar?queue=retention_due",
        ))

    members_total = db.scalar(select(func.count()).select_from(OrganizationMembership).where(
        OrganizationMembership.organization_id == organization.id
    )) or 0
    members_active = db.scalar(select(func.count()).select_from(OrganizationMembership).where(
        OrganizationMembership.organization_id == organization.id,
        OrganizationMembership.status == "active",
    )) or 0
    invitations_pending = db.scalar(select(func.count()).select_from(OrganizationInvitation).where(
        OrganizationInvitation.organization_id == organization.id,
        OrganizationInvitation.accepted_at.is_(None),
        OrganizationInvitation.expires_at > now,
    )) or 0

    return CompanyDashboardResponse(
        organization=membership_summary,
        as_of=now,
        period=CompanyDashboardPeriod(key=period, from_=start, to=now),
        indicators=CompanyDashboardIndicators(
            active_positions=active_positions,
            new_applications=new_applications,
            assessment_pending=assessment_pending,
            technical_review_pending=technical_review,
            shortlisted=shortlisted,
            assessment_usage=usage,
        ),
        tasks=sorted(tasks, key=lambda item: (-item.priority, item.type))[:5],
        summary=_summary(db, organization.id, start, now),
        members_total=members_total,
        members_active=members_active,
        invitations_pending=invitations_pending,
    )


def applications(
    db: Session,
    organization: Organization,
    queue: str | None,
    stage: str | None,
    position_id: str | None,
) -> CompanyApplicationsResponse:
    statement = (
        select(RecruitingApplication, RecruitingPosition.title)
        .join(RecruitingPosition, RecruitingPosition.id == RecruitingApplication.position_id)
        .where(RecruitingApplication.organization_id == organization.id)
    )
    if position_id:
        statement = statement.where(RecruitingApplication.position_id == position_id)
    if stage:
        statement = statement.where(RecruitingApplication.current_stage == stage)
    if queue == "new":
        statement = statement.where(
            RecruitingApplication.first_reviewed_at.is_(None),
            RecruitingApplication.current_stage.in_(ACTIVE_APPLICATION_STAGES),
        )
    elif queue == "technical_review":
        statement = statement.where(RecruitingApplication.current_stage == "technical_review")
    elif queue == "assessment_pending":
        statement = statement.join(RecruitingAssessment, RecruitingAssessment.application_id == RecruitingApplication.id).where(
            RecruitingAssessment.required.is_(True),
            RecruitingAssessment.status.in_(["assigned", "in_progress"]),
        )
    elif queue == "scorecard_missing":
        statement = statement.join(RecruitingScorecard, RecruitingScorecard.application_id == RecruitingApplication.id).where(
            RecruitingScorecard.scorecard_type == "technical",
            RecruitingScorecard.status.in_(["pending", "in_progress"]),
        )
    elif queue == "retention_due":
        now = datetime.now(UTC)
        statement = statement.where(
            RecruitingApplication.retention_expires_at >= now,
            RecruitingApplication.retention_expires_at <= now + timedelta(days=30),
        )
    rows = db.execute(statement.distinct().order_by(RecruitingApplication.applied_at.desc())).all()
    return CompanyApplicationsResponse(items=[CompanyApplicationResponse(
        id=application.id,
        position_id=application.position_id,
        position_title=position_title,
        candidate_name=application.candidate_name,
        candidate_email=application.candidate_email,
        current_stage=application.current_stage,
        first_reviewed_at=application.first_reviewed_at,
        applied_at=application.applied_at,
        retention_expires_at=application.retention_expires_at,
    ) for application, position_title in rows])


def assessments(db: Session, organization: Organization) -> CompanyAssessmentsResponse:
    rows = db.execute(
        select(RecruitingAssessment, RecruitingApplication.candidate_name, RecruitingPosition.title)
        .join(RecruitingApplication, RecruitingApplication.id == RecruitingAssessment.application_id)
        .join(RecruitingPosition, RecruitingPosition.id == RecruitingApplication.position_id)
        .where(RecruitingAssessment.organization_id == organization.id)
        .order_by(RecruitingAssessment.assigned_at.desc())
    ).all()
    return CompanyAssessmentsResponse(
        usage=assessment_usage(db, organization, datetime.now(UTC)),
        items=[CompanyAssessmentResponse(
            id=assessment.id,
            application_id=assessment.application_id,
            position_title=position_title,
            candidate_name=candidate_name,
            title=assessment.title,
            status=assessment.status,
            assigned_at=assessment.assigned_at,
            completed_at=assessment.completed_at,
        ) for assessment, candidate_name, position_title in rows],
    )
