from datetime import timedelta

import pytest
from sqlalchemy import create_engine, func, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import sessionmaker
from sqlalchemy.pool import StaticPool

from app.core.database import Base
from app.celery_app import celery_app
from app.core.config import settings
from app.models.company_recruiting import CompanyTaskOutbox, RecruitingPositionAiAnalysis
from app.services.company_outbox import (
    POSITION_ANALYSIS_TASK,
    DELIVERY_LEASE,
    claim_dispatch_batch,
    claim_outbox_for_processing,
    dispatch_pending_outbox,
    enqueue_company_task,
    fail_outbox_claim_permanently,
    finalize_outbox_claim,
    utcnow,
)


@pytest.fixture()
def outbox_sessions():
    engine = create_engine(
        "sqlite://",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    Base.metadata.create_all(engine)
    return sessionmaker(bind=engine, expire_on_commit=False)


def _enqueue(
    db,
    aggregate_id: str,
    *,
    max_attempts: int = 5,
    organization_id: str = "org-outbox-test",
) -> CompanyTaskOutbox:
    return enqueue_company_task(
        db,
        organization_id=organization_id,
        task_name=POSITION_ANALYSIS_TASK,
        aggregate_type="position_ai_analysis",
        aggregate_id=aggregate_id,
        max_attempts=max_attempts,
    )


def test_outbox_is_atomic_with_caller_transaction_and_deduplicated(outbox_sessions):
    with outbox_sessions() as db:
        _enqueue(db, "analysis-rollback")
        db.rollback()
        assert db.scalar(select(func.count()).select_from(CompanyTaskOutbox)) == 0

        _enqueue(db, "analysis-commit")
        db.commit()
        assert db.scalar(select(func.count()).select_from(CompanyTaskOutbox)) == 1

        _enqueue(db, "analysis-commit")
        with pytest.raises(IntegrityError):
            db.commit()
        db.rollback()


def test_dispatcher_retries_broker_failure_without_losing_job(outbox_sessions):
    with outbox_sessions() as db:
        row = _enqueue(db, "analysis-retry", max_attempts=2)
        db.commit()
        row_id = row.id

    def unavailable(_task_name, _args, _task_id):
        raise ConnectionError("broker unavailable")

    result = dispatch_pending_outbox(session_factory=outbox_sessions, publisher=unavailable)
    assert result == {"claimed": 1, "published": 0, "failed": 1}
    with outbox_sessions() as db:
        row = db.get(CompanyTaskOutbox, row_id)
        assert row.status == "pending"
        assert row.attempt_count == 1
        assert row.available_at > row.created_at
        assert "broker unavailable" in row.last_error


def test_dispatcher_dead_letters_after_bounded_publish_attempts(outbox_sessions):
    with outbox_sessions() as db:
        row = _enqueue(db, "analysis-dead", max_attempts=1)
        db.commit()
        row_id = row.id

    result = dispatch_pending_outbox(
        session_factory=outbox_sessions,
        publisher=lambda *_args: (_ for _ in ()).throw(ConnectionError("still down")),
    )
    assert result["failed"] == 1
    with outbox_sessions() as db:
        row = db.get(CompanyTaskOutbox, row_id)
        assert row.status == "dead_letter"
        assert row.completed_at is not None


def test_duplicate_delivery_is_noop_and_stale_lease_is_recoverable(outbox_sessions):
    published = []
    with outbox_sessions() as db:
        row = _enqueue(db, "analysis-once")
        db.commit()
        row_id = row.id

    assert dispatch_pending_outbox(
        session_factory=outbox_sessions,
        publisher=lambda task, args, task_id: published.append((task, args, task_id)),
    )["published"] == 1
    assert published[0][0] == POSITION_ANALYSIS_TASK
    assert published[0][1] == ["analysis-once", row_id]

    with outbox_sessions() as db:
        first = claim_outbox_for_processing(
            db,
            outbox_id=row_id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id="analysis-once",
            organization_id="org-outbox-test",
        )
        assert first is not None
        assert claim_outbox_for_processing(
            db,
            outbox_id=row_id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id="analysis-once",
            organization_id="org-outbox-test",
        ) is None
        assert finalize_outbox_claim(
            db,
            outbox_id=row_id,
            lock_token="wrong-token",
            succeeded=True,
        ) is False
        assert finalize_outbox_claim(
            db,
            outbox_id=row_id,
            lock_token=first.lock_token,
            succeeded=True,
        ) is True
        db.commit()
        assert db.get(CompanyTaskOutbox, row_id).status == "succeeded"

    with outbox_sessions() as db:
        stale = _enqueue(db, "analysis-stale")
        db.commit()
        stale.status = "processing"
        stale.attempt_count = 1
        stale.lock_token = "abandoned-worker"
        stale.lease_until = utcnow() - timedelta(seconds=1)
        db.commit()
        claims = claim_dispatch_batch(db)
        assert [claim.aggregate_id for claim in claims] == ["analysis-stale"]
        assert claims[0].lock_token != "abandoned-worker"
        assert db.get(CompanyTaskOutbox, stale.id).attempt_count == 2


def test_stale_worker_is_fenced_after_lease_recovery(outbox_sessions):
    with outbox_sessions() as db:
        row = _enqueue(db, "analysis-fenced")
        db.commit()
        row_id = row.id
        dispatch = claim_dispatch_batch(db)[0]
        first_worker = claim_outbox_for_processing(
            db,
            outbox_id=row_id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id="analysis-fenced",
            organization_id="org-outbox-test",
        )
        assert first_worker is not None
        row = db.get(CompanyTaskOutbox, row_id)
        row.lease_until = utcnow() - timedelta(seconds=1)
        db.commit()

        redispatch = claim_dispatch_batch(db)[0]
        assert redispatch.celery_task_id != dispatch.celery_task_id
        second_worker = claim_outbox_for_processing(
            db,
            outbox_id=row_id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id="analysis-fenced",
            organization_id="org-outbox-test",
        )
        assert second_worker is not None
        assert finalize_outbox_claim(
            db,
            outbox_id=row_id,
            lock_token=first_worker.lock_token,
            succeeded=True,
        ) is False
        assert finalize_outbox_claim(
            db,
            outbox_id=row_id,
            lock_token=second_worker.lock_token,
            succeeded=True,
        ) is True
        db.commit()


def test_cross_tenant_delivery_is_dead_lettered(outbox_sessions):
    with outbox_sessions() as db:
        row = _enqueue(db, "analysis-cross-tenant", organization_id="org-b")
        db.commit()
        claim = claim_outbox_for_processing(
            db,
            outbox_id=row.id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id="analysis-cross-tenant",
            organization_id="org-a",
        )
        assert claim is None
        db.expire_all()
        stored = db.get(CompanyTaskOutbox, row.id)
        assert stored.status == "dead_letter"
        assert stored.last_error == "outbox_tenant_mismatch"


def test_retry_exhaustion_fails_outbox_and_tenant_matched_target(outbox_sessions):
    with outbox_sessions() as db:
        db.add(RecruitingPositionAiAnalysis(
            id="analysis-retry-exhausted",
            organization_id="org-outbox-test",
            position_id="position-retry-exhausted",
            criteria_version_id="criteria-retry-exhausted",
            status="queued",
            input_snapshot={},
            result={},
        ))
        row = _enqueue(db, "analysis-retry-exhausted")
        db.commit()
        claim = claim_outbox_for_processing(
            db,
            outbox_id=row.id,
            task_name=POSITION_ANALYSIS_TASK,
            aggregate_id="analysis-retry-exhausted",
            organization_id="org-outbox-test",
        )
        assert claim is not None
        outbox_id = row.id
        lock_token = claim.lock_token

    fail_outbox_claim_permanently(
        session_factory=outbox_sessions,
        outbox_id=outbox_id,
        lock_token=lock_token,
        error=TimeoutError("provider timed out repeatedly"),
    )
    with outbox_sessions() as db:
        assert db.get(CompanyTaskOutbox, outbox_id).status == "failed"
        target = db.get(RecruitingPositionAiAnalysis, "analysis-retry-exhausted")
        assert target.status == "failed"
        assert target.error_code == "ai_provider_retry_exhausted"


def test_company_tasks_and_periodic_dispatcher_are_registered():
    celery_app.loader.import_default_modules()
    assert {
        POSITION_ANALYSIS_TASK,
        "company.analyze_candidate_application",
        "company.dispatch_outbox",
    } <= set(celery_app.tasks)
    assert celery_app.tasks[POSITION_ANALYSIS_TASK].acks_late is True
    assert celery_app.tasks[POSITION_ANALYSIS_TASK].reject_on_worker_lost is True
    assert celery_app.conf.worker_prefetch_multiplier == 1
    assert celery_app.conf.beat_schedule["dispatch-company-task-outbox"]["schedule"] == 5.0
    worst_case_provider_window = (
        settings.AI_REQUEST_TIMEOUT_SECONDS
        * (settings.AI_PROVIDER_MAX_RETRIES + 1)
        * 2
    )
    assert DELIVERY_LEASE.total_seconds() > worst_case_provider_window + 60
