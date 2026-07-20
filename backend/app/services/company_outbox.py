"""Transactional outbox delivery for company AI jobs."""

from __future__ import annotations

from dataclasses import dataclass
from datetime import UTC, datetime, timedelta
from typing import Callable
from uuid import uuid4

from sqlalchemy import and_, or_, select
from sqlalchemy.orm import Session, sessionmaker

from app.core.database import SessionLocal
from app.models.company_recruiting import (
    CompanyTaskOutbox,
    RecruitingApplication,
    RecruitingPositionAiAnalysis,
)


POSITION_ANALYSIS_TASK = "company.analyze_position"
CANDIDATE_ANALYSIS_TASK = "company.analyze_candidate_application"
ALLOWED_TASKS = {POSITION_ANALYSIS_TASK, CANDIDATE_ANALYSIS_TASK}

DISPATCH_LEASE = timedelta(seconds=60)
DELIVERY_LEASE = timedelta(minutes=20)
DEFAULT_BATCH_SIZE = 25


@dataclass(frozen=True)
class DispatchClaim:
    id: str
    task_name: str
    aggregate_id: str
    lock_token: str
    celery_task_id: str
    attempt_count: int


@dataclass(frozen=True)
class ProcessingClaim:
    id: str
    aggregate_id: str
    lock_token: str


def utcnow() -> datetime:
    return datetime.now(UTC)


def _aware(value: datetime | None) -> datetime | None:
    if value is None or value.tzinfo is not None:
        return value
    return value.replace(tzinfo=UTC)


def enqueue_company_task(
    db: Session,
    *,
    organization_id: str,
    task_name: str,
    aggregate_type: str,
    aggregate_id: str,
    max_attempts: int = 5,
) -> CompanyTaskOutbox:
    """Add a durable job in the caller's domain transaction."""
    if task_name not in ALLOWED_TASKS:
        raise ValueError(f"Unsupported company task: {task_name}")
    row = CompanyTaskOutbox(
        id=str(uuid4()),
        organization_id=organization_id,
        task_name=task_name,
        aggregate_type=aggregate_type,
        aggregate_id=aggregate_id,
        dedupe_key=f"{task_name}:{aggregate_id}",
        payload={"schema_version": 1, "aggregate_id": aggregate_id},
        status="pending",
        attempt_count=0,
        max_attempts=max_attempts,
        available_at=utcnow(),
    )
    db.add(row)
    return row


def _mark_delivery_exhausted(db: Session, row: CompanyTaskOutbox, now: datetime) -> None:
    row.status = "dead_letter"
    row.completed_at = now
    row.lease_until = None
    row.lock_token = None
    row.last_error = row.last_error or "outbox_delivery_exhausted"
    if row.task_name == POSITION_ANALYSIS_TASK:
        target = db.scalar(select(RecruitingPositionAiAnalysis).where(
            RecruitingPositionAiAnalysis.id == row.aggregate_id,
            RecruitingPositionAiAnalysis.organization_id == row.organization_id,
        ))
        if target is not None and target.status in {"queued", "processing"}:
            target.status = "failed"
            target.error_code = "queue_delivery_exhausted"
            target.error_message = "AI job could not be delivered to the worker"
            target.completed_at = now
    elif row.task_name == CANDIDATE_ANALYSIS_TASK:
        target = db.scalar(select(RecruitingApplication).where(
            RecruitingApplication.id == row.aggregate_id,
            RecruitingApplication.organization_id == row.organization_id,
        ))
        if target is not None and target.analysis_status in {"queued", "processing"}:
            target.analysis_status = "failed"
            target.analysis_result = {"error_code": "queue_delivery_exhausted"}


def claim_dispatch_batch(
    db: Session,
    *,
    limit: int = DEFAULT_BATCH_SIZE,
    now: datetime | None = None,
) -> list[DispatchClaim]:
    """Lease pending or stale jobs without holding a lock during broker I/O."""
    now = now or utcnow()
    eligible = or_(
        and_(CompanyTaskOutbox.status == "pending", CompanyTaskOutbox.available_at <= now),
        and_(
            CompanyTaskOutbox.status.in_({"dispatching", "dispatched", "processing"}),
            CompanyTaskOutbox.lease_until.is_not(None),
            CompanyTaskOutbox.lease_until <= now,
        ),
    )
    rows = list(
        db.scalars(
            select(CompanyTaskOutbox)
            .where(eligible)
            .order_by(CompanyTaskOutbox.available_at, CompanyTaskOutbox.id)
            .with_for_update(skip_locked=True)
            .limit(limit)
        ).all()
    )
    claims: list[DispatchClaim] = []
    for row in rows:
        if row.attempt_count >= row.max_attempts:
            _mark_delivery_exhausted(db, row, now)
            continue
        row.attempt_count += 1
        token = str(uuid4())
        celery_task_id = f"outbox:{row.id}:{row.attempt_count}"
        row.status = "dispatching"
        row.lock_token = token
        row.celery_task_id = celery_task_id
        row.lease_until = now + DISPATCH_LEASE
        row.last_error = None
        claims.append(
            DispatchClaim(
                id=row.id,
                task_name=row.task_name,
                aggregate_id=str(row.aggregate_id),
                lock_token=token,
                celery_task_id=celery_task_id,
                attempt_count=row.attempt_count,
            )
        )
    db.commit()
    return claims


def _retry_delay(attempt_count: int) -> timedelta:
    return timedelta(seconds=min(300, 2 ** min(max(attempt_count, 1), 8)))


def _mark_publish_success(
    db: Session,
    claim: DispatchClaim,
    *,
    now: datetime | None = None,
) -> None:
    now = now or utcnow()
    row = db.get(CompanyTaskOutbox, claim.id)
    if row is None:
        return
    row.published_at = row.published_at or now
    row.celery_task_id = claim.celery_task_id
    if row.status == "dispatching" and row.lock_token == claim.lock_token:
        row.status = "dispatched"
        row.lock_token = None
        row.lease_until = now + DELIVERY_LEASE
    db.commit()


def _mark_publish_failure(
    db: Session,
    claim: DispatchClaim,
    error: Exception,
    *,
    now: datetime | None = None,
) -> None:
    now = now or utcnow()
    row = db.get(CompanyTaskOutbox, claim.id)
    if row is None or row.status != "dispatching" or row.lock_token != claim.lock_token:
        return
    row.last_error = str(error)[:2000]
    row.lease_until = None
    row.lock_token = None
    if row.attempt_count >= row.max_attempts:
        _mark_delivery_exhausted(db, row, now)
    else:
        row.status = "pending"
        row.available_at = now + _retry_delay(row.attempt_count)
    db.commit()


Publisher = Callable[[str, list[str], str], object]


def dispatch_pending_outbox(
    *,
    session_factory: sessionmaker = SessionLocal,
    publisher: Publisher | None = None,
    limit: int = DEFAULT_BATCH_SIZE,
) -> dict[str, int]:
    """Publish one leased batch; safe to run from multiple beat/worker nodes."""
    if publisher is None:
        from app.celery_app import celery_app

        publisher = lambda task_name, args, task_id: celery_app.send_task(
            task_name,
            args=args,
            task_id=task_id,
        )

    with session_factory() as db:
        claims = claim_dispatch_batch(db, limit=limit)

    published = failed = 0
    for claim in claims:
        try:
            publisher(claim.task_name, [claim.aggregate_id, claim.id], claim.celery_task_id)
        except Exception as exc:
            with session_factory() as db:
                _mark_publish_failure(db, claim, exc)
            failed += 1
        else:
            with session_factory() as db:
                _mark_publish_success(db, claim)
            published += 1
    return {"claimed": len(claims), "published": published, "failed": failed}


def claim_outbox_for_processing(
    db: Session,
    *,
    outbox_id: str,
    task_name: str,
    aggregate_id: str,
    organization_id: str,
    now: datetime | None = None,
) -> ProcessingClaim | None:
    """Claim a delivery once; duplicate deliveries become no-ops."""
    now = now or utcnow()
    row = db.scalar(
        select(CompanyTaskOutbox)
        .where(CompanyTaskOutbox.id == outbox_id)
        .with_for_update()
    )
    if row is None:
        return None
    if row.task_name != task_name or row.aggregate_id != aggregate_id:
        raise ValueError("Outbox task/aggregate mismatch")
    if row.organization_id != organization_id:
        row.status = "dead_letter"
        row.last_error = "outbox_tenant_mismatch"
        row.completed_at = now
        row.lease_until = None
        row.lock_token = None
        db.commit()
        return None
    if row.status in {"succeeded", "failed", "dead_letter"}:
        return None
    if row.status == "processing" and (_aware(row.lease_until) or now) > now:
        return None
    token = str(uuid4())
    row.status = "processing"
    row.lock_token = token
    row.lease_until = now + DELIVERY_LEASE
    row.started_at = row.started_at or now
    db.commit()
    return ProcessingClaim(id=row.id, aggregate_id=aggregate_id, lock_token=token)


def finalize_outbox_claim(
    db: Session,
    *,
    outbox_id: str,
    lock_token: str,
    succeeded: bool,
    error: str | None = None,
    now: datetime | None = None,
) -> bool:
    """Finalize inside the same transaction as the domain result write."""
    now = now or utcnow()
    row = db.scalar(
        select(CompanyTaskOutbox)
        .where(CompanyTaskOutbox.id == outbox_id)
        .with_for_update()
    )
    if row is None or row.status != "processing" or row.lock_token != lock_token:
        return False
    row.status = "succeeded" if succeeded else "failed"
    row.last_error = error[:2000] if error else None
    row.completed_at = now
    row.lease_until = None
    row.lock_token = None
    return True


def fail_outbox_target_missing(
    db: Session,
    *,
    outbox_id: str,
    task_name: str,
    aggregate_id: str,
) -> None:
    """Terminally fail a delivery whose domain aggregate no longer exists."""
    row = db.scalar(
        select(CompanyTaskOutbox)
        .where(CompanyTaskOutbox.id == outbox_id)
        .with_for_update()
    )
    if row is None or row.status in {"succeeded", "failed", "dead_letter"}:
        return
    if row.task_name != task_name or row.aggregate_id != aggregate_id:
        raise ValueError("Outbox task/aggregate mismatch")
    row.status = "failed"
    row.last_error = "outbox_target_missing"
    row.completed_at = utcnow()
    row.lease_until = None
    row.lock_token = None
    db.commit()


def release_outbox_claim(
    *,
    session_factory: sessionmaker = SessionLocal,
    outbox_id: str,
    lock_token: str,
    error: Exception,
) -> None:
    """Return an unexpected worker failure to the dispatcher retry loop."""
    now = utcnow()
    with session_factory() as db:
        row = db.scalar(
            select(CompanyTaskOutbox)
            .where(CompanyTaskOutbox.id == outbox_id)
            .with_for_update()
        )
        if row is None or row.status != "processing" or row.lock_token != lock_token:
            return
        row.last_error = str(error)[:2000]
        row.status = "pending"
        row.available_at = now + _retry_delay(row.attempt_count)
        row.lease_until = None
        row.lock_token = None
        db.commit()


def fail_outbox_claim_permanently(
    *,
    session_factory: sessionmaker = SessionLocal,
    outbox_id: str,
    lock_token: str,
    error: Exception,
) -> None:
    """Fail the outbox and its tenant-matched domain status after retry exhaustion."""
    now = utcnow()
    with session_factory() as db:
        row = db.scalar(
            select(CompanyTaskOutbox)
            .where(CompanyTaskOutbox.id == outbox_id)
            .with_for_update()
        )
        if row is None or row.status != "processing" or row.lock_token != lock_token:
            return
        row.status = "failed"
        row.last_error = str(error)[:2000]
        row.completed_at = now
        row.lease_until = None
        row.lock_token = None
        if row.task_name == POSITION_ANALYSIS_TASK:
            target = db.scalar(select(RecruitingPositionAiAnalysis).where(
                RecruitingPositionAiAnalysis.id == row.aggregate_id,
                RecruitingPositionAiAnalysis.organization_id == row.organization_id,
            ))
            if target is not None and target.status in {"queued", "processing"}:
                target.status = "failed"
                target.error_code = "ai_provider_retry_exhausted"
                target.error_message = str(error)[:500]
                target.completed_at = now
        elif row.task_name == CANDIDATE_ANALYSIS_TASK:
            target = db.scalar(select(RecruitingApplication).where(
                RecruitingApplication.id == row.aggregate_id,
                RecruitingApplication.organization_id == row.organization_id,
            ))
            if target is not None and target.analysis_status in {"queued", "processing"}:
                target.analysis_status = "failed"
                target.analysis_result = {
                    "error_code": "ai_provider_retry_exhausted",
                    "message": str(error)[:500],
                }
        db.commit()
