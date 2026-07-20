"""Add company position, ATS, criteria and public application core.

Revision ID: 20260720_14
Revises: 20260720_13
"""

from __future__ import annotations

import secrets
import re
import unicodedata

from alembic import op
import sqlalchemy as sa


revision = "20260720_14"
down_revision = "20260720_13"
branch_labels = None
depends_on = None

_ALPHABET = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ"


def _public_id() -> str:
    return "".join(secrets.choice(_ALPHABET) for _ in range(10))


def _slugify(value: str) -> str:
    value = value.translate(str.maketrans({"ı": "i", "İ": "I", "ş": "s", "Ş": "S", "ğ": "g", "Ğ": "G", "ü": "u", "Ü": "U", "ö": "o", "Ö": "O", "ç": "c", "Ç": "C"}))
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "-", value).strip("-")[:160] or "pozisyon"


def upgrade() -> None:
    op.drop_constraint("ck_recruiting_positions_status", "recruiting_positions", type_="check")
    op.execute("UPDATE recruiting_positions SET status = 'published' WHERE status = 'open'")
    op.create_check_constraint(
        "ck_recruiting_positions_status", "recruiting_positions",
        "status IN ('draft', 'published', 'paused', 'closed', 'archived')",
    )
    op.create_unique_constraint(
        "uq_organization_memberships_id_organization", "organization_memberships", ["id", "organization_id"]
    )

    position_columns = [
        sa.Column("slug", sa.String(180)), sa.Column("public_id", sa.String(16)),
        sa.Column("level", sa.String(80)), sa.Column("location", sa.String(180)),
        sa.Column("salary_min", sa.Numeric(14, 2)), sa.Column("salary_max", sa.Numeric(14, 2)),
        sa.Column("salary_currency", sa.String(3)), sa.Column("responsibilities", sa.Text()),
        sa.Column("must_have_skills", sa.JSON(), nullable=False, server_default=sa.text("'[]'::json")),
        sa.Column("preferred_skills", sa.JSON(), nullable=False, server_default=sa.text("'[]'::json")),
        sa.Column("learnable_skills", sa.JSON(), nullable=False, server_default=sa.text("'[]'::json")),
        sa.Column("experience_expectation", sa.Text()), sa.Column("language_work_authorization", sa.Text()),
        sa.Column("source_text", sa.Text()),
        sa.Column("ats_terms", sa.JSON(), nullable=False, server_default=sa.text("'[]'::json")),
        sa.Column("ats_notes", sa.Text()),
        sa.Column("evaluation_config", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("application_form_id", sa.String(80)), sa.Column("assessment_template_id", sa.String(80)),
        sa.Column("retention_days", sa.Integer(), nullable=False, server_default="180"),
        sa.Column("target_start_date", sa.Date()),
        sa.Column("recruiter_membership_id", sa.String(36)),
        sa.Column("technical_manager_membership_id", sa.String(36)),
    ]
    for column in position_columns:
        op.add_column("recruiting_positions", column)
    bind = op.get_bind()
    position_table = sa.table(
        "recruiting_positions", sa.column("id", sa.String()), sa.column("title", sa.String()),
        sa.column("slug", sa.String()), sa.column("public_id", sa.String()),
    )
    used: set[str] = set()
    for position_id, title in bind.execute(sa.select(position_table.c.id, position_table.c.title)):
        token = _public_id()
        while token in used:
            token = _public_id()
        used.add(token)
        bind.execute(position_table.update().where(position_table.c.id == position_id).values(slug=_slugify(title), public_id=token))
    op.alter_column("recruiting_positions", "slug", nullable=False)
    op.alter_column("recruiting_positions", "public_id", nullable=False)
    op.create_unique_constraint("uq_recruiting_positions_public_id", "recruiting_positions", ["public_id"])
    op.create_index("ix_recruiting_positions_slug", "recruiting_positions", ["slug"])
    op.create_index("ix_recruiting_positions_public_id", "recruiting_positions", ["public_id"])
    op.create_foreign_key(
        "fk_recruiting_positions_recruiter_tenant", "recruiting_positions", "organization_memberships",
        ["recruiter_membership_id", "organization_id"], ["id", "organization_id"],
    )
    op.create_foreign_key(
        "fk_recruiting_positions_technical_manager_tenant", "recruiting_positions", "organization_memberships",
        ["technical_manager_membership_id", "organization_id"], ["id", "organization_id"],
    )

    op.create_table(
        "organization_ats_configurations",
        sa.Column("organization_id", sa.String(36), primary_key=True),
        sa.Column("provider", sa.String(32), nullable=False, server_default="generic"),
        sa.Column("system_name", sa.String(120)),
        sa.Column("terms", sa.JSON(), nullable=False, server_default=sa.text("'[]'::json")),
        sa.Column("notes", sa.Text()), sa.Column("candidate_analysis_instructions", sa.Text()),
        sa.Column("updated_by_membership_id", sa.String(36)),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["updated_by_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
        sa.CheckConstraint("provider IN ('generic', 'greenhouse', 'lever', 'workable', 'sap_successfactors', 'teamtailor', 'custom')", name="ck_organization_ats_configurations_provider"),
    )
    op.create_table(
        "recruiting_position_criteria_versions",
        sa.Column("id", sa.String(36), primary_key=True), sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("position_id", sa.String(36), nullable=False), sa.Column("version_number", sa.Integer(), nullable=False),
        sa.Column("status", sa.String(20), nullable=False, server_default="draft"),
        sa.Column("criteria", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("ai_suggestions", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("created_by_membership_id", sa.String(36)), sa.Column("approved_by_membership_id", sa.String(36)),
        sa.Column("approved_at", sa.DateTime(timezone=True)),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.CheckConstraint("status IN ('draft', 'approved', 'superseded')", name="ck_recruiting_position_criteria_status"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_position_criteria_position_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["created_by_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
        sa.ForeignKeyConstraint(["approved_by_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
        sa.UniqueConstraint("organization_id", "position_id", "version_number", name="uq_recruiting_position_criteria_version"),
    )
    op.create_index("ix_recruiting_position_criteria_versions_organization_id", "recruiting_position_criteria_versions", ["organization_id"])
    op.create_index("ix_recruiting_position_criteria_versions_position_id", "recruiting_position_criteria_versions", ["position_id"])
    op.create_index("ix_recruiting_position_criteria_versions_status", "recruiting_position_criteria_versions", ["status"])
    op.create_index(
        "uq_recruiting_position_criteria_active", "recruiting_position_criteria_versions",
        ["organization_id", "position_id"], unique=True, postgresql_where=sa.text("status = 'approved'"),
    )
    op.create_table(
        "recruiting_position_ai_analyses",
        sa.Column("id", sa.String(36), primary_key=True), sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("position_id", sa.String(36), nullable=False), sa.Column("criteria_version_id", sa.String(36), nullable=False),
        sa.Column("status", sa.String(20), nullable=False, server_default="queued"),
        sa.Column("input_snapshot", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("result", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("error_code", sa.String(60)), sa.Column("error_message", sa.String(500)),
        sa.Column("requested_by_membership_id", sa.String(36)),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("completed_at", sa.DateTime(timezone=True)),
        sa.CheckConstraint("status IN ('queued', 'processing', 'completed', 'failed')", name="ck_recruiting_position_ai_analysis_status"),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_position_ai_analysis_position_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["criteria_version_id"], ["recruiting_position_criteria_versions.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["requested_by_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
    )
    for column in ("organization_id", "position_id", "status"):
        op.create_index(f"ix_recruiting_position_ai_analyses_{column}", "recruiting_position_ai_analyses", [column])

    op.create_table(
        "recruiting_share_links",
        sa.Column("id", sa.String(36), primary_key=True), sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("position_id", sa.String(36), nullable=False), sa.Column("channel", sa.String(40), nullable=False),
        sa.Column("label", sa.String(160), nullable=False), sa.Column("short_code", sa.String(16), nullable=False),
        sa.Column("campaign", sa.String(120)), sa.Column("agency_reference", sa.String(160)),
        sa.Column("employee_reference", sa.String(160)), sa.Column("source_description", sa.Text()),
        sa.Column("expires_at", sa.DateTime(timezone=True)), sa.Column("application_limit", sa.Integer()),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default=sa.true()),
        sa.Column("click_count", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("created_by_membership_id", sa.String(36)),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_share_links_position_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["created_by_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
        sa.UniqueConstraint("short_code", name="uq_recruiting_share_links_short_code"),
    )
    for column in ("organization_id", "position_id", "channel", "short_code"):
        op.create_index(f"ix_recruiting_share_links_{column}", "recruiting_share_links", [column])

    application_columns = [
        sa.Column("cv_document_id", sa.String(36)), sa.Column("criteria_version_id", sa.String(36)), sa.Column("original_share_link_id", sa.String(36)),
        sa.Column("last_share_link_id", sa.String(36)),
        sa.Column("consent_snapshot", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("application_snapshot", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("ats_context_snapshot", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("analysis_status", sa.String(24), nullable=False, server_default="not_requested"),
        sa.Column("analysis_result", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
    ]
    for column in application_columns:
        op.add_column("recruiting_applications", column)
    op.create_foreign_key("fk_recruiting_applications_cv_document", "recruiting_applications", "cv_documents", ["cv_document_id"], ["id"], ondelete="SET NULL")
    op.create_foreign_key("fk_recruiting_applications_criteria_version", "recruiting_applications", "recruiting_position_criteria_versions", ["criteria_version_id"], ["id"], ondelete="SET NULL")
    op.create_foreign_key("fk_recruiting_applications_original_share_link", "recruiting_applications", "recruiting_share_links", ["original_share_link_id"], ["id"], ondelete="SET NULL")
    op.create_foreign_key("fk_recruiting_applications_last_share_link", "recruiting_applications", "recruiting_share_links", ["last_share_link_id"], ["id"], ondelete="SET NULL")
    op.create_unique_constraint("uq_recruiting_applications_position_candidate", "recruiting_applications", ["organization_id", "position_id", "candidate_user_id"])
    for column in ("cv_document_id", "criteria_version_id", "original_share_link_id", "last_share_link_id"):
        op.create_index(f"ix_recruiting_applications_{column}", "recruiting_applications", [column])

    op.create_table(
        "recruiting_position_activities",
        sa.Column("id", sa.String(36), primary_key=True), sa.Column("organization_id", sa.String(36), nullable=False),
        sa.Column("position_id", sa.String(36), nullable=False), sa.Column("event_type", sa.String(80), nullable=False),
        sa.Column("entity_type", sa.String(40), nullable=False, server_default="position"),
        sa.Column("entity_id", sa.String(36)), sa.Column("actor_membership_id", sa.String(36)),
        sa.Column("actor_user_id", sa.Integer()),
        sa.Column("details", sa.JSON(), nullable=False, server_default=sa.text("'{}'::json")),
        sa.Column("occurred_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.ForeignKeyConstraint(["organization_id"], ["organizations.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["position_id", "organization_id"], ["recruiting_positions.id", "recruiting_positions.organization_id"], name="fk_recruiting_position_activities_position_tenant", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["actor_membership_id"], ["organization_memberships.id"], ondelete="SET NULL"),
        sa.ForeignKeyConstraint(["actor_user_id"], ["users.id"], ondelete="SET NULL"),
    )
    for column in ("organization_id", "position_id", "event_type", "occurred_at"):
        op.create_index(f"ix_recruiting_position_activities_{column}", "recruiting_position_activities", [column])

    for table in ("organization_memberships", "organization_invitations"):
        op.execute(f"""
            UPDATE {table}
            SET permissions = CASE
                WHEN role IN ('owner', 'admin') THEN (permissions::jsonb || '[\"ats_config.view\",\"ats_config.write\"]'::jsonb)::json
                WHEN role = 'recruiter' THEN (permissions::jsonb || '[\"ats_config.view\",\"ats_config.write\"]'::jsonb)::json
                WHEN role IN ('hiring_manager', 'viewer') THEN (permissions::jsonb || '[\"ats_config.view\"]'::jsonb)::json
                ELSE permissions
            END
        """)


def downgrade() -> None:
    raise RuntimeError("Company position core migration is forward-only.")
