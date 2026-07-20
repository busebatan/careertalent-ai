"""Reliable Celery consumers and dispatcher for company recruiting AI."""

from __future__ import annotations

import logging

from app.celery_app import celery_app
from app.core.database import SessionLocal
from app.models.company_recruiting import RecruitingApplication, RecruitingPositionAiAnalysis
from app.services.company_outbox import (
    CANDIDATE_ANALYSIS_TASK,
    POSITION_ANALYSIS_TASK,
    claim_outbox_for_processing,
    dispatch_pending_outbox,
    fail_outbox_claim_permanently,
    fail_outbox_target_missing,
    finalize_outbox_claim,
    release_outbox_claim,
)
from app.services.company_positions import analyze_candidate_application, analyze_position


logger = logging.getLogger(__name__)


def _retry_countdown(retries: int) -> int:
    return min(300, 2 ** min(retries + 1, 8))


@celery_app.task(
    bind=True,
    name=POSITION_ANALYSIS_TASK,
    acks_late=True,
    reject_on_worker_lost=True,
    max_retries=5,
)
def analyze_position_task(self, analysis_id: str, outbox_id: str | None = None) -> str:
    claim = None
    try:
        with SessionLocal() as db:
            row = db.get(RecruitingPositionAiAnalysis, analysis_id)
            if row is None:
                if outbox_id:
                    fail_outbox_target_missing(
                        db,
                        outbox_id=outbox_id,
                        task_name=POSITION_ANALYSIS_TASK,
                        aggregate_id=analysis_id,
                    )
                return analysis_id
            if outbox_id:
                claim = claim_outbox_for_processing(
                    db,
                    outbox_id=outbox_id,
                    task_name=POSITION_ANALYSIS_TASK,
                    aggregate_id=analysis_id,
                    organization_id=row.organization_id,
                )
                if claim is None:
                    return analysis_id
                db.refresh(row)
                if row.status in {"completed", "failed"}:
                    if finalize_outbox_claim(
                        db,
                        outbox_id=claim.id,
                        lock_token=claim.lock_token,
                        succeeded=row.status == "completed",
                        error=row.error_message,
                    ):
                        db.commit()
                    return analysis_id
            analyze_position(
                db,
                row,
                outbox_id=claim.id if claim else None,
                outbox_lock_token=claim.lock_token if claim else None,
            )
            return analysis_id
    except Exception as exc:
        if claim:
            if self.request.retries >= self.max_retries:
                fail_outbox_claim_permanently(
                    outbox_id=claim.id,
                    lock_token=claim.lock_token,
                    error=exc,
                )
                return analysis_id
            try:
                release_outbox_claim(outbox_id=claim.id, lock_token=claim.lock_token, error=exc)
            except Exception:
                logger.exception("Could not release position analysis outbox claim", extra={"outbox_id": claim.id})
        raise self.retry(exc=exc, countdown=_retry_countdown(self.request.retries))


@celery_app.task(
    bind=True,
    name=CANDIDATE_ANALYSIS_TASK,
    acks_late=True,
    reject_on_worker_lost=True,
    max_retries=5,
)
def analyze_candidate_application_task(self, application_id: str, outbox_id: str | None = None) -> str:
    claim = None
    try:
        with SessionLocal() as db:
            row = db.get(RecruitingApplication, application_id)
            if row is None:
                if outbox_id:
                    fail_outbox_target_missing(
                        db,
                        outbox_id=outbox_id,
                        task_name=CANDIDATE_ANALYSIS_TASK,
                        aggregate_id=application_id,
                    )
                return application_id
            if outbox_id:
                claim = claim_outbox_for_processing(
                    db,
                    outbox_id=outbox_id,
                    task_name=CANDIDATE_ANALYSIS_TASK,
                    aggregate_id=application_id,
                    organization_id=row.organization_id,
                )
                if claim is None:
                    return application_id
                db.refresh(row)
                if row.analysis_status in {"completed", "failed"}:
                    error = (row.analysis_result or {}).get("message") or (row.analysis_result or {}).get("error_code")
                    if finalize_outbox_claim(
                        db,
                        outbox_id=claim.id,
                        lock_token=claim.lock_token,
                        succeeded=row.analysis_status == "completed",
                        error=error,
                    ):
                        db.commit()
                    return application_id
            analyze_candidate_application(
                db,
                row,
                outbox_id=claim.id if claim else None,
                outbox_lock_token=claim.lock_token if claim else None,
            )
            return application_id
    except Exception as exc:
        if claim:
            if self.request.retries >= self.max_retries:
                fail_outbox_claim_permanently(
                    outbox_id=claim.id,
                    lock_token=claim.lock_token,
                    error=exc,
                )
                return application_id
            try:
                release_outbox_claim(outbox_id=claim.id, lock_token=claim.lock_token, error=exc)
            except Exception:
                logger.exception("Could not release candidate analysis outbox claim", extra={"outbox_id": claim.id})
        raise self.retry(exc=exc, countdown=_retry_countdown(self.request.retries))


@celery_app.task(name="company.dispatch_outbox")
def dispatch_company_outbox_task() -> dict[str, int]:
    return dispatch_pending_outbox()
