"""CV dosya ve oluşturucu geçmişi.

Revision ID: 20260713_02
Revises: 20260713_01
"""
from alembic import op
import sqlalchemy as sa

revision = "20260713_02"
down_revision = "20260713_01"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.create_table(
        "cv_documents",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("user_id", sa.Integer(), nullable=False),
        sa.Column("kind", sa.String(20), nullable=False),
        sa.Column("display_name", sa.String(255), nullable=False),
        sa.Column("original_name", sa.String(255), nullable=False),
        sa.Column("file_path", sa.String(1024), nullable=False),
        sa.Column("file_size", sa.Integer(), nullable=False),
        sa.Column("language", sa.String(8), nullable=True),
        sa.Column("builder_data", sa.JSON(), nullable=True),
        sa.Column("is_current", sa.Boolean(), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False),
        sa.ForeignKeyConstraint(["user_id"], ["users.id"]),
    )
    op.create_index("ix_cv_documents_user_id", "cv_documents", ["user_id"])
    op.create_index("ix_cv_documents_kind", "cv_documents", ["kind"])
    op.create_index("ix_cv_documents_is_current", "cv_documents", ["is_current"])
    op.create_index("ix_cv_documents_created_at", "cv_documents", ["created_at"])


def downgrade() -> None:
    op.drop_table("cv_documents")
