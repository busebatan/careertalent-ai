import json
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path

import pytest
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

    assert script.get_heads() == ["20260721_18"]


def test_job_analysis_provenance_migration_adds_nullable_snapshot_columns(tmp_path) -> None:
    backend_dir = Path(__file__).resolve().parents[1]
    path = backend_dir / "migrations/versions/20260721_18_job_analysis_provenance.py"
    spec = spec_from_file_location("job_analysis_provenance_migration", path)
    assert spec is not None and spec.loader is not None
    migration = module_from_spec(spec)
    spec.loader.exec_module(migration)
    engine = sa.create_engine(f"sqlite:///{tmp_path / 'job-analysis-provenance.sqlite'}")

    with engine.begin() as connection:
        connection.exec_driver_sql("CREATE TABLE job_opportunities (id VARCHAR(36) PRIMARY KEY)")
        migration.op = Operations(MigrationContext.configure(connection))
        migration.upgrade()

        columns = {item["name"]: item for item in sa.inspect(connection).get_columns("job_opportunities")}
        assert columns["source_analysis_id"]["nullable"] is True
        assert columns["source_analysis_id"]["type"].length == 36
        assert columns["source_cv_file_name"]["nullable"] is True
        assert columns["source_cv_file_name"]["type"].length == 255


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


def test_interview_lifecycle_migration_archives_active_rows_and_deduplicates_answers(tmp_path) -> None:
    backend_dir = Path(__file__).resolve().parents[1]
    path = backend_dir / "migrations/versions/20260721_17_interview_lifecycle.py"
    spec = spec_from_file_location("interview_lifecycle_migration", path)
    assert spec is not None and spec.loader is not None
    migration = module_from_spec(spec)
    spec.loader.exec_module(migration)
    engine = sa.create_engine(f"sqlite:///{tmp_path / 'interview-migration.sqlite'}")

    with engine.begin() as connection:
        connection.exec_driver_sql("CREATE TABLE users (id INTEGER PRIMARY KEY)")
        connection.exec_driver_sql("CREATE TABLE cv_documents (id VARCHAR(36) PRIMARY KEY)")
        connection.exec_driver_sql("CREATE TABLE career_analyses (id VARCHAR(36) PRIMARY KEY)")
        connection.exec_driver_sql(
            "CREATE TABLE career_interviews ("
            "id VARCHAR(36) PRIMARY KEY, user_id INTEGER NOT NULL, target_role VARCHAR(160) NOT NULL, "
            "status VARCHAR(24) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'tr', questions JSON NOT NULL, "
            "created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, "
            "FOREIGN KEY(user_id) REFERENCES users(id))"
        )
        connection.exec_driver_sql(
            "CREATE TABLE career_interview_answers ("
            "id VARCHAR(36) PRIMARY KEY, interview_id VARCHAR(36) NOT NULL, user_id INTEGER NOT NULL, "
            "question_id VARCHAR(80) NOT NULL, answer TEXT NOT NULL, score INTEGER NOT NULL, "
            "feedback TEXT NOT NULL, strengths JSON NOT NULL, improvements JSON NOT NULL, "
            "created_at DATETIME NOT NULL, FOREIGN KEY(interview_id) REFERENCES career_interviews(id), "
            "FOREIGN KEY(user_id) REFERENCES users(id))"
        )
        connection.exec_driver_sql("INSERT INTO users (id) VALUES (7)")
        connection.exec_driver_sql(
            "INSERT INTO career_interviews "
            "(id, user_id, target_role, status, language, questions, created_at, updated_at) VALUES "
            "('i1', 7, 'Data Analyst', 'active', 'tr', '[]', '2026-07-20 20:00:00', '2026-07-20 20:01:00'), "
            "('i2', 7, 'Data Analyst', 'active', 'tr', '[]', '2026-07-20 21:00:00', '2026-07-20 21:01:00')"
        )
        connection.exec_driver_sql(
            "INSERT INTO career_interview_answers "
            "(id, interview_id, user_id, question_id, answer, score, feedback, strengths, improvements, created_at) VALUES "
            "('a1', 'i1', 7, 'q1', 'old answer', 40, 'old', '[]', '[]', '2026-07-20 20:02:00'), "
            "('a2', 'i1', 7, 'q1', 'new answer', 80, 'new', '[]', '[]', '2026-07-20 20:03:00')"
        )
        migration.op = Operations(MigrationContext.configure(connection))

        migration.upgrade()

        interviews = connection.exec_driver_sql(
            "SELECT id, status, ended_at FROM career_interviews ORDER BY id"
        ).all()
        assert [(row.id, row.status) for row in interviews] == [("i1", "archived"), ("i2", "archived")]
        assert all(row.ended_at is not None for row in interviews)
        assert connection.exec_driver_sql(
            "SELECT id, answer, score FROM career_interview_answers"
        ).one() == ("a2", "new answer", 80)

        interview_columns = {item["name"] for item in sa.inspect(connection).get_columns("career_interviews")}
        assert {
            "analysis_id", "cv_document_id", "cv_name_snapshot", "context_snapshot",
            "retry_of_id", "ended_at",
        }.issubset(interview_columns)
        assert "uq_career_interviews_active_user" in {
            item["name"] for item in sa.inspect(connection).get_indexes("career_interviews")
        }
        assert "uq_career_interview_answers_interview_question" in {
            item["name"] for item in sa.inspect(connection).get_indexes("career_interview_answers")
        }

        connection.exec_driver_sql(
            "INSERT INTO career_interviews "
            "(id, user_id, target_role, status, language, questions, context_snapshot, created_at, updated_at) "
            "VALUES ('i3', 7, 'Data Analyst', 'active', 'tr', '[]', '{}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        )
        with pytest.raises(sa.exc.IntegrityError), connection.begin_nested():
            connection.exec_driver_sql(
                "INSERT INTO career_interviews "
                "(id, user_id, target_role, status, language, questions, context_snapshot, created_at, updated_at) "
                "VALUES ('i4', 7, 'Data Analyst', 'active', 'tr', '[]', '{}', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            )
        with pytest.raises(sa.exc.IntegrityError), connection.begin_nested():
            connection.exec_driver_sql(
                "INSERT INTO career_interview_answers "
                "(id, interview_id, user_id, question_id, answer, score, feedback, strengths, improvements, created_at) "
                "VALUES ('a3', 'i1', 7, 'q1', 'duplicate', 50, 'duplicate', '[]', '[]', CURRENT_TIMESTAMP)"
            )
