"""Add career chat threads.

Revision ID: 20260720_16
Revises: 400e5d7dfba4
"""

from uuid import uuid4

from alembic import op
import sqlalchemy as sa


revision = "20260720_16"
down_revision = "400e5d7dfba4"
branch_labels = None
depends_on = None


def _title(value: str | None) -> str:
    normalized = " ".join((value or "").split()) or "Yeni sohbet"
    return normalized[:157] + "..." if len(normalized) > 160 else normalized


def upgrade() -> None:
    op.create_table(
        "career_chat_threads",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("user_id", sa.Integer(), nullable=False),
        sa.Column("title", sa.String(160), nullable=False, server_default="Yeni sohbet"),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default=sa.true()),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.ForeignKeyConstraint(["user_id"], ["users.id"], ondelete="CASCADE"),
    )
    op.create_index("ix_career_chat_threads_user_id", "career_chat_threads", ["user_id"])
    op.create_index("ix_career_chat_threads_is_active", "career_chat_threads", ["is_active"])
    op.create_index(
        "uq_career_chat_threads_active_user",
        "career_chat_threads",
        ["user_id"],
        unique=True,
        postgresql_where=sa.text("is_active = true"),
        sqlite_where=sa.text("is_active = 1"),
    )

    with op.batch_alter_table("career_chat_messages") as batch_op:
        batch_op.add_column(sa.Column("thread_id", sa.String(36), nullable=True))
        batch_op.create_index("ix_career_chat_messages_thread_id", ["thread_id"])
        batch_op.create_foreign_key(
            "fk_career_chat_messages_thread_id",
            "career_chat_threads",
            ["thread_id"],
            ["id"],
            ondelete="CASCADE",
        )

    bind = op.get_bind()
    users = bind.execute(sa.text("SELECT DISTINCT user_id FROM career_chat_messages")).scalars().all()
    for user_id in users:
        first_message = bind.execute(
            sa.text(
                "SELECT content FROM career_chat_messages "
                "WHERE user_id = :user_id AND role = 'user' ORDER BY created_at, id LIMIT 1"
            ),
            {"user_id": user_id},
        ).scalar_one_or_none()
        thread_id = str(uuid4())
        bind.execute(
            sa.text(
                "INSERT INTO career_chat_threads (id, user_id, title, is_active) "
                "VALUES (:id, :user_id, :title, :is_active)"
            ),
            {"id": thread_id, "user_id": user_id, "title": _title(first_message), "is_active": True},
        )
        bind.execute(
            sa.text("UPDATE career_chat_messages SET thread_id = :thread_id WHERE user_id = :user_id"),
            {"thread_id": thread_id, "user_id": user_id},
        )


def downgrade() -> None:
    with op.batch_alter_table("career_chat_messages") as batch_op:
        batch_op.drop_constraint("fk_career_chat_messages_thread_id", type_="foreignkey")
        batch_op.drop_index("ix_career_chat_messages_thread_id")
        batch_op.drop_column("thread_id")
    op.drop_index("uq_career_chat_threads_active_user", table_name="career_chat_threads")
    op.drop_index("ix_career_chat_threads_is_active", table_name="career_chat_threads")
    op.drop_index("ix_career_chat_threads_user_id", table_name="career_chat_threads")
    op.drop_table("career_chat_threads")
