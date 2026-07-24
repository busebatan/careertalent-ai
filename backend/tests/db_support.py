"""PostgreSQL test database helpers."""

from contextlib import contextmanager
from uuid import uuid4

from sqlalchemy import create_engine
from sqlalchemy.engine import Connection, Engine, make_url

from app.core.config import settings


TEST_DATABASE_URL = settings.TEST_DATABASE_URL


def create_postgres_test_engine() -> Engine:
    url = make_url(TEST_DATABASE_URL)
    if url.get_backend_name() != "postgresql" or not (url.database or "").endswith("_test"):
        raise RuntimeError("TEST_DATABASE_URL ayrı bir PostgreSQL *_test veritabanını göstermelidir")
    return create_engine(TEST_DATABASE_URL, pool_pre_ping=True)


def reset_postgres_test_sequences(connection: Connection) -> None:
    sequence_names = connection.exec_driver_sql(
        "SELECT sequencename FROM pg_sequences WHERE schemaname = current_schema()"
    ).scalars()
    for sequence_name in sequence_names:
        connection.exec_driver_sql(
            "SELECT setval(%s::regclass, 1, false)",
            (sequence_name,),
        )


class TransactionalMigrationEngine:
    def __init__(self, engine: Engine):
        self.engine = engine

    @contextmanager
    def begin(self):
        with self.engine.connect() as connection:
            transaction = connection.begin()
            schema = f"migration_test_{uuid4().hex}"
            connection.exec_driver_sql(f'CREATE SCHEMA "{schema}"')
            connection.exec_driver_sql(f'SET LOCAL search_path TO "{schema}"')
            try:
                yield connection
            finally:
                transaction.rollback()
