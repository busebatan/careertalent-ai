"""Persist per-CV dismissal of the builder import notice.

Revision ID: 20260723_21
Revises: 20260723_20
"""
from alembic import op
import sqlalchemy as sa


revision = "20260723_21"
down_revision = "20260723_20"
branch_labels = None
depends_on = None


def upgrade() -> None:
    with op.batch_alter_table("cv_documents") as batch_op:
        batch_op.add_column(
            sa.Column(
                "builder_import_notice_dismissed",
                sa.Boolean(),
                nullable=False,
                server_default=sa.text("false"),
            )
        )


def downgrade() -> None:
    with op.batch_alter_table("cv_documents") as batch_op:
        batch_op.drop_column("builder_import_notice_dismissed")
