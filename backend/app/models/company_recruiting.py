"""Kurum işe alım pozisyonları, aday kuyruğu ve ölçüm olayları."""

from datetime import date, datetime
from decimal import Decimal
import secrets

from sqlalchemy import JSON, Boolean, CheckConstraint, Date, DateTime, ForeignKey, ForeignKeyConstraint, Index, Integer, Numeric, String, Text, UniqueConstraint, func, text
from sqlalchemy.orm import Mapped, mapped_column

from app.core.database import Base

_PUBLIC_ALPHABET = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ"


def new_public_id() -> str:
    return "".join(secrets.choice(_PUBLIC_ALPHABET) for _ in range(10))


def new_short_code() -> str:
    return "".join(secrets.choice(_PUBLIC_ALPHABET) for _ in range(8))


class RecruitingPosition(Base):
    __tablename__ = "recruiting_positions"
    __table_args__ = (
        CheckConstraint(
            "status IN ('draft', 'published', 'paused', 'closed', 'archived')",
            name="ck_recruiting_positions_status",
        ),
        CheckConstraint(
            "employment_type IS NULL OR employment_type IN ('full_time', 'part_time', 'contract', 'internship')",
            name="ck_recruiting_positions_employment_type",
        ),
        CheckConstraint(
            "workplace_type IS NULL OR workplace_type IN ('onsite', 'hybrid', 'remote')",
            name="ck_recruiting_positions_workplace_type",
        ),
        UniqueConstraint("id", "organization_id", name="uq_recruiting_positions_id_organization"),
        ForeignKeyConstraint(["recruiter_membership_id", "organization_id"], ["organization_memberships.id", "organization_memberships.organization_id"], name="fk_recruiting_positions_recruiter_tenant"),
        ForeignKeyConstraint(["technical_manager_membership_id", "organization_id"], ["organization_memberships.id", "organization_memberships.organization_id"], name="fk_recruiting_positions_technical_manager_tenant"),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    title: Mapped[str] = mapped_column(String(160), nullable=False)
    slug: Mapped[str] = mapped_column(String(180), nullable=False, index=True)
    public_id: Mapped[str] = mapped_column(String(16), unique=True, nullable=False, index=True, default=new_public_id)
    department: Mapped[str | None] = mapped_column(String(120), nullable=True)
    level: Mapped[str | None] = mapped_column(String(80), nullable=True)
    employment_type: Mapped[str | None] = mapped_column(String(24), nullable=True)
    workplace_type: Mapped[str | None] = mapped_column(String(24), nullable=True)
    location: Mapped[str | None] = mapped_column(String(180), nullable=True)
    salary_min: Mapped[Decimal | None] = mapped_column(Numeric(14, 2), nullable=True)
    salary_max: Mapped[Decimal | None] = mapped_column(Numeric(14, 2), nullable=True)
    salary_currency: Mapped[str | None] = mapped_column(String(3), nullable=True)
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    responsibilities: Mapped[str | None] = mapped_column(Text, nullable=True)
    must_have_skills: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    preferred_skills: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    learnable_skills: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    experience_expectation: Mapped[str | None] = mapped_column(Text, nullable=True)
    language_work_authorization: Mapped[str | None] = mapped_column(Text, nullable=True)
    source_text: Mapped[str | None] = mapped_column(Text, nullable=True)
    ats_terms: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    ats_notes: Mapped[str | None] = mapped_column(Text, nullable=True)
    evaluation_config: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    application_form_id: Mapped[str | None] = mapped_column(String(80), nullable=True)
    assessment_template_id: Mapped[str | None] = mapped_column(String(80), nullable=True)
    retention_days: Mapped[int] = mapped_column(Integer, nullable=False, default=180)
    status: Mapped[str] = mapped_column(String(20), index=True, nullable=False, default="draft")
    application_deadline: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), index=True, nullable=True)
    target_start_date: Mapped[date | None] = mapped_column(Date, nullable=True)
    opened_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    closed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    recruiter_membership_id: Mapped[str | None] = mapped_column(String(36), nullable=True)
    technical_manager_membership_id: Mapped[str | None] = mapped_column(String(36), nullable=True)
    created_by_membership_id: Mapped[str | None] = mapped_column(
        ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True
    )
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )


class RecruitingPositionQuestion(Base):
    __tablename__ = "recruiting_position_questions"
    __table_args__ = (
        CheckConstraint(
            "question_type IN ('text', 'number', 'single_choice')",
            name="ck_recruiting_position_questions_type",
        ),
        ForeignKeyConstraint(
            ["position_id", "organization_id"],
            ["recruiting_positions.id", "recruiting_positions.organization_id"],
            name="fk_recruiting_position_questions_position_tenant",
            ondelete="CASCADE",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    position_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    question_text: Mapped[str] = mapped_column(Text, nullable=False)
    question_type: Mapped[str] = mapped_column(String(20), nullable=False, default="text")
    options: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    is_required: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    sort_order: Mapped[int] = mapped_column(Integer, nullable=False, default=0)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )


class RecruitingApplication(Base):
    __tablename__ = "recruiting_applications"
    __table_args__ = (
        CheckConstraint(
            "current_stage IN ('new', 'assessment_pending', 'assessment_in_progress', 'technical_review', 'shortlisted', 'interview', 'offer', 'hired', 'rejected', 'withdrawn')",
            name="ck_recruiting_applications_stage",
        ),
        UniqueConstraint(
            "organization_id", "position_id", "candidate_email",
            name="uq_recruiting_applications_position_email",
        ),
        UniqueConstraint("id", "organization_id", name="uq_recruiting_applications_id_organization"),
        UniqueConstraint("organization_id", "position_id", "candidate_user_id", name="uq_recruiting_applications_position_candidate"),
        ForeignKeyConstraint(
            ["position_id", "organization_id"],
            ["recruiting_positions.id", "recruiting_positions.organization_id"],
            name="fk_recruiting_applications_position_tenant",
            ondelete="CASCADE",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    position_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    candidate_user_id: Mapped[int | None] = mapped_column(
        Integer, ForeignKey("users.id", ondelete="SET NULL"), index=True, nullable=True
    )
    candidate_name: Mapped[str] = mapped_column(String(160), nullable=False)
    candidate_email: Mapped[str] = mapped_column(String(255), index=True, nullable=False)
    cv_document_id: Mapped[str | None] = mapped_column(ForeignKey("cv_documents.id", ondelete="SET NULL"), index=True, nullable=True)
    criteria_version_id: Mapped[str | None] = mapped_column(ForeignKey("recruiting_position_criteria_versions.id", ondelete="SET NULL"), index=True, nullable=True)
    original_share_link_id: Mapped[str | None] = mapped_column(ForeignKey("recruiting_share_links.id", ondelete="SET NULL"), index=True, nullable=True)
    last_share_link_id: Mapped[str | None] = mapped_column(ForeignKey("recruiting_share_links.id", ondelete="SET NULL"), index=True, nullable=True)
    consent_snapshot: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    application_snapshot: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    ats_context_snapshot: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    analysis_status: Mapped[str] = mapped_column(String(24), nullable=False, default="queued")
    analysis_result: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    current_stage: Mapped[str] = mapped_column(String(32), index=True, nullable=False, default="new")
    first_reviewed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    applied_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), index=True, nullable=False)
    retention_expires_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), index=True, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )


class RecruitingApplicationStageEvent(Base):
    __tablename__ = "recruiting_application_stage_events"
    __table_args__ = (
        UniqueConstraint("organization_id", "idempotency_key", name="uq_recruiting_stage_events_idempotency"),
        ForeignKeyConstraint(
            ["position_id", "organization_id"],
            ["recruiting_positions.id", "recruiting_positions.organization_id"],
            name="fk_recruiting_stage_events_position_tenant",
            ondelete="CASCADE",
        ),
        ForeignKeyConstraint(
            ["application_id", "organization_id"],
            ["recruiting_applications.id", "recruiting_applications.organization_id"],
            name="fk_recruiting_stage_events_application_tenant",
            ondelete="CASCADE",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    position_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    application_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    from_stage: Mapped[str | None] = mapped_column(String(32), nullable=True)
    to_stage: Mapped[str] = mapped_column(String(32), nullable=False)
    reason_code: Mapped[str | None] = mapped_column(String(80), nullable=True)
    actor_membership_id: Mapped[str | None] = mapped_column(
        ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True
    )
    idempotency_key: Mapped[str] = mapped_column(String(120), nullable=False)
    occurred_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), index=True, nullable=False)


class RecruitingAssessment(Base):
    __tablename__ = "recruiting_assessments"
    __table_args__ = (
        CheckConstraint(
            "status IN ('assigned', 'in_progress', 'completed', 'expired', 'cancelled')",
            name="ck_recruiting_assessments_status",
        ),
        UniqueConstraint("id", "organization_id", name="uq_recruiting_assessments_id_organization"),
        ForeignKeyConstraint(
            ["application_id", "organization_id"],
            ["recruiting_applications.id", "recruiting_applications.organization_id"],
            name="fk_recruiting_assessments_application_tenant",
            ondelete="CASCADE",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    application_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    title: Mapped[str | None] = mapped_column(String(180), nullable=True)
    required: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    status: Mapped[str] = mapped_column(String(24), index=True, nullable=False, default="assigned")
    assigned_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    started_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    completed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), index=True, nullable=True)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)


class RecruitingScorecard(Base):
    __tablename__ = "recruiting_scorecards"
    __table_args__ = (
        CheckConstraint(
            "scorecard_type IN ('technical', 'recruiter')",
            name="ck_recruiting_scorecards_type",
        ),
        CheckConstraint(
            "status IN ('pending', 'in_progress', 'submitted', 'cancelled')",
            name="ck_recruiting_scorecards_status",
        ),
        ForeignKeyConstraint(
            ["application_id", "organization_id"],
            ["recruiting_applications.id", "recruiting_applications.organization_id"],
            name="fk_recruiting_scorecards_application_tenant",
            ondelete="CASCADE",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    application_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    reviewer_membership_id: Mapped[str | None] = mapped_column(
        ForeignKey("organization_memberships.id", ondelete="SET NULL"), index=True, nullable=True
    )
    scorecard_type: Mapped[str] = mapped_column(String(24), nullable=False, default="technical")
    status: Mapped[str] = mapped_column(String(24), index=True, nullable=False, default="pending")
    overall_score: Mapped[int | None] = mapped_column(Integer, nullable=True)
    requested_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    due_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    submitted_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)


class AssessmentUsageLedger(Base):
    __tablename__ = "assessment_usage_ledger"
    __table_args__ = (
        CheckConstraint(
            "entry_type IN ('consume', 'credit', 'adjustment')",
            name="ck_assessment_usage_ledger_entry_type",
        ),
        CheckConstraint("units <> 0", name="ck_assessment_usage_ledger_units_nonzero"),
        UniqueConstraint("organization_id", "idempotency_key", name="uq_assessment_usage_ledger_idempotency"),
        ForeignKeyConstraint(
            ["assessment_id", "organization_id"],
            ["recruiting_assessments.id", "recruiting_assessments.organization_id"],
            name="fk_assessment_usage_ledger_assessment_tenant",
            ondelete="RESTRICT",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    assessment_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    entry_type: Mapped[str] = mapped_column(String(20), nullable=False)
    units: Mapped[int] = mapped_column(Integer, nullable=False)
    idempotency_key: Mapped[str] = mapped_column(String(120), nullable=False)
    reason_code: Mapped[str] = mapped_column(String(80), nullable=False)
    occurred_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), index=True, nullable=False)


class RecruitingApplicationSnapshot(Base):
    __tablename__ = "recruiting_application_snapshots"
    __table_args__ = (
        ForeignKeyConstraint(
            ["application_id"],
            ["recruiting_applications.id"],
            name="fk_recruiting_application_snapshots_application",
            ondelete="CASCADE",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    application_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    schema_version: Mapped[int] = mapped_column(Integer, nullable=False, default=1)
    payload: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    consent_scope: Mapped[str] = mapped_column(String(80), nullable=False, default="all")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)

class OrganizationAtsConfiguration(Base):
    __tablename__ = "organization_ats_configurations"
    __table_args__ = (CheckConstraint("provider IN ('generic', 'greenhouse', 'lever', 'workable', 'sap_successfactors', 'teamtailor', 'custom')", name="ck_organization_ats_configurations_provider"),)
    organization_id: Mapped[str] = mapped_column(ForeignKey("organizations.id", ondelete="CASCADE"), primary_key=True)
    provider: Mapped[str] = mapped_column(String(32), nullable=False, default="generic")
    system_name: Mapped[str | None] = mapped_column(String(120), nullable=True)
    terms: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    notes: Mapped[str | None] = mapped_column(Text, nullable=True)
    candidate_analysis_instructions: Mapped[str | None] = mapped_column(Text, nullable=True)
    updated_by_membership_id: Mapped[str | None] = mapped_column(ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class RecruitingPositionCriteriaVersion(Base):
    __tablename__ = "recruiting_position_criteria_versions"
    __table_args__ = (
        CheckConstraint("status IN ('draft', 'approved', 'superseded')", name="ck_recruiting_position_criteria_status"),
        UniqueConstraint("organization_id", "position_id", "version_number", name="uq_recruiting_position_criteria_version"),
        Index("uq_recruiting_position_criteria_active", "organization_id", "position_id", unique=True, postgresql_where=text("status = 'approved'"), sqlite_where=text("status = 'approved'")),
        ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_position_criteria_position_tenant", ondelete="CASCADE"),
    )
    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False)
    position_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    version_number: Mapped[int] = mapped_column(Integer, nullable=False)
    status: Mapped[str] = mapped_column(String(20), nullable=False, default="draft", index=True)
    criteria: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    ai_suggestions: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    created_by_membership_id: Mapped[str | None] = mapped_column(ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True)
    approved_by_membership_id: Mapped[str | None] = mapped_column(ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True)
    approved_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class RecruitingPositionAiAnalysis(Base):
    __tablename__ = "recruiting_position_ai_analyses"
    __table_args__ = (
        CheckConstraint("status IN ('queued', 'processing', 'completed', 'failed')", name="ck_recruiting_position_ai_analysis_status"),
        ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_position_ai_analysis_position_tenant", ondelete="CASCADE"),
    )
    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False)
    position_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    criteria_version_id: Mapped[str] = mapped_column(ForeignKey("recruiting_position_criteria_versions.id", ondelete="CASCADE"), nullable=False)
    status: Mapped[str] = mapped_column(String(20), nullable=False, default="queued", index=True)
    input_snapshot: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    result: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    error_code: Mapped[str | None] = mapped_column(String(60), nullable=True)
    error_message: Mapped[str | None] = mapped_column(String(500), nullable=True)
    requested_by_membership_id: Mapped[str | None] = mapped_column(ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    completed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)


class RecruitingShareLink(Base):
    __tablename__ = "recruiting_share_links"
    __table_args__ = (ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_share_links_position_tenant", ondelete="CASCADE"),)
    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False)
    position_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    channel: Mapped[str] = mapped_column(String(40), nullable=False, index=True)
    label: Mapped[str] = mapped_column(String(160), nullable=False)
    short_code: Mapped[str] = mapped_column(String(16), unique=True, nullable=False, index=True, default=new_short_code)
    campaign: Mapped[str | None] = mapped_column(String(120), nullable=True)
    agency_reference: Mapped[str | None] = mapped_column(String(160), nullable=True)
    employee_reference: Mapped[str | None] = mapped_column(String(160), nullable=True)
    source_description: Mapped[str | None] = mapped_column(Text, nullable=True)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    application_limit: Mapped[int | None] = mapped_column(Integer, nullable=True)
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    click_count: Mapped[int] = mapped_column(Integer, nullable=False, default=0)
    created_by_membership_id: Mapped[str | None] = mapped_column(ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class RecruitingPositionActivity(Base):
    __tablename__ = "recruiting_position_activities"
    __table_args__ = (ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_position_activities_position_tenant", ondelete="CASCADE"),)
    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False)
    position_id: Mapped[str] = mapped_column(String(36), index=True, nullable=False)
    event_type: Mapped[str] = mapped_column(String(80), index=True, nullable=False)
    entity_type: Mapped[str] = mapped_column(String(40), nullable=False, default="position")
    entity_id: Mapped[str | None] = mapped_column(String(36), nullable=True)
    actor_membership_id: Mapped[str | None] = mapped_column(ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True)
    actor_user_id: Mapped[int | None] = mapped_column(ForeignKey("users.id", ondelete="SET NULL"), nullable=True)
    details: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    occurred_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), index=True, nullable=False)


class CompanyTaskOutbox(Base):
    """Kurum kapsamındaki asenkron görevlerin güvenilir teslim kuyruğu."""

    __tablename__ = "company_task_outbox"
    __table_args__ = (
        CheckConstraint(
            "task_name IN ('company.analyze_position', 'company.analyze_candidate_application')",
            name="ck_company_task_outbox_task_name",
        ),
        CheckConstraint(
            "status IN ('pending', 'dispatching', 'dispatched', 'processing', 'succeeded', 'failed', 'dead_letter')",
            name="ck_company_task_outbox_status",
        ),
        CheckConstraint("attempt_count >= 0", name="ck_company_task_outbox_attempt_count_nonnegative"),
        CheckConstraint("max_attempts > 0", name="ck_company_task_outbox_max_attempts_positive"),
        UniqueConstraint("dedupe_key", name="uq_company_task_outbox_dedupe_key"),
        Index(
            "ix_company_task_outbox_dispatch",
            "status",
            "available_at",
            "id",
        ),
        Index(
            "ix_company_task_outbox_lease",
            "lease_until",
            "id",
        ),
        Index(
            "ix_company_task_outbox_organization_status_created",
            "organization_id",
            "status",
            "created_at",
        ),
        Index(
            "ix_company_task_outbox_aggregate",
            "aggregate_type",
            "aggregate_id",
        ),
        Index(
            "ix_company_task_outbox_pending",
            "available_at",
            "id",
            postgresql_where=text("status = 'pending'"),
            sqlite_where=text("status = 'pending'"),
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), nullable=False
    )
    task_name: Mapped[str] = mapped_column(String(160), nullable=False)
    aggregate_type: Mapped[str | None] = mapped_column(String(80), nullable=True)
    aggregate_id: Mapped[str | None] = mapped_column(String(120), nullable=True)
    payload: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    dedupe_key: Mapped[str] = mapped_column(String(200), unique=True, nullable=False)
    status: Mapped[str] = mapped_column(String(20), nullable=False, default="pending")
    attempt_count: Mapped[int] = mapped_column(Integer, nullable=False, default=0)
    max_attempts: Mapped[int] = mapped_column(Integer, nullable=False, default=5)
    available_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    lease_until: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    lock_token: Mapped[str | None] = mapped_column(String(120), nullable=True)
    celery_task_id: Mapped[str | None] = mapped_column(String(255), nullable=True)
    last_error: Mapped[str | None] = mapped_column(Text, nullable=True)
    published_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    started_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    completed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )
