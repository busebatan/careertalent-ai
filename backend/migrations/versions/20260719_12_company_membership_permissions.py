"""Persist company permissions on invitations and memberships.

Revision ID: 20260719_12
Revises: 20260719_11
"""

from alembic import op
import sqlalchemy as sa


revision = "20260719_12"
down_revision = "20260719_11"
branch_labels = None
depends_on = None

_DASHBOARD = ["dashboard.view"]
_MEMBERS_VIEW = ["dashboard.view", "members.view"]
_ALL = ["dashboard.view", "organization.update", "members.view", "members.invite", "members.manage"]


def upgrade() -> None:
    default = sa.text("'[\"dashboard.view\"]'")
    op.add_column(
        "organization_memberships",
        sa.Column("permissions", sa.JSON(), nullable=False, server_default=default),
    )
    op.add_column(
        "organization_invitations",
        sa.Column("permissions", sa.JSON(), nullable=False, server_default=default),
    )
    for table in ("organization_memberships", "organization_invitations"):
        op.execute(
            sa.text(
                f"""
                UPDATE {table}
                SET permissions = CASE
                    WHEN role IN ('owner', 'admin') THEN :all_permissions
                    WHEN role IN ('recruiter', 'hiring_manager', 'viewer') THEN :members_view
                    ELSE :dashboard
                END
                """
            ).bindparams(
                sa.bindparam("all_permissions", _ALL, type_=sa.JSON()),
                sa.bindparam("members_view", _MEMBERS_VIEW, type_=sa.JSON()),
                sa.bindparam("dashboard", _DASHBOARD, type_=sa.JSON()),
            )
        )


def downgrade() -> None:
    raise RuntimeError("Company permission migration is forward-only.")
