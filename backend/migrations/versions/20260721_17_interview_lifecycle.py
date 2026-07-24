"""Persist interview lifecycle, source snapshots and retry lineage.

Revision ID: 20260721_17
Revises: 20260720_16
"""

from alembic import op
import sqlalchemy as sa


revision = "20260721_17"
down_revision = "20260720_16"
branch_labels = None
depends_on = None


def upgrade() -> None:
    with op.batch_alter_table("career_interviews") as batch_op:
        batch_op.add_column(sa.Column("analysis_id", sa.String(36), nullable=True))
        batch_op.add_column(sa.Column("cv_document_id", sa.String(36), nullable=True))
        batch_op.add_column(sa.Column("cv_name_snapshot", sa.String(255), nullable=True))
        batch_op.add_column(
            sa.Column("context_snapshot", sa.JSON(), nullable=False, server_default=sa.text("'{}'"))
        )
        batch_op.add_column(sa.Column("retry_of_id", sa.String(36), nullable=True))
        batch_op.add_column(sa.Column("ended_at", sa.DateTime(timezone=True), nullable=True))
        batch_op.create_foreign_key(
            "fk_career_interviews_analysis_id",
            "career_analyses",
            ["analysis_id"],
            ["id"],
            ondelete="SET NULL",
        )
        batch_op.create_foreign_key(
            "fk_career_interviews_cv_document_id",
            "cv_documents",
            ["cv_document_id"],
            ["id"],
            ondelete="SET NULL",
        )
        batch_op.create_foreign_key(
            "fk_career_interviews_retry_of_id",
            "career_interviews",
            ["retry_of_id"],
            ["id"],
            ondelete="SET NULL",
        )

    bind = op.get_bind()
    bind.execute(
        sa.text(
            "UPDATE career_interviews "
            "SET status = 'archived', ended_at = COALESCE(updated_at, created_at, CURRENT_TIMESTAMP) "
            "WHERE status = 'active'"
        )
    )
    bind.execute(
        sa.text(
            "UPDATE career_interviews "
            "SET ended_at = COALESCE(updated_at, created_at, CURRENT_TIMESTAMP) "
            "WHERE status <> 'active' AND ended_at IS NULL"
        )
    )
    bind.execute(
        sa.text(
            "DELETE FROM career_interview_answers WHERE id IN ("
            "SELECT id FROM ("
            "SELECT id, ROW_NUMBER() OVER ("
            "PARTITION BY interview_id, question_id "
            "ORDER BY created_at DESC, id DESC"
            ") AS duplicate_order FROM career_interview_answers"
            ") ranked WHERE duplicate_order > 1"
            ")"
        )
    )

    op.create_index("ix_career_interviews_analysis_id", "career_interviews", ["analysis_id"])
    op.create_index("ix_career_interviews_cv_document_id", "career_interviews", ["cv_document_id"])
    op.create_index("ix_career_interviews_retry_of_id", "career_interviews", ["retry_of_id"])
    op.create_index(
        "uq_career_interviews_active_user",
        "career_interviews",
        ["user_id"],
        unique=True,
        postgresql_where=sa.text("status = 'active'"),
    )
    op.create_index(
        "uq_career_interview_answers_interview_question",
        "career_interview_answers",
        ["interview_id", "question_id"],
        unique=True,
    )


def downgrade() -> None:
    op.drop_index(
        "uq_career_interview_answers_interview_question",
        table_name="career_interview_answers",
    )
    op.drop_index("uq_career_interviews_active_user", table_name="career_interviews")
    op.drop_index("ix_career_interviews_retry_of_id", table_name="career_interviews")
    op.drop_index("ix_career_interviews_cv_document_id", table_name="career_interviews")
    op.drop_index("ix_career_interviews_analysis_id", table_name="career_interviews")
    with op.batch_alter_table("career_interviews") as batch_op:
        batch_op.drop_constraint("fk_career_interviews_retry_of_id", type_="foreignkey")
        batch_op.drop_constraint("fk_career_interviews_cv_document_id", type_="foreignkey")
        batch_op.drop_constraint("fk_career_interviews_analysis_id", type_="foreignkey")
        batch_op.drop_column("ended_at")
        batch_op.drop_column("retry_of_id")
        batch_op.drop_column("context_snapshot")
        batch_op.drop_column("cv_name_snapshot")
        batch_op.drop_column("cv_document_id")
        batch_op.drop_column("analysis_id")
