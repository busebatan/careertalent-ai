"""Authenticated career-engine state and queue endpoints."""

from datetime import datetime, timezone
import os
from pathlib import Path
from typing import Annotated
from uuid import uuid4

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence
from app.models.user import User
from app.schemas.career import (
    CareerAnalysisResponse,
    CareerTargetRequest,
    CareerTargetResponse,
    CareerTaskResponse,
    CareerResetRequest,
    EvidenceCreateRequest,
    EvidenceResponse,
)
from app.services.career_engine import (
    create_analysis,
    select_target,
    serialize_analysis,
    serialize_target,
    serialize_task,
    submit_evidence,
    reset_career_state,
)
from app.tasks.career import analyze_cv_task, plan_target_task, review_evidence_task

router = APIRouter(prefix="/career", tags=["Career Engine"], dependencies=[Depends(get_current_user)])
DB = Annotated[Session, Depends(get_db)]
CurrentUser = Annotated[User, Depends(get_current_user)]


def _not_found() -> HTTPException:
    return HTTPException(status_code=404, detail="Kariyer kaydı bulunamadı")


@router.get("/analysis/current", response_model=CareerAnalysisResponse | None)
def current_analysis(db: DB, user: CurrentUser):
    row = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == user.id).order_by(CareerAnalysis.created_at.desc()))
    return serialize_analysis(row) if row else None


@router.get("/analysis/{analysis_id}", response_model=CareerAnalysisResponse)
def analysis_status(analysis_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CareerAnalysis).where(CareerAnalysis.id == analysis_id, CareerAnalysis.user_id == user.id))
    if row is None:
        raise _not_found()
    return serialize_analysis(row)


@router.post("/targets", response_model=CareerTargetResponse, status_code=202)
def create_target(request: CareerTargetRequest, db: DB, user: CurrentUser):
    target = select_target(db, user.id, request.title, request.source, request.job_url)
    plan_target_task.delay(target.id)
    db.refresh(target)
    return serialize_target(target)


@router.get("/targets", response_model=list[CareerTargetResponse])
def list_targets(db: DB, user: CurrentUser):
    rows = db.scalars(select(CareerTarget).where(CareerTarget.user_id == user.id).order_by(CareerTarget.created_at.desc())).all()
    return [serialize_target(row) for row in rows]


@router.get("/targets/{target_id}/tasks", response_model=list[CareerTaskResponse])
def list_tasks(target_id: str, db: DB, user: CurrentUser):
    target = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id, CareerTarget.user_id == user.id))
    if target is None:
        raise _not_found()
    rows = db.scalars(select(CareerTask).where(CareerTask.target_id == target_id, CareerTask.user_id == user.id).order_by(CareerTask.created_at)).all()
    return [serialize_task(row) for row in rows]


@router.get("/tasks/{task_id}", response_model=CareerTaskResponse)
def task_status(task_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CareerTask).where(CareerTask.id == task_id, CareerTask.user_id == user.id))
    if row is None:
        raise _not_found()
    return serialize_task(row)


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
    return _serialize_evidence(evidence)


@router.post("/reset")
def reset_state(request: CareerResetRequest, db: DB, user: CurrentUser):
    return {"status": "cleared", "scope": request.scope, "deleted": reset_career_state(db, user.id, request.scope)}


def _serialize_evidence(row: Evidence) -> dict:
    return {"id": row.id, "task_id": row.task_id, "status": row.status, "confidence": row.confidence, "feedback": row.feedback}
