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

    assert script.get_heads() == ["20260720_13"]


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
