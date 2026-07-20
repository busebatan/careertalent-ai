"""Add company recruiting dashboard domain.

Revision ID: 20260720_13
Revises: 20260719_12
"""

from alembic import op
import sqlalchemy as sa


revision = "20260720_13"
down_revision = "20260719_12"
branch_labels = None
depends_on = None

_ALL = [
    "dashboard.view", "positions.view", "positions.write", "positions.delete",
    "applications.view", "applications.write", "assessments.view", "assessments.write",
    "scorecards.view", "scorecards.submit", "organization.update", "members.view",
    "members.invite", "members.manage",
]
_RECRUITER = [
    "dashboard.view", "positions.view", "positions.write", "positions.delete",
    "applications.view", "applications.write", "assessments.view", "assessments.write",
    "scorecards.view", "members.view",
]
_HIRING_MANAGER = [
    "dashboard.view", "positions.view", "applications.view", "applications.write",
    "assessments.view", "scorecards.view", "scorecards.submit", "members.view",
]
_VIEWER = [
    "dashboard.view", "positions.view", "applications.view", "assessments.view",
    "scorecards.view", "members.view",
]


def upgrade() -> None:
    op.create_table(
        "recruiting_positions",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("title", sa.String(160), nullable=False),
        sa.Column("department", sa.String(120)),
        sa.Column("employment_type", sa.String(24)),
        sa.Column("workplace_type", sa.String(24)),
        sa.Column("description", sa.Text()),
        sa.Column("status", sa.String(20), nullable=False, server_default="draft"),
        sa.Column("application_deadline", sa.DateTime(timezone=True)),
        sa.Column("opened_at", sa.DateTime(timezone=True)),
        sa.Column("closed_at", sa.DateTime(timezone=True)),
        sa.Column("created_by_membership_id", sa.String(36)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.CheckConstraint("status IN ('draft', 'open', 'paused', 'closed', 'archived')", name="ck_recruiting_positions_status"),
        sa.CheckConstraint("employment_type IS NULL OR employment_type IN ('full_time', 'part_time', 'contract', 'internship')", name="ck_recruiting_positions_employment_type"),
        sa.CheckConstraint("workplace_type IS NULL OR workplace_type IN ('onsite', 'hybrid', 'remote')", name="ck_recruiting_positions_workplace_type"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["created_by_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
        sa.UniqueConstraint("id", "organization_id", name="uq_recruiting_positions_id_organization"),
    )
    op.create_index("ix_recruiting_positions_organization_id", "recruiting_positions", ["organization_id"])
    op.create_index("ix_recruiting_positions_status", "recruiting_positions", ["status"])
    op.create_index("ix_recruiting_positions_application_deadline", "recruiting_positions", ["application_deadline"])
    op.create_index("ix_recruiting_positions_org_status", "recruiting_positions", ["organization_id", "status"])

    op.create_table(
        "recruiting_applications",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("position_id", sa.String(36), nullable=False),
        sa.Column("candidate_user_id", sa.Integer()),
        sa.Column("candidate_name", sa.String(160), nullable=False),
        sa.Column("candidate_email", sa.String(255), nullable=False),
        sa.Column("current_stage", sa.String(32), nullable=False, server_default="new"),
        sa.Column("first_reviewed_at", sa.DateTime(timezone=True)),
        sa.Column("applied_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("retention_expires_at", sa.DateTime(timezone=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.CheckConstraint("current_stage IN ('new', 'assessment_pending', 'assessment_in_progress', 'technical_review', 'shortlisted', 'interview', 'offer', 'hired', 'rejected', 'withdrawn')", name="ck_recruiting_applications_stage"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_applications_position_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["candidate_user_id"], ["users.id"], ondelete="SET NULL"),
        sa.UniqueConstraint("organization_id", "position_id", "candidate_email", name="uq_recruiting_applications_position_email"),
        sa.UniqueConstraint("id", "organization_id", name="uq_recruiting_applications_id_organization"),
    )
    for column in ("organization_id", "position_id", "candidate_user_id", "candidate_email", "current_stage", "applied_at", "retention_expires_at"):
        op.create_index(f"ix_recruiting_applications_{column}", "recruiting_applications", [column])
    op.create_index("ix_recruiting_applications_org_stage", "recruiting_applications", ["organization_id", "current_stage"])

    op.create_table(
        "recruiting_application_stage_events",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("position_id", sa.String(36), nullable=False),
        sa.Column("application_id", sa.String(36), nullable=False),
        sa.Column("from_stage", sa.String(32)),
        sa.Column("to_stage", sa.String(32), nullable=False),
        sa.Column("reason_code", sa.String(80)),
        sa.Column("actor_membership_id", sa.String(36)),
        sa.Column("idempotency_key", sa.String(120), nullable=False),
        sa.Column("occurred_at", sa.DateTime(timezone=True), nullable=False),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_stage_events_position_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["application_id", "organization_id"], ["recruiting_applications.id", "recruiting_applications.organization_id"], name="fk_recruiting_stage_events_application_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["actor_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
        sa.UniqueConstraint("organization_id", "idempotency_key", name="uq_recruiting_stage_events_idempotency"),
    )
    for column in ("organization_id", "position_id", "application_id", "occurred_at"):
        op.create_index(f"ix_recruiting_application_stage_events_{column}", "recruiting_application_stage_events", [column])

    op.create_table(
        "recruiting_assessments",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("application_id", sa.String(36), nullable=False),
        sa.Column("title", sa.String(180)),
        sa.Column("required", sa.Boolean(), nullable=False, server_default=sa.true()),
        sa.Column("status", sa.String(24), nullable=False, server_default="assigned"),
        sa.Column("assigned_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("started_at", sa.DateTime(timezone=True)),
        sa.Column("completed_at", sa.DateTime(timezone=True)),
        sa.Column("expires_at", sa.DateTime(timezone=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.CheckConstraint("status IN ('assigned', 'in_progress', 'completed', 'expired', 'cancelled')", name="ck_recruiting_assessments_status"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["application_id", "organization_id"], ["recruiting_applications.id", "recruiting_applications.organization_id"], name="fk_recruiting_assessments_application_tenant", ondelete="CASCADE"),
        sa.UniqueConstraint("id", "organization_id", name="uq_recruiting_assessments_id_organization"),
    )
    for column in ("organization_id", "application_id", "status", "completed_at"):
        op.create_index(f"ix_recruiting_assessments_{column}", "recruiting_assessments", [column])

    op.create_table(
        "recruiting_scorecards",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("application_id", sa.String(36), nullable=False),
        sa.Column("reviewer_membership_id", sa.String(36)),
        sa.Column("scorecard_type", sa.String(24), nullable=False, server_default="technical"),
        sa.Column("status", sa.String(24), nullable=False, server_default="pending"),
        sa.Column("overall_score", sa.Integer()),
        sa.Column("requested_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("due_at", sa.DateTime(timezone=True)),
        sa.Column("submitted_at", sa.DateTime(timezone=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.CheckConstraint("scorecard_type IN ('technical', 'recruiter')", name="ck_recruiting_scorecards_type"),
        sa.CheckConstraint("status IN ('pending', 'in_progress', 'submitted', 'cancelled')", name="ck_recruiting_scorecards_status"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["application_id", "organization_id"], ["recruiting_applications.id", "recruiting_applications.organization_id"], name="fk_recruiting_scorecards_application_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["reviewer_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
    )
    for column in ("organization_id", "application_id", "reviewer_membership_id", "status"):
        op.create_index(f"ix_recruiting_scorecards_{column}", "recruiting_scorecards", [column])

    op.create_table(
        "assessment_usage_ledger",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("assessment_id", sa.String(36), nullable=False),
        sa.Column("entry_type", sa.String(20), nullable=False),
        sa.Column("units", sa.Integer(), nullable=False),
        sa.Column("idempotency_key", sa.String(120), nullable=False),
        sa.Column("reason_code", sa.String(80), nullable=False),
        sa.Column("occurred_at", sa.DateTime(timezone=True), nullable=False),
        sa.CheckConstraint("entry_type IN ('consume', 'credit', 'adjustment')", name="ck_assessment_usage_ledger_entry_type"),
        sa.CheckConstraint("units <> 0", name="ck_assessment_usage_ledger_units_nonzero"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["assessment_id", "organization_id"], ["recruiting_assessments.id", "recruiting_assessments.organization_id"], name="fk_assessment_usage_ledger_assessment_tenant", ondelete="RESTRICT"),
        sa.UniqueConstraint("organization_id", "idempotency_key", name="uq_assessment_usage_ledger_idempotency"),
    )
    for column in ("organization_id", "assessment_id", "occurred_at"):
        op.create_index(f"ix_assessment_usage_ledger_{column}", "assessment_usage_ledger", [column])

    for table in ("organization_memberships", "organization_invitations"):
        op.execute(
            sa.text(
                f"""
                UPDATE {table}
                SET permissions = CASE
                    WHEN role IN ('owner', 'admin') THEN :all_permissions
                    WHEN role = 'recruiter' THEN :recruiter
                    WHEN role = 'hiring_manager' THEN :hiring_manager
                    WHEN role = 'viewer' THEN :viewer
                    ELSE permissions
                END
                """
            ).bindparams(
                sa.bindparam("all_permissions", _ALL, type_=sa.JSON()),
                sa.bindparam("recruiter", _RECRUITER, type_=sa.JSON()),
                sa.bindparam("hiring_manager", _HIRING_MANAGER, type_=sa.JSON()),
                sa.bindparam("viewer", _VIEWER, type_=sa.JSON()),
            )
        )


def downgrade() -> None:
    raise RuntimeError("Company recruiting dashboard migration is forward-only.")
