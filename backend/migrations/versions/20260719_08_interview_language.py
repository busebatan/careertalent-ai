"""Add language column to career_interviews

Revision ID: 20260719_08
Revises: 20260717_07
Create Date: 2026-07-19
"""
from alembic import op
import sqlalchemy as sa

revision = "20260719_08"
down_revision = "20260717_07"
branch_labels = None
depends_on = None

COLUMN_NAME = "language"
TABLE_NAME = "career_interviews"
DEFAULT_LANGUAGE = "tr"


def upgrade() -> None:
    with op.batch_alter_table(TABLE_NAME) as batch_op:
        batch_op.add_column(
            sa.Column(
                COLUMN_NAME,
                sa.String(8),
                nullable=False,
                server_default=DEFAULT_LANGUAGE,
            )
        )


def downgrade() -> None:
    with op.batch_alter_table(TABLE_NAME) as batch_op:
        batch_op.drop_column(COLUMN_NAME)
