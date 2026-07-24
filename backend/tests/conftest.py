from collections.abc import Generator

import pytest
from alembic import command
from alembic.config import Config
from fastapi.testclient import TestClient
from sqlalchemy.engine import Engine
from sqlalchemy.orm import Session, sessionmaker

from app import models  # noqa: F401
from app.core.database import Base, get_db
from app.main import app
from tests.db_support import (
    TransactionalMigrationEngine,
    create_postgres_test_engine,
    reset_postgres_test_sequences,
)


@pytest.fixture(scope="session")
def postgres_engine() -> Generator[Engine, None, None]:
    engine = create_postgres_test_engine()
    config = Config("alembic.ini")
    config.set_main_option("script_location", "migrations")
    with engine.begin() as connection:
        Base.metadata.create_all(connection)
        config.attributes["connection"] = connection
        command.stamp(config, "head")
    try:
        yield engine
    finally:
        engine.dispose()


@pytest.fixture()
def postgres_migration_engine(postgres_engine: Engine) -> TransactionalMigrationEngine:
    return TransactionalMigrationEngine(postgres_engine)


@pytest.fixture()
def client(postgres_engine: Engine) -> Generator[TestClient, None, None]:
    connection = postgres_engine.connect()
    reset_postgres_test_sequences(connection)
    connection.commit()
    transaction = connection.begin()
    testing_session = sessionmaker(
        bind=connection,
        join_transaction_mode="create_savepoint",
    )

    def override_db() -> Generator[Session, None, None]:
        db = testing_session()
        try:
            yield db
        finally:
            db.close()

    app.dependency_overrides[get_db] = override_db
    with TestClient(app) as test_client:
        yield test_client
    app.dependency_overrides.clear()
    transaction.rollback()
    connection.close()
