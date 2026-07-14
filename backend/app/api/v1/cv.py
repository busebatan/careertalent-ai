"""Authenticated CV intake; analysis is always queued through Celery."""

from __future__ import annotations

import json
import os
from pathlib import Path
import re
from typing import Annotated
from uuid import uuid4

from fastapi import APIRouter, Depends, File, Form, HTTPException, Response, UploadFile
from fastapi.responses import FileResponse
from sqlalchemy import delete, func, select
from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.database import get_db
from app.core.security import get_current_user
from app.models.user import User
from app.models.engagement import CvDocument
from app.schemas.career import CVQueueResponse
from app.schemas.cv import AnalyzeTextRequest
from app.services.career_engine import career_evidence_file_paths, create_analysis, remove_career_evidence_files, reset_career_state
from app.services.cv_content import has_meaningful_cv_content
from app.services.cv_parser import extract_text_from_pdf
from app.tasks.career import analyze_cv_task

router = APIRouter()
DB = Annotated[Session, Depends(get_db)]
CurrentUser = Annotated[User, Depends(get_current_user)]
_MAX_BYTES = settings.MAX_UPLOAD_SIZE_MB * 1024 * 1024


def _cv_path(user_id: int, document_id: str) -> Path:
    return Path(settings.UPLOAD_DIR) / str(user_id) / "cv" / f"{document_id}.pdf"


def _store_pdf(user_id: int, document_id: str, data: bytes) -> str:
    path = _cv_path(user_id, document_id)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)
    os.chmod(path, 0o600)
    return str(path)


def _remove_cv_files(user_id: int, documents: list[CvDocument]) -> None:
    base = (Path(settings.UPLOAD_DIR).resolve() / str(user_id) / "cv")
    for document in documents:
        path = Path(document.file_path).resolve()
        if path.is_relative_to(base) and path.is_file():
            path.unlink()


def _safe_pdf_name(name: str) -> str:
    clean = re.sub(r'[\x00-\x1f\x7f/\\]+', "-", name.strip())[:250] or "cv.pdf"
    return clean if clean.lower().endswith(".pdf") else clean + ".pdf"


def _serialize_document(row: CvDocument, include_builder: bool = False) -> dict:
    payload = {"id": row.id, "kind": row.kind, "display_name": row.display_name, "original_name": row.original_name, "file_size": row.file_size, "language": row.language, "is_current": row.is_current, "created_at": row.created_at.isoformat() if row.created_at else None}
    if include_builder:
        payload["builder_data"] = row.builder_data
    return payload


def _queue(db: DB, user: CurrentUser, cv_text: str, source: str, file_name: str, cv_document_id: str | None = None) -> CVQueueResponse:
    row = create_analysis(db, user.id, cv_text, source, file_name, cv_document_id)
    analyze_cv_task.delay(row.id)
    return {"analysis_id": row.id, "status": "queued"}


def _snapshot_text(value: object) -> str:
    parts: list[str] = []

    def visit(item: object) -> None:
        if isinstance(item, str) and item.strip():
            parts.append(item.strip())
        elif isinstance(item, dict):
            for child in item.values():
                visit(child)
        elif isinstance(item, list):
            for child in item:
                visit(child)

    visit(value)
    return "\n".join(parts)


@router.post("/analyze-text", response_model=CVQueueResponse, status_code=202)
async def analyze_cv_text(body: AnalyzeTextRequest, db: DB, user: CurrentUser):
    cv_text = body.cv_text.strip()
    if len(cv_text) < 40 or not has_meaningful_cv_content(cv_text):
        raise HTTPException(status_code=422, detail="CV'de analiz edilebilir deneyim, eğitim, proje veya yetenek içeriği bulunamadı")
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
    if len(cv_text) < 40 or not has_meaningful_cv_content(cv_text):
        raise HTTPException(status_code=422, detail="PDF'de analiz edilebilir deneyim, eğitim, proje veya yetenek içeriği bulunamadı")
    document_id = str(uuid4())
    display_name = _safe_pdf_name(file.filename)
    previous_documents = list(db.scalars(select(CvDocument).where(CvDocument.user_id == user.id)).all())
    evidence_files = career_evidence_file_paths(db, user.id)
    file_path = _store_pdf(user.id, document_id, data)
    try:
        reset_career_state(db, user.id, "all", commit=False)
        db.execute(delete(CvDocument).where(CvDocument.user_id == user.id))
        db.add(CvDocument(id=document_id, user_id=user.id, kind="uploaded", display_name=display_name, original_name=display_name, file_path=file_path, file_size=len(data), language=None, builder_data=None, is_current=True))
        db.commit()
    except Exception:
        db.rollback()
        Path(file_path).unlink(missing_ok=True)
        raise
    _remove_cv_files(user.id, previous_documents)
    remove_career_evidence_files(user.id, evidence_files)
    return _queue(db, user, cv_text, "upload", display_name, document_id)


@router.get("/documents")
def list_cv_documents(db: DB, user: CurrentUser):
    rows = db.scalars(select(CvDocument).where(CvDocument.user_id == user.id).order_by(CvDocument.created_at.desc())).all()
    return [_serialize_document(row) for row in rows]


@router.get("/documents/{document_id}")
def get_cv_document(document_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CvDocument).where(CvDocument.id == document_id, CvDocument.user_id == user.id))
    if row is None: raise HTTPException(status_code=404, detail="CV kaydı bulunamadı")
    return _serialize_document(row, include_builder=True)


@router.post("/documents/{document_id}/analyze", response_model=CVQueueResponse, status_code=202)
def analyze_cv_document(document_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CvDocument).where(CvDocument.id == document_id, CvDocument.user_id == user.id))
    if row is None:
        raise HTTPException(status_code=404, detail="CV kaydı bulunamadı")

    if row.kind == "generated":
        cv_text = _snapshot_text(row.builder_data or {})
    else:
        path = Path(row.file_path)
        if path.resolve() != _cv_path(user.id, row.id).resolve() or not path.is_file():
            raise HTTPException(status_code=410, detail="CV dosyası artık erişilebilir değil")
        try:
            cv_text = extract_text_from_pdf(path.read_bytes())
        except Exception as exc:
            raise HTTPException(status_code=422, detail=f"PDF okunamadı: {exc}") from exc

    if len(cv_text) < 40 or not has_meaningful_cv_content(cv_text):
        raise HTTPException(status_code=422, detail="CV'de analiz edilebilir deneyim, eğitim, proje veya yetenek içeriği bulunamadı")
    return _queue(db, user, cv_text, f"archive_{row.kind}", row.display_name, row.id)


@router.post("/documents/generated", status_code=201)
async def archive_generated_cv(db: DB, user: CurrentUser, file: UploadFile = File(...), display_name: str = Form(...), language: str = Form(...), builder_data: str = Form(...)):
    data = await file.read()
    if len(data) > _MAX_BYTES: raise HTTPException(status_code=413, detail="Dosya çok büyük")
    if not data.startswith(b"%PDF"): raise HTTPException(status_code=422, detail="Geçersiz PDF")
    if len(builder_data.encode()) > 1_000_000: raise HTTPException(status_code=413, detail="CV oluşturucu verisi çok büyük")
    try: snapshot = json.loads(builder_data)
    except json.JSONDecodeError as exc: raise HTTPException(status_code=422, detail="CV oluşturucu verisi geçersiz") from exc
    if not isinstance(snapshot, dict) or language not in {"tr", "en"}: raise HTTPException(status_code=422, detail="CV oluşturucu verisi geçersiz")
    document_id = str(uuid4()); name = _safe_pdf_name(display_name)
    row = CvDocument(id=document_id, user_id=user.id, kind="generated", display_name=name, original_name=name, file_path=_store_pdf(user.id, document_id, data), file_size=len(data), language=language, builder_data=snapshot, is_current=False)
    db.add(row); db.commit(); db.refresh(row)
    return _serialize_document(row)


@router.get("/documents/{document_id}/download")
def download_cv_document(document_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CvDocument).where(CvDocument.id == document_id, CvDocument.user_id == user.id))
    if row is None: raise HTTPException(status_code=404, detail="CV kaydı bulunamadı")
    path = Path(row.file_path).resolve(); base = (Path(settings.UPLOAD_DIR).resolve() / str(user.id) / "cv")
    if not path.is_relative_to(base) or not path.is_file(): raise HTTPException(status_code=404, detail="CV dosyası bulunamadı")
    return FileResponse(path, media_type="application/pdf", filename=row.display_name)


@router.patch("/documents/{document_id}/archive")
def archive_current_cv(document_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CvDocument).where(CvDocument.id == document_id, CvDocument.user_id == user.id, CvDocument.is_current.is_(True)))
    if row is None: raise HTTPException(status_code=404, detail="Mevcut CV bulunamadı")
    row.is_current = False; db.commit(); db.refresh(row)
    return _serialize_document(row)


@router.delete("/documents/{document_id}", status_code=204)
def delete_cv_document(document_id: str, db: DB, user: CurrentUser):
    row = db.scalar(select(CvDocument).where(CvDocument.id == document_id, CvDocument.user_id == user.id, CvDocument.is_current.is_(False)))
    if row is None: raise HTTPException(status_code=404, detail="Geçmiş CV kaydı bulunamadı")
    remaining = db.scalar(select(func.count()).select_from(CvDocument).where(CvDocument.user_id == user.id, CvDocument.id != document_id)) or 0
    evidence_files = career_evidence_file_paths(db, user.id) if remaining == 0 else []
    if remaining == 0:
        reset_career_state(db, user.id, "all", commit=False)
    db.delete(row); db.commit()
    _remove_cv_files(user.id, [row])
    remove_career_evidence_files(user.id, evidence_files)
    return Response(status_code=204)
