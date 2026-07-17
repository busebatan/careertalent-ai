"""Hesap bazlı çekirdek etkileşim tabloları.

Revision ID: 20260713_01
Revises: None
"""
from alembic import op
import sqlalchemy as sa

revision = "20260713_01"
down_revision = None
branch_labels = None
depends_on = None


def _table_exists(name: str) -> bool:
    return sa.inspect(op.get_bind()).has_table(name)


def _column_exists(table: str, column: str) -> bool:
    return any(item["name"] == column for item in sa.inspect(op.get_bind()).get_columns(table))


def _index_exists(table: str, index: str) -> bool:
    return any(item["name"] == index for item in sa.inspect(op.get_bind()).get_indexes(table))


def _create_index_if_missing(name: str, table: str, columns: list[str]) -> None:
    if not _index_exists(table, name):
        op.create_index(name, table, columns)


def upgrade() -> None:
    if not _column_exists("career_tasks", "note"):
        op.add_column("career_tasks", sa.Column("note", sa.Text(), nullable=True))
    if not _table_exists("user_profiles"):
        op.create_table("user_profiles", sa.Column("user_id", sa.Integer(), nullable=False), sa.Column("phone", sa.String(40)), sa.Column("location", sa.String(160)), sa.Column("headline", sa.String(240)), sa.Column("linkedin", sa.String(2048)), sa.Column("social_links", sa.JSON(), nullable=False), sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.ForeignKeyConstraint(["user_id"], ["users.id"]), sa.PrimaryKeyConstraint("user_id"))
    if not _table_exists("career_chat_messages"):
        op.create_table("career_chat_messages", sa.Column("id", sa.String(36), primary_key=True), sa.Column("user_id", sa.Integer(), nullable=False), sa.Column("role", sa.String(20), nullable=False), sa.Column("content", sa.Text(), nullable=False), sa.Column("meta", sa.JSON(), nullable=False), sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.ForeignKeyConstraint(["user_id"], ["users.id"]))
    _create_index_if_missing("ix_career_chat_messages_user_id", "career_chat_messages", ["user_id"]); _create_index_if_missing("ix_career_chat_messages_created_at", "career_chat_messages", ["created_at"])
    if not _table_exists("career_interviews"):
        op.create_table("career_interviews", sa.Column("id", sa.String(36), primary_key=True), sa.Column("user_id", sa.Integer(), nullable=False), sa.Column("target_role", sa.String(160), nullable=False), sa.Column("status", sa.String(24), nullable=False), sa.Column("questions", sa.JSON(), nullable=False), sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.ForeignKeyConstraint(["user_id"], ["users.id"]))
    _create_index_if_missing("ix_career_interviews_user_id", "career_interviews", ["user_id"])
    if not _table_exists("career_interview_answers"):
        op.create_table("career_interview_answers", sa.Column("id", sa.String(36), primary_key=True), sa.Column("interview_id", sa.String(36), nullable=False), sa.Column("user_id", sa.Integer(), nullable=False), sa.Column("question_id", sa.String(80), nullable=False), sa.Column("answer", sa.Text(), nullable=False), sa.Column("score", sa.Integer(), nullable=False), sa.Column("feedback", sa.Text(), nullable=False), sa.Column("strengths", sa.JSON(), nullable=False), sa.Column("improvements", sa.JSON(), nullable=False), sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.ForeignKeyConstraint(["interview_id"], ["career_interviews.id"]), sa.ForeignKeyConstraint(["user_id"], ["users.id"]))
    _create_index_if_missing("ix_career_interview_answers_interview_id", "career_interview_answers", ["interview_id"]); _create_index_if_missing("ix_career_interview_answers_user_id", "career_interview_answers", ["user_id"])
    if not _table_exists("personal_tasks"):
        op.create_table("personal_tasks", sa.Column("id", sa.String(36), primary_key=True), sa.Column("user_id", sa.Integer(), nullable=False), sa.Column("target_id", sa.String(36)), sa.Column("title", sa.String(240), nullable=False), sa.Column("note", sa.Text()), sa.Column("completed", sa.Boolean(), nullable=False), sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.ForeignKeyConstraint(["target_id"], ["career_targets.id"], ondelete="SET NULL"), sa.ForeignKeyConstraint(["user_id"], ["users.id"]))
    _create_index_if_missing("ix_personal_tasks_user_id", "personal_tasks", ["user_id"]); _create_index_if_missing("ix_personal_tasks_target_id", "personal_tasks", ["target_id"])
    if not _table_exists("job_applications"):
        op.create_table("job_applications", sa.Column("id", sa.String(36), primary_key=True), sa.Column("user_id", sa.Integer(), nullable=False), sa.Column("job_id", sa.String(36)), sa.Column("company", sa.String(160), nullable=False), sa.Column("role", sa.String(200), nullable=False), sa.Column("stage", sa.String(30), nullable=False), sa.Column("next_action", sa.String(300)), sa.Column("note", sa.Text()), sa.Column("applied_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.Column("updated_at", sa.DateTime(timezone=True), server_default=sa.func.now(), nullable=False), sa.ForeignKeyConstraint(["job_id"], ["job_opportunities.id"], ondelete="SET NULL"), sa.ForeignKeyConstraint(["user_id"], ["users.id"]))
    _create_index_if_missing("ix_job_applications_user_id", "job_applications", ["user_id"]); _create_index_if_missing("ix_job_applications_job_id", "job_applications", ["job_id"]); _create_index_if_missing("ix_job_applications_stage", "job_applications", ["stage"])


def downgrade() -> None:
    for table in ("job_applications", "personal_tasks", "career_interview_answers", "career_interviews", "career_chat_messages", "user_profiles"):
        op.drop_table(table)
    op.drop_column("career_tasks", "note")
