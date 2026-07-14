"""Unified CV analysis and target-plan AI service."""

from __future__ import annotations

import json
import ipaddress
import os
import re
import socket
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, TypeVar
from uuid import uuid4

from langchain_core.messages import HumanMessage, SystemMessage
import httpx
from urllib.parse import urlparse
from pydantic import BaseModel, ValidationError
from sqlalchemy import delete, select, update
from sqlalchemy.orm import Session

from app.core.config import settings
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence
from app.models.user import User
from app.schemas.career import CareerAnalysisAI, CareerPlanAI, EvidenceReviewAI
from app.services.ai_factory import AIOutputError, AIProviderError, AIUnavailableError, ai_configured, create_chat_model
from app.services.education_search import TrainingSearchUnavailable, search_training

T = TypeVar("T", bound=BaseModel)

def _invoke(prompt: str, schema: type[T]) -> T:
    if not ai_configured():
        raise AIUnavailableError("AI sağlayıcısı yapılandırılmamış")
    try:
        contract = prompt + "\n\nZorunlu JSON Schema:\n" + json.dumps(schema.model_json_schema(), ensure_ascii=False)
        response = create_chat_model().invoke([
            SystemMessage(content="Yalnızca verilen JSON Schema ile birebir uyumlu tek JSON nesnesi üret; markdown, kod bloğu veya açıklama ekleme."),
            HumanMessage(content=contract),
        ])
        content = response.content
        if isinstance(content, list):
            raw = "".join(
                item if isinstance(item, str) else str(item.get("text", ""))
                for item in content
                if isinstance(item, (str, dict))
            )
        else:
            raw = str(content or "")
        raw = raw.strip()
        if raw.startswith("```"):
            raw = raw.removeprefix("```json").removeprefix("```").removesuffix("```").strip()
        if not raw.startswith("{"):
            start, end = raw.find("{"), raw.rfind("}")
            if start >= 0 and end > start:
                raw = raw[start:end + 1]
        payload = json.loads(raw)
        return schema.model_validate(payload)
    except (ValidationError, ValueError, TypeError, json.JSONDecodeError) as exc:
        raise AIOutputError("AI yanıtı beklenen kariyer JSON şemasına uymuyor") from exc
    except (AIUnavailableError, AIOutputError):
        raise
    except Exception as exc:
        raise AIProviderError("AI sağlayıcısından kariyer yanıtı alınamadı") from exc


def create_analysis(
    db: Session,
    user_id: int,
    cv_text: str,
    source: str,
    file_name: str | None,
    cv_document_id: str | None = None,
) -> CareerAnalysis:
    now = datetime.now(timezone.utc)
    row = CareerAnalysis(
        id=str(uuid4()), user_id=user_id, cv_document_id=cv_document_id,
        status="queued", source=source, file_name=file_name, cv_text=cv_text,
        created_at=now, updated_at=now,
    )
    db.add(row)
    db.commit()
    db.refresh(row)
    return row


def analyze_row(db: Session, row: CareerAnalysis, evidence_context: list[dict] | None = None) -> CareerAnalysis:
    row.status = "running"
    db.commit()
    prompt = json.dumps({
        "purpose": "CV ve doğrulanmış kanıtlardan kişiye özel kariyer analizi üret",
        "rules": [
            "current_role yalnız CV içindeki kronolojik olarak en son iş deneyiminin meslek unvanıdır; deneyim yoksa null",
            "skills ve radar yalnız CV ile accepted_evidence içindeki doğrulanabilir yeteneklere dayanır",
            "radar score mevcut seviyeyi, target seçilebilir kariyer hedefleri için beklenen seviyeyi gösterir",
            "roles toplam 3-15 öğedir ve A, B, C katmanlarının her biri en az bir rol içerir; katman başına birden çok rol üretilebilir",
            "A mevcut yeteneklerle şimdi hazır olunan roller, B kısa gelişimle yakın roller, C ulaşılabilecek en yüksek zirve rollerdir",
            "Her rolün SWOT alanları kişiye ve role özel somut AI analizidir; genel/geçici metin kullanma",
            "Accepted evidence yeni bir yeteneği doğruluyorsa ilgili skill ve radar score değerini yeniden değerlendir",
        ],
        "cv_text": (row.cv_text or "")[:12000],
        "accepted_evidence": evidence_context or [],
    }, ensure_ascii=False)
    try:
        output = _invoke(prompt, CareerAnalysisAI)
        data = output.model_dump(mode="json")
        row.status = "ready"
        row.current_role = output.current_role
        row.profile = output.profile
        row.skills = data["skills"]
        row.radar = data["radar"]
        row.career_ladder = data["roles"]
        row.error_code = row.error_message = None
        if row.source in {"archive_uploaded", "archive_generated"}:
            _delete_target_plan(db, row.user_id)
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        row.status = "failed"
        row.error_code = "ai_unavailable" if isinstance(exc, AIUnavailableError) else ("ai_invalid_output" if isinstance(exc, AIOutputError) else "ai_provider_error")
        row.error_message = str(exc)[:500]
    db.commit()
    db.refresh(row)
    return row


def select_target(db: Session, user_id: int, title: str, source: str, job_url: str | None) -> CareerTarget:
    now = datetime.now(timezone.utc)
    db.execute(update(CareerTarget).where(CareerTarget.user_id == user_id, CareerTarget.status.in_(["queued", "ready", "active"])).values(status="closed", closed_at=now))
    target = CareerTarget(id=str(uuid4()), user_id=user_id, title=title, source=source, job_url=job_url, status="queued")
    db.add(target)
    db.commit()
    db.refresh(target)
    return target


def plan_target(db: Session, target: CareerTarget) -> CareerTarget:
    if target.status != "queued":
        return target
    analysis = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == target.user_id, CareerAnalysis.status == "ready").order_by(CareerAnalysis.created_at.desc()))
    if analysis is None:
        target.status = "failed"
        target.plan = {"error_code": "analysis_required"}
        db.commit()
        return target
    prompt = json.dumps({
        "purpose": "Seçilen mesleğe ulaşmak için kişiye özel görev ve eğitim planı üret",
        "target_role": target.title,
        "current_role": analysis.current_role,
        "skills": analysis.skills,
        "radar": analysis.radar,
        "rules": [
            "Görevler somut, ölçülebilir ve hedef role doğrudan bağlı olmalı",
            "Kanıt gerektiren görevde evidence_required true ve uygun link/file türleri olmalı",
            "skill_impacts yalnız görev tamamlandığında yeniden puanlanacak yetenekleri içermeli",
            "Eğitim gerektiğinde training_queries içine hedef rol ve yeteneği içeren özgül web arama sorgusu yaz",
            "Eğitim gerekmeyen görevde training_queries boş olmalı",
            "Sorgular sertifika, resmi eğitim, kurs veya uygulamalı öğrenme kaynağı bulmaya uygun olmalı",
        ],
    }, ensure_ascii=False)
    try:
        output = _invoke(prompt, CareerPlanAI)
        task_specs = []
        search_errors = []
        for item in output.tasks:
            suggestions = []
            for search in item.training_queries:
                try:
                    suggestions.extend(search_training(search.query, search.skill, 3))
                except (TrainingSearchUnavailable, httpx.HTTPError) as exc:
                    search_errors.append(str(exc)[:200])
            task_specs.append((item, suggestions))
        db.refresh(target)
        if target.status != "queued":
            return target
        tasks = []
        for item, suggestions in task_specs:
            task = CareerTask(id=str(uuid4()), user_id=target.user_id, target_id=target.id, title=item.title, hint=item.hint, status="pending", evidence_required=item.evidence_required, evidence_types=item.evidence_types, skill_impacts=item.skill_impacts, training_suggestions=suggestions)
            db.add(task)
            tasks.append({"id": task.id, "title": task.title, "training_suggestions": suggestions})
        target.plan = {"tasks": tasks, "training_search": {"provider": settings.EDUCATION_SEARCH_PROVIDER, "status": "ready" if not search_errors else "partial", "errors": search_errors[:3]}}
        target.status = "active"
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        db.refresh(target)
        if target.status != "queued":
            return target
        target.status = "failed"
        target.plan = {"error_code": "ai_unavailable" if isinstance(exc, AIUnavailableError) else "ai_error", "message": str(exc)[:500]}
    db.commit()
    db.refresh(target)
    return target


def serialize_analysis(row: CareerAnalysis) -> dict[str, Any]:
    return {"id": row.id, "status": row.status, "source": row.source, "file_name": row.file_name, "cv_document_id": row.cv_document_id, "current_role": row.current_role, "profile": row.profile or {}, "skills": row.skills or [], "radar": row.radar or [], "career_ladder": row.career_ladder or [], "error_code": row.error_code, "error_message": row.error_message, "created_at": (row.created_at or datetime.now(timezone.utc)).isoformat()}


def serialize_target(row: CareerTarget) -> dict[str, Any]:
    return {"id": row.id, "title": row.title, "source": row.source, "status": row.status, "plan": row.plan or {}, "created_at": (row.created_at or datetime.now(timezone.utc)).isoformat()}


def serialize_task(row: CareerTask, db: Session | None = None) -> dict[str, Any]:
    has_evidence = evidence_verified = evidence_pending = False
    if db is not None:
        evidence_rows = db.scalars(select(Evidence).where(Evidence.task_id == row.id)).all()
        has_evidence = len(evidence_rows) > 0
        evidence_verified = any(item.status == "accepted" for item in evidence_rows)
        evidence_pending = any(item.status == "pending" for item in evidence_rows)
    return {
        "id": row.id,
        "target_id": row.target_id,
        "title": row.title,
        "hint": row.hint,
        "note": row.note or "",
        "status": row.status,
        "evidence_required": row.evidence_required,
        "evidence_types": row.evidence_types or [],
        "skill_impacts": row.skill_impacts or [],
        "training_suggestions": row.training_suggestions or [],
        "feedback": row.feedback,
        "has_evidence": has_evidence,
        "evidence_verified": evidence_verified,
        "evidence_pending": evidence_pending,
    }


def submit_evidence(db: Session, user_id: int, task: CareerTask, kind: str, url: str | None, file_path: str | None) -> Evidence:
    evidence = Evidence(id=str(uuid4()), user_id=user_id, task_id=task.id, kind=kind, url=url, file_path=file_path, status="pending")
    db.add(evidence)
    db.commit()
    db.refresh(evidence)
    return evidence


def reset_career_state(db: Session, user_id: int, scope: str, *, commit: bool = True) -> dict[str, int]:
    evidence_files = career_evidence_file_paths(db, user_id) if scope in {"plan", "all"} else []
    deleted = {"analyses": 0, "targets": 0, "tasks": 0, "evidence": 0}
    if scope in {"plan", "all"}:
        deleted.update(_delete_target_plan(db, user_id))
    if scope in {"analysis", "all"}:
        deleted["analyses"] = db.execute(delete(CareerAnalysis).where(CareerAnalysis.user_id == user_id)).rowcount
    if commit:
        db.commit()
        remove_career_evidence_files(user_id, evidence_files)
    return deleted


def career_evidence_file_paths(db: Session, user_id: int) -> list[str]:
    return list(db.scalars(select(Evidence.file_path).where(Evidence.user_id == user_id, Evidence.file_path.is_not(None))).all())


def remove_career_evidence_files(user_id: int, file_paths: list[str]) -> None:
    upload_root = (Path(settings.UPLOAD_DIR).resolve() / str(user_id))
    for file_path in file_paths:
        path = Path(file_path).resolve()
        if path.is_relative_to(upload_root) and path.is_file():
            path.unlink()


def _delete_target_plan(db: Session, user_id: int) -> dict[str, int]:
    return {
        "evidence": db.execute(delete(Evidence).where(Evidence.user_id == user_id)).rowcount,
        "tasks": db.execute(delete(CareerTask).where(CareerTask.user_id == user_id)).rowcount,
        "targets": db.execute(delete(CareerTarget).where(CareerTarget.user_id == user_id)).rowcount,
    }


def _find_skill_task(db: Session, user_id: int, target_id: str, skill: str) -> CareerTask | None:
    needle = skill.strip().lower()
    if needle == "":
        return None
    rows = db.scalars(select(CareerTask).where(CareerTask.user_id == user_id, CareerTask.target_id == target_id)).all()
    for row in rows:
        impacts = [str(item).lower() for item in (row.skill_impacts or [])]
        if needle in impacts:
            return row
    return None


def ensure_skill_evidence_task(db: Session, user_id: int, target_id: str, skill: str) -> CareerTask:
    existing = _find_skill_task(db, user_id, target_id, skill)
    if existing is not None:
        return existing
    target = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id, CareerTarget.user_id == user_id))
    if target is None:
        raise ValueError("Hedef bulunamadı")
    task = CareerTask(
        id=str(uuid4()),
        user_id=user_id,
        target_id=target_id,
        title=f"{skill.strip()} kanıtı",
        hint=f"{skill.strip()} yeteneğini kanıtlayan GitHub, sertifika, portföy veya doğrulanabilir bağlantı yükle.",
        status="pending",
        evidence_required=False,
        evidence_types=["link", "file"],
        skill_impacts=[skill.strip()],
        training_suggestions=[],
    )
    db.add(task)
    db.commit()
    db.refresh(task)
    return task


def clear_skill_evidence(db: Session, user_id: int, target_id: str, skill: str) -> CareerTask | None:
    task = _find_skill_task(db, user_id, target_id, skill)
    if task is None:
        return None
    db.execute(delete(Evidence).where(Evidence.task_id == task.id, Evidence.user_id == user_id))
    task.status = "pending"
    task.feedback = None
    db.commit()
    db.refresh(task)
    return task


def review_evidence(db: Session, evidence: Evidence) -> Evidence:
    task = db.scalar(select(CareerTask).where(CareerTask.id == evidence.task_id, CareerTask.user_id == evidence.user_id))
    if task is None:
        return evidence
    content = _evidence_content(evidence)
    if content is None:
        evidence.status = "revision_required"
        evidence.confidence = 0
        evidence.feedback = "Kanıt içeriği erişilemedi veya güvenli biçimde doğrulanamadı"
        task.status = "revision_required"
        task.feedback = evidence.feedback
        evidence.reviewed_at = datetime.now(timezone.utc)
        db.commit()
        db.refresh(evidence)
        return evidence
    owner = db.scalar(select(User).where(User.id == evidence.user_id))
    analysis = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == evidence.user_id).order_by(CareerAnalysis.created_at.desc()))
    prompt = json.dumps({"owner": {"id": evidence.user_id, "full_name": owner.full_name if owner else None, "email": owner.email if owner else None}, "latest_profile": analysis.profile if analysis else {}, "evidence": {"kind": evidence.kind, "content": content[:12000]}, "task_acceptance_criteria": {"title": task.title, "hint": task.hint, "skill_impacts": task.skill_impacts, "evidence_types": task.evidence_types}, "output": "decision accept/revise, confidence 0-1, feedback"}, ensure_ascii=False)
    try:
        result = _invoke(prompt, EvidenceReviewAI)
        accepted = result.decision == "accept" and result.confidence >= 0.8
        evidence.status = "accepted" if accepted else "revision_required"
        evidence.confidence = result.confidence
        evidence.feedback = result.feedback
        task.status = "completed" if accepted else "revision_required"
        task.feedback = result.feedback
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        evidence.status = "revision_required"
        evidence.confidence = 0
        evidence.feedback = str(exc)[:500]
        task.status = "revision_required"
        task.feedback = evidence.feedback
    evidence.reviewed_at = datetime.now(timezone.utc)
    db.commit()
    db.refresh(evidence)
    return evidence


def _evidence_content(evidence: Evidence) -> str | None:
    if evidence.kind == "link":
        url = evidence.url or ""
        parsed = urlparse(url)
        if parsed.scheme not in {"http", "https"} or not parsed.hostname or not _public_host(parsed.hostname):
            return None
        try:
            response = httpx.get(url, follow_redirects=False, timeout=5, headers={"User-Agent": "CareerTalentAI/1.0"})
            if response.status_code >= 400 or len(response.content) > 250_000:
                return None
            text = re.sub(r"<[^>]+>", " ", response.text)
            return " ".join(text.split()) or None
        except httpx.HTTPError:
            return None
    path = evidence.file_path
    if not path:
        return None
    base = Path(settings.UPLOAD_DIR).resolve() / str(evidence.user_id)
    candidate = Path(path).resolve()
    if not candidate.is_relative_to(base) or not candidate.is_file() or candidate.stat().st_size > settings.MAX_UPLOAD_SIZE_MB * 1024 * 1024:
        return None
    data = candidate.read_bytes()
    if data.startswith(b"%PDF"):
        from app.services.cv_parser import extract_text_from_pdf
        try:
            return extract_text_from_pdf(data) or None
        except Exception:
            return None
    if data.startswith(b"\x89PNG") or data.startswith(b"\xff\xd8\xff"):
        try:
            result = subprocess.run(["/usr/bin/tesseract", str(candidate), "stdout", "-l", "eng+tur"], capture_output=True, text=True, timeout=15, check=False)
        except (OSError, subprocess.TimeoutExpired):
            return None
        text = " ".join(result.stdout.split())
        return text or None
    return None


def _public_host(host: str) -> bool:
    lowered = host.lower().rstrip(".")
    if lowered in {"localhost", "metadata.google.internal"} or lowered.endswith(".localhost"):
        return False
    try:
        address = ipaddress.ip_address(lowered)
        return not (address.is_private or address.is_loopback or address.is_link_local or address.is_reserved or address.is_multicast)
    except ValueError:
        try:
            resolved = socket.getaddrinfo(lowered, None)
        except OSError:
            return False
        return all(_public_host(str(item[4][0])) for item in resolved)
