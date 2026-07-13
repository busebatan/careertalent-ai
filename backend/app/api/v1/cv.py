"""Authenticated CV intake; analysis is always queued through Celery."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, File, HTTPException, UploadFile
from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.user import User
from app.schemas.career import CVQueueResponse
from app.schemas.cv import AnalyzeTextRequest
from app.services.career_engine import create_analysis
from app.services.cv_parser import extract_text_from_pdf
from app.tasks.career import analyze_cv_task

router = APIRouter()
DB = Annotated[Session, Depends(get_db)]
CurrentUser = Annotated[User, Depends(get_current_user)]
_MAX_BYTES = settings.MAX_UPLOAD_SIZE_MB * 1024 * 1024


def _queue(db: DB, user: CurrentUser, cv_text: str, source: str, file_name: str) -> CVQueueResponse:
    row = create_analysis(db, user.id, cv_text, source, file_name)
    analyze_cv_task.delay(row.id)
    return {"analysis_id": row.id, "status": "queued"}


@router.post("/analyze-text", response_model=CVQueueResponse, status_code=202)
async def analyze_cv_text(body: AnalyzeTextRequest, db: DB, user: CurrentUser):
    cv_text = body.cv_text.strip()
    if len(cv_text) < 40:
        raise HTTPException(status_code=422, detail="CV metni çok kısa")
    file_name = (body.file_name or "cv-builder.json").strip() or "cv-builder.json"
    return _queue(db, user, cv_text, "text", file_name)


@router.post("/analyze", response_model=CVQueueResponse, status_code=202)
async def analyze_cv(db: DB, user: CurrentUser, file: UploadFile = File(...)):
    if not file.filename:
        raise HTTPException(status_code=422, detail="Dosya adı gerekli")
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=422, detail="Yalnızca PDF kabul edilir")
    data = await file.read()
    if len(data) > _MAX_BYTES:
        raise HTTPException(status_code=413, detail="Dosya çok büyük")
    if len(data) < 32:
        raise HTTPException(status_code=422, detail="Geçersiz PDF")
    try:
        cv_text = extract_text_from_pdf(data)
    except Exception as exc:
        raise HTTPException(status_code=422, detail=f"PDF okunamadı: {exc}") from exc
    if len(cv_text) < 40:
        raise HTTPException(status_code=422, detail="PDF'den yeterli metin çıkarılamadı")
    return _queue(db, user, cv_text, "upload", file.filename or "cv.pdf")
