"""Analizi kaynak CV belgesine bağla.

Revision ID: 20260713_03
Revises: 20260713_02
"""
from alembic import op
import sqlalchemy as sa

revision = "20260713_03"
down_revision = "20260713_02"
branch_labels = None
depends_on = None


def upgrade() -> None:
    with op.batch_alter_table("career_analyses") as batch:
        batch.add_column(sa.Column("cv_document_id", sa.String(36), nullable=True))
        batch.create_index("ix_career_analyses_cv_document_id", ["cv_document_id"])
        batch.create_foreign_key(
            "fk_career_analyses_cv_document_id",
            "cv_documents", ["cv_document_id"], ["id"], ondelete="SET NULL",
        )


def downgrade() -> None:
    with op.batch_alter_table("career_analyses") as batch:
        batch.drop_constraint("fk_career_analyses_cv_document_id", type_="foreignkey")
        batch.drop_index("ix_career_analyses_cv_document_id")
        batch.drop_column("cv_document_id")
