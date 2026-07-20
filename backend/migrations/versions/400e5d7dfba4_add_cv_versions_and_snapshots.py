"""Add cv versions and snapshots

Revision ID: 400e5d7dfba4
Revises: 20260720_13
Create Date: 2026-07-20 23:19:19.725642
"""
from typing import Sequence, Union
from alembic import op
import sqlalchemy as sa


revision: str = '400e5d7dfba4'
down_revision: Union[str, None] = '20260720_13'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

def upgrade() -> None:
    op.create_table(
        "candidate_cv_versions",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("user_id", sa.Integer(), nullable=False),
        sa.Column("version_name", sa.String(160), nullable=False),
        sa.Column("language", sa.String(8), nullable=False),
        sa.Column("is_main", sa.Boolean(), nullable=False, server_default=sa.false()),
        sa.Column("payload", sa.JSON(), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.ForeignKeyConstraint(["user_id"], ["users.id"], ondelete="CASCADE"),
    )
    op.create_index(
        "ix_candidate_cv_versions_user_is_main",
        "candidate_cv_versions",
        ["user_id", "is_main"]
    )

    op.create_table(
        "recruiting_application_snapshots",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("application_id", sa.String(36), nullable=False),
        sa.Column("schema_version", sa.Integer(), nullable=False, server_default="1"),
        sa.Column("payload", sa.JSON(), nullable=False),
        sa.Column("consent_scope", sa.String(80), nullable=False, server_default="all"),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.ForeignKeyConstraint(["application_id"], ["recruiting_applications.id"], ondelete="CASCADE"),
    )
    op.create_index(
        "ix_recruiting_application_snapshots_application_id",
        "recruiting_application_snapshots",
        ["application_id"]
    )

def downgrade() -> None:
    op.drop_table("recruiting_application_snapshots")
    op.drop_table("candidate_cv_versions")

