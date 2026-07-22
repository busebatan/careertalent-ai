"""Authenticated career-engine state and queue endpoints."""

from datetime import datetime, timezone
import json
import os
from pathlib import Path
import time
from typing import Annotated, Iterator
from uuid import uuid4

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from fastapi.responses import StreamingResponse
from sqlalchemy import delete, select
from sqlalchemy.orm import Session, sessionmaker

from app.core.config import settings
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence, JobOpportunity
from app.models.user import User
from app.models.engagement import JobApplication
from app.schemas.career import (
    CareerAnalysisResponse,
    CareerTargetRequest,
    CareerTargetResponse,
    CareerTaskResponse,
    CareerResetRequest,
    EvidenceCreateRequest,
    EvidenceResponse,
    JobAnalyzeRequest,
    JobApplyRequest,
    JobCvVersionRequest,
)
from app.schemas.cv import CandidateCvVersionResponse
from app.services.ai_factory import AIOutputError, AIProviderError, AIUnavailableError
from app.services.career_engine import (
    CareerLocalizationError,
    ensure_career_localizations,
    select_target,
    serialize_analysis,
    serialize_target,
    serialize_task,
    submit_evidence,
    reset_career_state,
)
from app.services.job_opportunity import (
    JobCvVersionError,
    JobQueueUnavailableError,
    QUEUE_UNAVAILABLE_CODE,
    QUEUE_UNAVAILABLE_MESSAGE,
    create_cv_version_for_job,
    create_job,
    cv_snapshot,
    dispatch_job_analysis,
    latest_analysis as latest_user_analysis,
    serialize_job,
)
from app.tasks.career import analyze_cv_task, analyze_job_task, apply_job_suggestions_task, plan_target_task, review_evidence_task

router = APIRouter(prefix="/career", tags=["Career Engine"], dependencies=[Depends(get_current_user)])
DB = Annotated[Session, Depends(get_db)]
CurrentUser = Annotated[User, Depends(get_current_user)]
ANALYSIS_STREAM_MAX_POLLS = 180
ANALYSIS_STREAM_POLL_SECONDS = 1.0


def _not_found() -> HTTPException:
    return HTTPException(status_code=404, detail="Kariyer kaydı bulunamadı")


def _format_sse(event: str, data: dict) -> str:
    return f"event: {event}\ndata: {json.dumps(data, ensure_ascii=False)}\n\n"


def _localized_locale(
    db: Session,
    user: User,
    *,
    analysis_id: str | None = None,
    target_id: str | None = None,
    include_analysis: bool = True,
    include_targets: bool = True,
) -> str:
    try:
        ensure_career_localizations(
            db,
            user.id,
            analysis_id=analysis_id,
            target_id=target_id,
            include_analysis=include_analysis,
            include_targets=include_targets,
        )
    except CareerLocalizationError as exc:
        raise HTTPException(
            status_code=503,
            detail={
                "code": "career_localization_failed",
                "message": "Kariyer içerikleri seçilen panel dilinde hazırlanamadı. Lütfen tekrar deneyin.",
            },
        ) from exc
    return user.preferred_locale


@router.post("/jobs/analyze", status_code=202)
def create_job_analysis(request: JobAnalyzeRequest, db: DB, user: CurrentUser):
    analysis = latest_user_analysis(db, user.id)
    if analysis is None or analysis.status != "ready":
        raise HTTPException(status_code=409, detail="İlan analizi için önce CV analizi tamamlanmalı")
    snapshot = cv_snapshot(analysis)
    row = create_job(db, user.id, request.source_url, request.job_text, analysis)
    try:
        dispatch_job_analysis(db, row, snapshot, analyze_job_task.delay)
    except JobQueueUnavailableError as exc:
        raise HTTPException(
            status_code=503,
            detail={
                "code": QUEUE_UNAVAILABLE_CODE,
                "message": QUEUE_UNAVAILABLE_MESSAGE,
            },
        ) from exc
    return serialize_job(row)


@router.get("/jobs")
def list_jobs(db: DB, user: CurrentUser):
    rows = db.scalars(select(JobOpportunity).where(JobOpportunity.user_id == user.id).order_by(JobOpportunity.created_at.desc())).all()
    applied_job_ids = set(db.scalars(select(JobApplication.job_id).where(JobApplication.user_id == user.id, JobApplication.job_id.is_not(None))).all())
    return [serialize_job(row) | {"application_created": row.id in applied_job_ids} for row in rows]


@router.get("/jobs/{job_id}")
def job_status(job_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(JobOpportunity).where(JobOpportunity.id == job_id, JobOpportunity.user_id == user.id))
    if row is None:
        raise _not_found()
    return serialize_job(row)


@router.post("/jobs/{job_id}/save")
def save_job(job_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(JobOpportunity).where(JobOpportunity.id == job_id, JobOpportunity.user_id == user.id))
    if row is None:
        raise _not_found()
    if row.status != "ready":
        raise HTTPException(status_code=409, detail="Yalnız tamamlanan ilan analizi kaydedilebilir")
    row.saved = True
    db.commit(); db.refresh(row)
    return serialize_job(row)


@router.delete("/jobs/{job_id}", status_code=204)
def delete_job(job_id: str, db: DB, user: CurrentUser):
    result = db.execute(delete(JobOpportunity).where(JobOpportunity.id == job_id, JobOpportunity.user_id == user.id))
    if not result.rowcount:
        raise _not_found()
    db.commit()


@router.post("/jobs/{job_id}/apply", status_code=202)
def apply_job(job_id: str, request: JobApplyRequest, db: DB, user: CurrentUser):
    row = db.scalar(select(JobOpportunity).where(JobOpportunity.id == job_id, JobOpportunity.user_id == user.id))
    if row is None:
        raise _not_found()
    indexed = {item.get("id"): item for item in (row.cv_suggestions or [])}
    selected = [indexed.get(item_id) for item_id in request.suggestion_ids]
    if row.status != "ready" or any(item is None or not item.get("safe_to_apply") for item in selected):
        raise HTTPException(status_code=422, detail="Yalnız güvenli CV önerileri uygulanabilir")

    # ── B2B Snapshots Integration ──────────────────────────────────────
    from datetime import timedelta
    from app.models.company_recruiting import RecruitingPosition, RecruitingApplication, RecruitingApplicationSnapshot
    from app.models.engagement import CandidateCvVersion
    
    position = db.scalar(select(RecruitingPosition).where(RecruitingPosition.id == job_id))
    if position is not None:
        existing_app = db.scalar(
            select(RecruitingApplication).where(
                RecruitingApplication.position_id == position.id,
                RecruitingApplication.candidate_user_id == user.id
            )
        )
        if not existing_app:
            payload_data = {}
            if request.cv_version_id:
                cv_ver = db.scalar(
                    select(CandidateCvVersion).where(
                        CandidateCvVersion.id == request.cv_version_id,
                        CandidateCvVersion.user_id == user.id
                    )
                )
                if cv_ver:
                    payload_data = cv_ver.payload
            
            if not payload_data:
                cv_ver = db.scalar(
                    select(CandidateCvVersion).where(
                        CandidateCvVersion.user_id == user.id,
                        CandidateCvVersion.is_main.is_(True)
                    )
                )
                if cv_ver:
                    payload_data = cv_ver.payload
                else:
                    latest_analysis = db.scalar(
                        select(CareerAnalysis)
                        .where(CareerAnalysis.user_id == user.id, CareerAnalysis.status == "ready")
                        .order_by(CareerAnalysis.created_at.desc())
                    )
                    if latest_analysis:
                        payload_data = {
                            "current_role": latest_analysis.current_role,
                            "profile": latest_analysis.profile,
                            "skills": latest_analysis.skills,
                            "radar": latest_analysis.radar
                        }
            
            app_id = str(uuid4())
            new_app = RecruitingApplication(
                id=app_id,
                organization_id=position.organization_id,
                position_id=position.id,
                candidate_user_id=user.id,
                candidate_name=user.full_name or "Aday",
                candidate_email=user.email,
                current_stage="new",
                applied_at=datetime.now(timezone.utc),
                retention_expires_at=datetime.now(timezone.utc) + timedelta(days=180),
            )
            db.add(new_app)
            db.flush()
            
            snapshot_id = str(uuid4())
            new_snapshot = RecruitingApplicationSnapshot(
                id=snapshot_id,
                application_id=app_id,
                schema_version=1,
                payload=payload_data,
                consent_scope="all",
                created_at=datetime.now(timezone.utc)
            )
            db.add(new_snapshot)
            db.commit()
    # ───────────────────────────────────────────────────────────────────

    row.apply_status = "queued"
    db.commit()
    apply_job_suggestions_task.delay(row.id, request.suggestion_ids)
    db.refresh(row)
    return serialize_job(row)


@router.post("/jobs/{job_id}/cv-version", response_model=CandidateCvVersionResponse, status_code=201)
def create_job_cv_version(job_id: str, request: JobCvVersionRequest, db: DB, user: CurrentUser):
    row = db.scalar(select(JobOpportunity).where(JobOpportunity.id == job_id, JobOpportunity.user_id == user.id))
    if row is None:
        raise _not_found()
    try:
        return create_cv_version_for_job(
            db,
            row,
            request.suggestion_ids,
            request.source_cv_version_id,
            user.preferred_locale,
        )
    except JobCvVersionError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc



@router.get("/analysis/current", response_model=CareerAnalysisResponse | None)
def current_analysis(db: DB, user: CurrentUser):
    row = db.scalar(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id == user.id, CareerAnalysis.status == "ready")
        .order_by(CareerAnalysis.created_at.desc())
    )
    return serialize_analysis(row, _localized_locale(db, user, include_targets=False)) if row else None


@router.get("/analysis/latest", response_model=CareerAnalysisResponse | None)
def latest_analysis(db: DB, user: CurrentUser):
    row = latest_user_analysis(db, user.id)
    if row is None:
        return None
    locale = _localized_locale(
        db,
        user,
        analysis_id=row.id,
        include_targets=False,
    ) if row.status == "ready" else user.preferred_locale
    return serialize_analysis(row, locale)


@router.get("/analysis/{analysis_id}", response_model=CareerAnalysisResponse)
def analysis_status(analysis_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CareerAnalysis).where(CareerAnalysis.id == analysis_id, CareerAnalysis.user_id == user.id))
    if row is None:
        raise _not_found()
    locale = _localized_locale(
        db,
        user,
        analysis_id=row.id,
        include_targets=False,
    ) if row.status == "ready" else user.preferred_locale
    return serialize_analysis(row, locale)


@router.get("/analysis/{analysis_id}/stream")
def analysis_stream(analysis_id: str, db: DB, user: CurrentUser) -> StreamingResponse:
    stream_session_factory = sessionmaker(
        bind=db.get_bind(),
        autocommit=False,
        autoflush=False,
    )
    user_id = user.id

    def generate() -> Iterator[str]:
        with stream_session_factory() as stream_db:
            for attempt in range(ANALYSIS_STREAM_MAX_POLLS):
                stream_db.expire_all()
                row = stream_db.scalar(
                    select(CareerAnalysis).where(
                        CareerAnalysis.id == analysis_id,
                        CareerAnalysis.user_id == user_id,
                    )
                )
                if row is None:
                    yield _format_sse("error", {"message": "Kariyer kaydı bulunamadı"})
                    return

                stream_user = stream_db.get(User, user_id)
                if stream_user is None:
                    yield _format_sse("error", {"message": "Kullanıcı bulunamadı"})
                    return

                if row.status == "ready":
                    locale = _localized_locale(
                        stream_db,
                        stream_user,
                        analysis_id=row.id,
                        include_targets=False,
                    )
                    yield _format_sse("complete", serialize_analysis(row, locale))
                    return

                if row.status == "failed":
                    yield _format_sse("failed", serialize_analysis(row, stream_user.preferred_locale))
                    return

                yield _format_sse("status", {"id": row.id, "status": row.status})
                if attempt < ANALYSIS_STREAM_MAX_POLLS - 1:
                    time.sleep(ANALYSIS_STREAM_POLL_SECONDS)

            yield _format_sse(
                "timeout",
                {
                    "message": "CV analizi hâlâ sürüyor. Durumu kontrol etmek için sayfayı yenile.",
                },
            )

    return StreamingResponse(
        generate(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no",
        },
    )


@router.post("/targets", response_model=CareerTargetResponse, status_code=202)
def create_target(request: CareerTargetRequest, db: DB, user: CurrentUser):
    target = select_target(db, user.id, request.title, request.source, request.job_url)
    plan_target_task.delay(target.id)
    db.refresh(target)
    return serialize_target(target, user.preferred_locale)


@router.get("/targets", response_model=list[CareerTargetResponse])
def list_targets(db: DB, user: CurrentUser):
    locale = _localized_locale(db, user, include_analysis=False)
    rows = db.scalars(select(CareerTarget).where(CareerTarget.user_id == user.id).order_by(CareerTarget.created_at.desc())).all()
    return [serialize_target(row, locale) for row in rows]


@router.get("/targets/{target_id}", response_model=CareerTargetResponse)
def target_status(target_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id, CareerTarget.user_id == user.id))
    if row is None:
        raise _not_found()
    return serialize_target(row, _localized_locale(db, user, target_id=row.id, include_analysis=False))


@router.get("/targets/{target_id}/tasks", response_model=list[CareerTaskResponse])
def list_tasks(target_id: str, db: DB, user: CurrentUser):
    target = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id, CareerTarget.user_id == user.id))
    if target is None:
        raise _not_found()
    locale = _localized_locale(db, user, target_id=target.id, include_analysis=False)
    rows = db.scalars(select(CareerTask).where(CareerTask.target_id == target_id, CareerTask.user_id == user.id).order_by(CareerTask.created_at)).all()
    return [serialize_task(row, db, locale) for row in rows]


@router.get("/tasks/{task_id}", response_model=CareerTaskResponse)
def task_status(task_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CareerTask).where(CareerTask.id == task_id, CareerTask.user_id == user.id))
    if row is None:
        raise _not_found()
    return serialize_task(
        row,
        db,
        _localized_locale(db, user, target_id=row.target_id, include_analysis=False),
    )


@router.post("/tasks/{task_id}/evidence", response_model=EvidenceResponse, status_code=201)
def add_link_evidence(task_id: str, request: EvidenceCreateRequest, db: DB, user: CurrentUser):
    task = db.scalar(select(CareerTask).where(CareerTask.id == task_id, CareerTask.user_id == user.id))
    if task is None or request.kind != "link":
        raise _not_found()
    evidence = submit_evidence(db, user.id, task, request.kind, request.url, None)
    review_evidence_task.delay(evidence.id)
    return _serialize_evidence(evidence)


@router.post("/tasks/{task_id}/evidence/upload", response_model=EvidenceResponse, status_code=201)
async def add_file_evidence(task_id: str, db: DB, user: CurrentUser, file: UploadFile = File(...)):
    task = db.scalar(select(CareerTask).where(CareerTask.id == task_id, CareerTask.user_id == user.id))
    if task is None:
        raise _not_found()
    if file.content_type not in {"application/pdf", "image/png", "image/jpeg"}:
        raise HTTPException(status_code=422, detail="Yalnızca PDF, PNG veya JPEG kanıt kabul edilir")
    data = await file.read()
    if len(data) > settings.MAX_UPLOAD_SIZE_MB * 1024 * 1024:
        raise HTTPException(status_code=413, detail="Dosya çok büyük")
    safe_name = Path(file.filename or "evidence.bin").name
    path = Path(settings.UPLOAD_DIR) / str(user.id) / (str(uuid4()) + "-" + safe_name)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)
    os.chmod(path, 0o600)
    evidence = submit_evidence(db, user.id, task, "file", None, str(path))
    review_evidence_task.delay(evidence.id)
    return _serialize_evidence(evidence)


@router.get("/evidence/{evidence_id}", response_model=EvidenceResponse)
def evidence_status(evidence_id: str, db: DB, user: CurrentUser):
    evidence = db.scalar(select(Evidence).where(Evidence.id == evidence_id, Evidence.user_id == user.id))
    if evidence is None:
        raise _not_found()
    task = db.scalar(select(CareerTask).where(CareerTask.id == evidence.task_id, CareerTask.user_id == user.id))
    if task is None:
        raise _not_found()
    locale = _localized_locale(db, user, target_id=task.target_id, include_analysis=False)
    response = _serialize_evidence(evidence)
    response["feedback"] = None if evidence.status == "pending" else serialize_task(task, db, locale)["feedback"]
    return response


@router.post("/reset")
def reset_state(request: CareerResetRequest, db: DB, user: CurrentUser):
    return {"status": "cleared", "scope": request.scope, "deleted": reset_career_state(db, user.id, request.scope)}


def _serialize_evidence(row: Evidence) -> dict:
    return {"id": row.id, "task_id": row.task_id, "status": row.status, "confidence": row.confidence, "feedback": row.feedback}
