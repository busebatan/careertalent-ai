"""Persist job-analysis CV provenance metadata.

Revision ID: 20260721_18
Revises: 20260721_17
"""

from alembic import op
import sqlalchemy as sa


revision = "20260721_18"
down_revision = "20260721_17"
branch_labels = None
depends_on = None


def upgrade() -> None:
    with op.batch_alter_table("job_opportunities") as batch_op:
        batch_op.add_column(sa.Column("source_analysis_id", sa.String(36), nullable=True))
        batch_op.add_column(sa.Column("source_cv_file_name", sa.String(255), nullable=True))


def downgrade() -> None:
    with op.batch_alter_table("job_opportunities") as batch_op:
        batch_op.drop_column("source_cv_file_name")
        batch_op.drop_column("source_analysis_id")
