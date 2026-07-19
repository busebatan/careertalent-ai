"""Reapply engagement foreign-key delete rules after revision drift."""

from alembic import op
import sqlalchemy as sa


revision = "20260719_08"
down_revision = "20260717_07"
branch_labels = None
depends_on = None


_FOREIGN_KEYS = (
    (
        "personal_tasks",
        "personal_tasks_target_id_fkey",
        "target_id",
        "career_targets",
        "id",
    ),
    (
        "job_applications",
        "job_applications_job_id_fkey",
        "job_id",
        "job_opportunities",
        "id",
    ),
)


def _uses_set_null(constraint: str) -> bool:
    return bool(
        op.get_bind().scalar(
            sa.text(
                "SELECT confdeltype = 'n' "
                "FROM pg_constraint "
                "WHERE conname = :constraint"
            ),
            {"constraint": constraint},
        )
    )


def _replace_foreign_key(
    table: str,
    constraint: str,
    local_column: str,
    remote_table: str,
    remote_column: str,
    *,
    ondelete: str | None,
) -> None:
    op.drop_constraint(constraint, table, type_="foreignkey")
    op.create_foreign_key(
        constraint,
        table,
        remote_table,
        [local_column],
        [remote_column],
        ondelete=ondelete,
    )


def upgrade() -> None:
    if op.get_bind().dialect.name != "postgresql":
        return
    for table, constraint, local_column, remote_table, remote_column in _FOREIGN_KEYS:
        if not _uses_set_null(constraint):
            _replace_foreign_key(
                table,
                constraint,
                local_column,
                remote_table,
                remote_column,
                ondelete="SET NULL",
            )


def downgrade() -> None:
    if op.get_bind().dialect.name != "postgresql":
        return
    for table, constraint, local_column, remote_table, remote_column in reversed(_FOREIGN_KEYS):
        _replace_foreign_key(
            table,
            constraint,
            local_column,
            remote_table,
            remote_column,
            ondelete=None,
        )
