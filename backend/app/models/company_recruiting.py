"""Kurum işe alım pozisyonları, aday kuyruğu ve ölçüm olayları."""

from datetime import datetime

from sqlalchemy import Boolean, CheckConstraint, DateTime, ForeignKey, ForeignKeyConstraint, Integer, String, Text, UniqueConstraint, func
from sqlalchemy.orm import Mapped, mapped_column

from app.core.database import Base


class RecruitingPosition(Base):
    __tablename__ = "recruiting_positions"
    __table_args__ = (
        CheckConstraint(
            "status IN ('draft', 'open', 'paused', 'closed', 'archived')",
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
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    title: Mapped[str] = mapped_column(String(160), nullable=False)
    department: Mapped[str | None] = mapped_column(String(120), nullable=True)
    employment_type: Mapped[str | None] = mapped_column(String(24), nullable=True)
    workplace_type: Mapped[str | None] = mapped_column(String(24), nullable=True)
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    status: Mapped[str] = mapped_column(String(20), index=True, nullable=False, default="draft")
    application_deadline: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), index=True, nullable=True)
    opened_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    closed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_by_membership_id: Mapped[str | None] = mapped_column(
        ForeignKey("organization_memberships.id", ondelete="SET NULL"), nullable=True
    )
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
