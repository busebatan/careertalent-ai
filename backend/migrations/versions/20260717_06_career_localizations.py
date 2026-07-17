"""Persist the preferred panel locale and localized career content."""

from alembic import op
import sqlalchemy as sa


revision = "20260717_06"
down_revision = "20260716_05"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.add_column(
        "users",
        sa.Column("preferred_locale", sa.String(length=2), server_default="tr", nullable=False),
    )
    op.create_check_constraint(
        "ck_users_preferred_locale",
        "users",
        "preferred_locale IN ('tr', 'en')",
    )
    op.add_column(
        "career_analyses",
        sa.Column("localizations", sa.JSON(), server_default=sa.text("'{}'"), nullable=False),
    )
    op.add_column(
        "career_targets",
        sa.Column("localizations", sa.JSON(), server_default=sa.text("'{}'"), nullable=False),
    )
    op.add_column(
        "career_tasks",
        sa.Column("localizations", sa.JSON(), server_default=sa.text("'{}'"), nullable=False),
    )


def downgrade() -> None:
    op.drop_column("career_tasks", "localizations")
    op.drop_column("career_targets", "localizations")
    op.drop_column("career_analyses", "localizations")
    op.drop_constraint("ck_users_preferred_locale", "users", type_="check")
    op.drop_column("users", "preferred_locale")
