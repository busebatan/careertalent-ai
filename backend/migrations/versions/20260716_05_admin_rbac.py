"""Admin rolleri, modül izinleri ve ilk giriş parola zorunluluğu."""

from alembic import op
import sqlalchemy as sa


revision = "20260716_05"
down_revision = "20260714_04"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.add_column("users", sa.Column("role", sa.String(length=24), server_default="student", nullable=False))
    op.add_column("users", sa.Column("admin_permissions", sa.JSON(), server_default=sa.text("'[]'"), nullable=False))
    op.add_column("users", sa.Column("must_change_password", sa.Boolean(), server_default=sa.false(), nullable=False))
    op.add_column("users", sa.Column("token_version", sa.Integer(), server_default="0", nullable=False))
    op.create_index("ix_users_role", "users", ["role"])
    op.execute("UPDATE users SET role = 'super_admin' WHERE is_admin = TRUE")


def downgrade() -> None:
    op.drop_index("ix_users_role", table_name="users")
    op.drop_column("users", "must_change_password")
    op.drop_column("users", "token_version")
    op.drop_column("users", "admin_permissions")
    op.drop_column("users", "role")
