"""Gerçek kullanıcı profili, AI sohbet/mülakat, kişisel görev ve başvuru API'si."""

import os
from pathlib import Path
from typing import Annotated
from uuid import uuid4

from fastapi import APIRouter, Depends, File, Form, HTTPException, Response, UploadFile
from sqlalchemy import delete, select
from sqlalchemy.orm import Session
from pydantic import BaseModel

from app.core.config import settings
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.career_engine import CareerTarget, CareerTask, Evidence, JobOpportunity
from app.models.engagement import CareerChatMessage, CareerInterview, CareerInterviewAnswer, CvDocument, JobApplication, PersonalTask, UserProfile
from app.models.user import User
from app.schemas.engagement import ApplicationCreate, ApplicationUpdate, CareerTaskStatusUpdate, ChatRequest, InterviewAnswerRequest, PersonalTaskCreate, PersonalTaskUpdate, ProfileUpdate, SkillEvidenceLinkRequest, TaskNoteUpdate
from app.services.engagement import answer_chat, evaluate_interview_answer, serialize_answer, serialize_chat, serialize_interview, start_interview
from app.services.career_engine import (
    CareerLocalizationError,
    clear_skill_evidence,
    ensure_career_localizations,
    ensure_skill_evidence_task,
    serialize_task,
    submit_evidence,
)
from app.services.ai_factory import AIOutputError, AIProviderError, AIUnavailableError
from app.tasks.career import review_evidence_task

router = APIRouter(prefix="/career", tags=["Career Engagement"], dependencies=[Depends(get_current_user)])
DB = Annotated[Session, Depends(get_db)]
CurrentUser = Annotated[User, Depends(get_current_user)]


class InterviewStartRequest(BaseModel):
    language: str = "tr"


def _ensure_localized_target(db: Session, user: User, target_id: str) -> None:
    try:
        ensure_career_localizations(
            db,
            user.id,
            target_id=target_id,
            include_analysis=False,
        )
    except CareerLocalizationError as exc:
        raise HTTPException(
            status_code=503,
            detail={
                "code": "career_localization_failed",
                "message": "Kariyer içerikleri seçilen panel dilinde hazırlanamadı. Lütfen tekrar deneyin.",
            },
        ) from exc


@router.get("/profile")
def get_profile(db: DB, user: CurrentUser):
    return _serialize_profile(user, db.get(UserProfile, user.id), _current_cv(db, user.id))


@router.put("/profile")
def update_profile(body: ProfileUpdate, db: DB, user: CurrentUser):
    profile = db.get(UserProfile, user.id) or UserProfile(user_id=user.id)
    user.full_name = body.full_name
    for field in ("phone", "location", "headline", "linkedin"):
        setattr(profile, field, getattr(body, field))
    profile.social_links = [item.model_dump() for item in body.social_links]
    db.add(profile); db.commit(); db.refresh(profile)
    return _serialize_profile(user, profile, _current_cv(db, user.id))


@router.get("/chat")
def chat_history(db: DB, user: CurrentUser):
    rows = db.scalars(select(CareerChatMessage).where(CareerChatMessage.user_id == user.id).order_by(CareerChatMessage.created_at)).all()
    return [serialize_chat(row) for row in rows]


@router.post("/chat", status_code=201)
def send_chat(body: ChatRequest, db: DB, user: CurrentUser):
    try:
        return serialize_chat(answer_chat(db, user.id, body.message))
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@router.delete("/chat", status_code=204)
def clear_chat(db: DB, user: CurrentUser):
    db.execute(delete(CareerChatMessage).where(CareerChatMessage.user_id == user.id)); db.commit()
    return Response(status_code=204)


@router.get("/interviews/current")
def current_interview(db: DB, user: CurrentUser):
    row = db.scalar(select(CareerInterview).where(CareerInterview.user_id == user.id).order_by(CareerInterview.created_at.desc()))
    if row is None:
        return None
    answers = db.scalars(select(CareerInterviewAnswer).where(CareerInterviewAnswer.interview_id == row.id, CareerInterviewAnswer.user_id == user.id).order_by(CareerInterviewAnswer.created_at)).all()
    return serialize_interview(row, list(answers))


@router.post("/interviews", status_code=201)
def create_interview(request: InterviewStartRequest, db: DB, user: CurrentUser):
    try:
        return serialize_interview(start_interview(db, user.id, language=request.language))
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc


@router.post("/interviews/{interview_id}/answers", status_code=201)
def score_interview_answer(interview_id: str, body: InterviewAnswerRequest, db: DB, user: CurrentUser):
    interview = db.scalar(select(CareerInterview).where(CareerInterview.id == interview_id, CareerInterview.user_id == user.id))
    if interview is None:
        raise HTTPException(status_code=404, detail="Mülakat bulunamadı")
    try:
        return serialize_answer(evaluate_interview_answer(db, user.id, interview, body.question_id, body.answer))
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


@router.get("/personal-tasks")
def personal_tasks(db: DB, user: CurrentUser, target_id: str | None = None):
    query = select(PersonalTask).where(PersonalTask.user_id == user.id)
    if target_id:
        query = query.where(PersonalTask.target_id == target_id)
    return [_serialize_personal_task(row) for row in db.scalars(query.order_by(PersonalTask.created_at)).all()]


@router.patch("/tasks/{task_id}/note")
def update_ai_task_note(task_id: str, body: TaskNoteUpdate, db: DB, user: CurrentUser):
    row = db.scalar(select(CareerTask).where(CareerTask.id == task_id, CareerTask.user_id == user.id))
    if row is None: raise HTTPException(status_code=404, detail="Görev bulunamadı")
    row.note = body.note; db.commit(); db.refresh(row)
    return {"id": row.id, "note": row.note or ""}


@router.patch("/tasks/{task_id}")
def update_ai_task_status(task_id: str, body: CareerTaskStatusUpdate, db: DB, user: CurrentUser):
    row = db.scalar(select(CareerTask).where(CareerTask.id == task_id, CareerTask.user_id == user.id))
    if row is None:
        raise HTTPException(status_code=404, detail="Görev bulunamadı")
    _ensure_localized_target(db, user, row.target_id)
    row.status = body.status
    db.commit()
    db.refresh(row)
    return serialize_task(row, db, user.preferred_locale)


@router.post("/skill-evidence/link", status_code=201)
def add_skill_link_evidence(body: SkillEvidenceLinkRequest, db: DB, user: CurrentUser):
    if db.scalar(select(CareerTarget.id).where(CareerTarget.id == body.target_id, CareerTarget.user_id == user.id)) is None:
        raise HTTPException(status_code=404, detail="Hedef bulunamadı")
    _ensure_localized_target(db, user, body.target_id)
    try:
        task = ensure_skill_evidence_task(db, user.id, body.target_id, body.skill)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc
    evidence = submit_evidence(db, user.id, task, "link", body.url, None)
    review_evidence_task.delay(evidence.id)
    db.refresh(task)
    return {"skill": body.skill, "task": serialize_task(task, db, user.preferred_locale), "evidence": _serialize_evidence(evidence)}


@router.post("/skill-evidence/upload", status_code=201)
async def add_skill_file_evidence(db: DB, user: CurrentUser, skill: str = Form(...), target_id: str = Form(...), file: UploadFile = File(...)):
    if db.scalar(select(CareerTarget.id).where(CareerTarget.id == target_id, CareerTarget.user_id == user.id)) is None:
        raise HTTPException(status_code=404, detail="Hedef bulunamadı")
    _ensure_localized_target(db, user, target_id)
    if file.content_type not in {"application/pdf", "image/png", "image/jpeg"}:
        raise HTTPException(status_code=422, detail="Yalnızca PDF, PNG veya JPEG kanıt kabul edilir")
    data = await file.read()
    if len(data) > settings.MAX_UPLOAD_SIZE_MB * 1024 * 1024:
        raise HTTPException(status_code=413, detail="Dosya çok büyük")
    try:
        task = ensure_skill_evidence_task(db, user.id, target_id, skill)
    except ValueError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc
    safe_name = Path(file.filename or "evidence.bin").name
    path = Path(settings.UPLOAD_DIR) / str(user.id) / (str(uuid4()) + "-" + safe_name)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)
    os.chmod(path, 0o600)
    evidence = submit_evidence(db, user.id, task, "file", None, str(path))
    review_evidence_task.delay(evidence.id)
    db.refresh(task)
    return {"skill": skill, "task": serialize_task(task, db, user.preferred_locale), "evidence": _serialize_evidence(evidence)}


@router.delete("/skill-evidence", status_code=200)
def remove_skill_evidence(skill: str, target_id: str, db: DB, user: CurrentUser):
    if db.scalar(select(CareerTarget.id).where(CareerTarget.id == target_id, CareerTarget.user_id == user.id)) is None:
        raise HTTPException(status_code=404, detail="Hedef bulunamadı")
    _ensure_localized_target(db, user, target_id)
    task = clear_skill_evidence(db, user.id, target_id, skill)
    if task is None:
        return {"skill": skill, "task": None}
    return {"skill": skill, "task": serialize_task(task, db, user.preferred_locale)}


def _serialize_evidence(row: Evidence) -> dict:
    return {
        "id": row.id,
        "task_id": row.task_id,
        "status": row.status,
        "confidence": row.confidence,
        "feedback": row.feedback,
        "kind": row.kind,
        "url": row.url,
    }


@router.post("/personal-tasks", status_code=201)
def create_personal_task(body: PersonalTaskCreate, db: DB, user: CurrentUser):
    if body.target_id and db.scalar(select(CareerTarget.id).where(CareerTarget.id == body.target_id, CareerTarget.user_id == user.id)) is None:
        raise HTTPException(status_code=404, detail="Hedef bulunamadı")
    row = PersonalTask(id=str(uuid4()), user_id=user.id, target_id=body.target_id, title=body.title)
    db.add(row); db.commit(); db.refresh(row)
    return _serialize_personal_task(row)


@router.patch("/personal-tasks/{task_id}")
def update_personal_task(task_id: str, body: PersonalTaskUpdate, db: DB, user: CurrentUser):
    row = db.scalar(select(PersonalTask).where(PersonalTask.id == task_id, PersonalTask.user_id == user.id))
    if row is None:
        raise HTTPException(status_code=404, detail="Kişisel görev bulunamadı")
    for field, value in body.model_dump(exclude_unset=True).items(): setattr(row, field, value)
    db.commit(); db.refresh(row)
    return _serialize_personal_task(row)


@router.delete("/personal-tasks/{task_id}", status_code=204)
def delete_personal_task(task_id: str, db: DB, user: CurrentUser):
    result = db.execute(delete(PersonalTask).where(PersonalTask.id == task_id, PersonalTask.user_id == user.id))
    if not result.rowcount: raise HTTPException(status_code=404, detail="Kişisel görev bulunamadı")
    db.commit(); return Response(status_code=204)


@router.get("/applications")
def applications(db: DB, user: CurrentUser):
    return [_serialize_application(row) for row in db.scalars(select(JobApplication).where(JobApplication.user_id == user.id).order_by(JobApplication.applied_at.desc())).all()]


@router.post("/applications", status_code=201)
def create_application(body: ApplicationCreate, db: DB, user: CurrentUser):
    row = JobApplication(id=str(uuid4()), user_id=user.id, company=body.company, role=body.role, next_action=body.next_action)
    db.add(row); db.commit(); db.refresh(row)
    return _serialize_application(row)


@router.post("/jobs/{job_id}/application", status_code=201)
def create_job_application(job_id: str, db: DB, user: CurrentUser):
    job = db.scalar(select(JobOpportunity).where(JobOpportunity.id == job_id, JobOpportunity.user_id == user.id, JobOpportunity.saved.is_(True)))
    if job is None: raise HTTPException(status_code=404, detail="Önce ilanı kaydetmelisiniz")
    existing = db.scalar(select(JobApplication).where(JobApplication.user_id == user.id, JobApplication.job_id == job.id))
    if existing: return _serialize_application(existing)
    row = JobApplication(id=str(uuid4()), user_id=user.id, job_id=job.id, company=job.company or job.source or "Şirket", role=job.title or "Pozisyon", next_action="Takip tarihi belirle")
    db.add(row); db.commit(); db.refresh(row)
    return _serialize_application(row)


@router.patch("/applications/{application_id}")
def update_application(application_id: str, body: ApplicationUpdate, db: DB, user: CurrentUser):
    row = db.scalar(select(JobApplication).where(JobApplication.id == application_id, JobApplication.user_id == user.id))
    if row is None: raise HTTPException(status_code=404, detail="Başvuru bulunamadı")
    for field, value in body.model_dump(exclude_unset=True).items(): setattr(row, field, value)
    db.commit(); db.refresh(row)
    return _serialize_application(row)


def _current_cv(db: Session, user_id: int) -> CvDocument | None:
    return db.scalar(select(CvDocument).where(CvDocument.user_id == user_id, CvDocument.kind == "uploaded", CvDocument.is_current.is_(True)).order_by(CvDocument.created_at.desc()))


def _serialize_profile(user: User, row: UserProfile | None, current_cv: CvDocument | None = None) -> dict:
    return {"full_name": user.full_name, "email": user.email, "phone": row.phone if row else None, "location": row.location if row else None, "headline": row.headline if row else None, "linkedin": row.linkedin if row else None, "social_links": row.social_links if row else [], "uploaded_cv": None if current_cv is None else {"id": current_cv.id, "name": current_cv.display_name, "uploaded_at": current_cv.created_at.isoformat() if current_cv.created_at else None}}


def _serialize_personal_task(row: PersonalTask) -> dict:
    return {"id": row.id, "target_id": row.target_id, "title": row.title, "note": row.note or "", "completed": row.completed, "source": "custom", "status": "completed" if row.completed else "personal"}


def _serialize_application(row: JobApplication) -> dict:
    return {"id": row.id, "job_id": row.job_id, "company": row.company, "role": row.role, "stage": row.stage, "next_action": row.next_action or "", "note": row.note or "", "applied_at": row.applied_at.isoformat() if row.applied_at else None}