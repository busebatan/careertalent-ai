"""Add transactional company task outbox.

Revision ID: 20260720_15
Revises: 20260720_14
"""

from alembic import op
import sqlalchemy as sa


revision = "20260720_15"
down_revision = "20260720_14"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.create_table(
        "company_task_outbox",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("task_name", sa.String(160), nullable=False),
        sa.Column("aggregate_type", sa.String(80)),
        sa.Column("aggregate_id", sa.String(120)),
        sa.Column("payload", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("dedupe_key", sa.String(200), nullable=False),
        sa.Column("status", sa.String(20), nullable=False, server_default="pending"),
        sa.Column("attempt_count", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("max_attempts", sa.Integer(), nullable=False, server_default="5"),
        sa.Column("available_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("lease_until", sa.DateTime(timezone=True)),
        sa.Column("lock_token", sa.String(120)),
        sa.Column("celery_task_id", sa.String(255)),
        sa.Column("last_error", sa.Text()),
        sa.Column("published_at", sa.DateTime(timezone=True)),
        sa.Column("started_at", sa.DateTime(timezone=True)),
        sa.Column("completed_at", sa.DateTime(timezone=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.CheckConstraint(
            "task_name IN ('company.analyze_position', 'company.analyze_candidate_application')",
            name="ck_company_task_outbox_task_name",
        ),
        sa.CheckConstraint(
            "status IN ('pending', 'dispatching', 'dispatched', 'processing', 'succeeded', 'failed', 'dead_letter')",
            name="ck_company_task_outbox_status",
        ),
        sa.CheckConstraint("attempt_count >= 0", name="ck_company_task_outbox_attempt_count_nonnegative"),
        sa.CheckConstraint("max_attempts > 0", name="ck_company_task_outbox_max_attempts_positive"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.UniqueConstraint("dedupe_key", name="uq_company_task_outbox_dedupe_key"),
    )
    op.create_index(
        "ix_company_task_outbox_dispatch",
        "company_task_outbox",
        ["status", "available_at", "id"],
    )
    op.create_index(
        "ix_company_task_outbox_lease",
        "company_task_outbox",
        ["lease_until", "id"],
    )
    op.create_index(
        "ix_company_task_outbox_organization_status_created",
        "company_task_outbox",
        ["organization_id", "status", "created_at"],
    )
    op.create_index(
        "ix_company_task_outbox_aggregate",
        "company_task_outbox",
        ["aggregate_type", "aggregate_id"],
    )
    op.create_index(
        "ix_company_task_outbox_pending",
        "company_task_outbox",
        ["available_at", "id"],
        postgresql_where=sa.text("status = 'pending'"),
    )
    op.execute("""
        INSERT INTO company_task_outbox (
            id, organization_id, task_name, aggregate_type, aggregate_id,
            payload, dedupe_key, status, attempt_count, max_attempts,
            available_at, created_at, updated_at
        )
        SELECT
            'pos-' || md5('company.analyze_position:' || analysis.id),
            analysis.organization_id,
            'company.analyze_position',
            'position_ai_analysis',
            analysis.id,
            json_build_object('schema_version', 1, 'aggregate_id', analysis.id),
            'company.analyze_position:' || analysis.id,
            'pending', 0, 5, now(), now(), now()
        FROM recruiting_position_ai_analyses AS analysis
        WHERE analysis.status IN ('queued', 'processing')
        ON CONFLICT (dedupe_key) DO NOTHING
    """)
    op.execute("""
        INSERT INTO company_task_outbox (
            id, organization_id, task_name, aggregate_type, aggregate_id,
            payload, dedupe_key, status, attempt_count, max_attempts,
            available_at, created_at, updated_at
        )
        SELECT
            'app-' || md5('company.analyze_candidate_application:' || application.id),
            application.organization_id,
            'company.analyze_candidate_application',
            'recruiting_application',
            application.id,
            json_build_object('schema_version', 1, 'aggregate_id', application.id),
            'company.analyze_candidate_application:' || application.id,
            'pending', 0, 5, now(), now(), now()
        FROM recruiting_applications AS application
        WHERE application.analysis_status IN ('queued', 'processing')
        ON CONFLICT (dedupe_key) DO NOTHING
    """)


def downgrade() -> None:
    raise RuntimeError("Company task outbox migration is forward-only.")
