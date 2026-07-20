import json
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path

import sqlalchemy as sa
from alembic.config import Config
from alembic.migration import MigrationContext
from alembic.operations import Operations
from alembic.script import ScriptDirectory


def test_migration_graph_has_one_unambiguous_head() -> None:
    backend_dir = Path(__file__).resolve().parents[1]
    config = Config(str(backend_dir / "alembic.ini"))
    config.set_main_option("script_location", str(backend_dir / "migrations"))

    script = ScriptDirectory.from_config(config)

    assert script.get_heads() == ["20260720_16"]


def test_company_permission_migration_backfills_existing_role_behavior() -> None:
    backend_dir = Path(__file__).resolve().parents[1]
    path = backend_dir / "migrations/versions/20260719_12_company_membership_permissions.py"
    spec = spec_from_file_location("company_permission_migration", path)
    assert spec is not None and spec.loader is not None
    migration = module_from_spec(spec)
    spec.loader.exec_module(migration)
    engine = sa.create_engine("sqlite://")

    with engine.begin() as connection:
        for table in ("organization_memberships", "organization_invitations"):
            connection.exec_driver_sql(
                f"CREATE TABLE {table} (id VARCHAR(36) PRIMARY KEY, role VARCHAR(24) NOT NULL)"
            )
            connection.exec_driver_sql(
                f"INSERT INTO {table} (id, role) VALUES "
                "('owner', 'owner'), ('admin', 'admin'), ('viewer', 'viewer')"
            )
        migration.op = Operations(MigrationContext.configure(connection))
        migration.upgrade()

        for table in ("organization_memberships", "organization_invitations"):
            rows = dict(
                connection.exec_driver_sql(
                    f"SELECT role, permissions FROM {table} ORDER BY role"
                ).all()
            )
            assert json.loads(rows["owner"]) == migration._ALL
            assert json.loads(rows["admin"]) == migration._ALL
            assert json.loads(rows["viewer"]) == migration._MEMBERS_VIEW


def test_chat_thread_migration_backfills_existing_messages(tmp_path) -> None:
    backend_dir = Path(__file__).resolve().parents[1]
    path = backend_dir / "migrations/versions/20260720_16_chat_threads.py"
    spec = spec_from_file_location("chat_thread_migration", path)
    assert spec is not None and spec.loader is not None
    migration = module_from_spec(spec)
    spec.loader.exec_module(migration)
    engine = sa.create_engine(f"sqlite:///{tmp_path / 'chat-migration.sqlite'}")

    with engine.begin() as connection:
        connection.exec_driver_sql("CREATE TABLE users (id INTEGER PRIMARY KEY)")
        connection.exec_driver_sql(
            "CREATE TABLE career_chat_messages ("
            "id VARCHAR(36) PRIMARY KEY, user_id INTEGER NOT NULL, role VARCHAR(20) NOT NULL, "
            "content TEXT NOT NULL, meta JSON NOT NULL, created_at DATETIME NOT NULL, "
            "FOREIGN KEY(user_id) REFERENCES users(id))"
        )
        connection.exec_driver_sql("INSERT INTO users (id) VALUES (7)")
        connection.exec_driver_sql(
            "INSERT INTO career_chat_messages (id, user_id, role, content, meta, created_at) VALUES "
            "('m1', 7, 'user', 'SQL kariyer planım', '{}', '2026-07-20 20:00:00'), "
            "('m2', 7, 'assistant', 'İlk adım', '{}', '2026-07-20 20:00:01')"
        )
        migration.op = Operations(MigrationContext.configure(connection))
        migration.upgrade()

        thread = connection.exec_driver_sql(
            "SELECT id, title, is_active FROM career_chat_threads WHERE user_id = 7"
        ).one()
        message_threads = connection.exec_driver_sql(
            "SELECT DISTINCT thread_id FROM career_chat_messages WHERE user_id = 7"
        ).scalars().all()
        assert thread.title == "SQL kariyer planım"
        assert thread.is_active in (1, True)
        assert message_threads == [thread.id]
        assert "uq_career_chat_threads_active_user" in {
            item["name"] for item in sa.inspect(connection).get_indexes("career_chat_threads")
        }
