"""AI-backed job listing and CV improvement workflow."""

from __future__ import annotations

import json
import re
from copy import deepcopy
from html import unescape
from typing import Any, Callable
from urllib.parse import urlparse
from uuid import uuid4

import httpx
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.models.career_engine import CareerAnalysis, JobOpportunity
from app.models.engagement import CandidateCvVersion
from app.schemas.career import CvBuilderDraftAI, CvRewriteAI, JobOpportunityAI
from app.services.ai_factory import AIOutputError, AIProviderError, AIUnavailableError
from app.services.career_engine import _invoke, _public_host, analyze_row, create_analysis


class JobListingError(ValueError):
    """The supplied listing cannot safely be read."""


class JobCvVersionError(ValueError):
    """The requested CV-version draft cannot be created."""


class JobQueueUnavailableError(RuntimeError):
    """The committed job row could not be published to the worker queue."""


QUEUE_UNAVAILABLE_CODE = "queue_unavailable"
QUEUE_UNAVAILABLE_MESSAGE = "İşlem kuyruğa alınamadı. Lütfen tekrar deneyin."


def current_analysis(db: Session, user_id: int) -> CareerAnalysis | None:
    return db.scalar(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id == user_id, CareerAnalysis.status == "ready")
        .order_by(CareerAnalysis.created_at.desc())
    )


def latest_analysis(db: Session, user_id: int) -> CareerAnalysis | None:
    return db.scalar(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id == user_id)
        .order_by(CareerAnalysis.created_at.desc())
    )


def cv_snapshot(analysis: CareerAnalysis) -> dict[str, Any]:
    """Create the JSON-safe, bounded CV state captured by the analyze click."""
    return {
        "cv_text": (analysis.cv_text or "")[:16000],
        "current_role": analysis.current_role,
        "profile": deepcopy(analysis.profile or {}),
        "skills": deepcopy(analysis.skills or []),
    }


def create_job(
    db: Session,
    user_id: int,
    source_url: str | None,
    job_text: str | None,
    analysis: CareerAnalysis | None = None,
) -> JobOpportunity:
    row = JobOpportunity(
        id=str(uuid4()),
        user_id=user_id,
        status="queued",
        source_url=(source_url or "").strip() or None,
        job_text=(job_text or "").strip() or None,
        source_analysis_id=analysis.id if analysis is not None else None,
        source_cv_file_name=analysis.file_name if analysis is not None else None,
    )
    db.add(row)
    db.commit()
    db.refresh(row)
    return row


def dispatch_job_analysis(
    db: Session,
    row: JobOpportunity,
    snapshot: dict[str, Any],
    publish: Callable[[str, dict[str, Any]], Any],
) -> None:
    try:
        publish(row.id, snapshot)
    except Exception as exc:
        db.rollback()
        persisted = db.get(JobOpportunity, row.id)
        if persisted is not None:
            persisted.status = "failed"
            persisted.error_code = QUEUE_UNAVAILABLE_CODE
            persisted.error_message = QUEUE_UNAVAILABLE_MESSAGE
            db.commit()
        raise JobQueueUnavailableError(QUEUE_UNAVAILABLE_MESSAGE) from exc


def analyze_job(
    db: Session,
    row: JobOpportunity,
    analysis: CareerAnalysis | None = None,
    snapshot: dict[str, Any] | None = None,
) -> JobOpportunity:
    if snapshot is None:
        analysis = analysis or current_analysis(db, row.user_id)
        if analysis is None:
            return _fail(db, row, "cv_required", "İlan analizi için hazır CV analizi gerekli")
        snapshot = cv_snapshot(analysis)
    else:
        snapshot = {
            "cv_text": str(snapshot.get("cv_text") or "")[:16000],
            "current_role": snapshot.get("current_role"),
            "profile": deepcopy(snapshot.get("profile") if isinstance(snapshot.get("profile"), dict) else {}),
            "skills": deepcopy(snapshot.get("skills") if isinstance(snapshot.get("skills"), list) else []),
        }

    snapshot.update({"job_id": row.id, "source_url": row.source_url, "job_text": row.job_text})
    row.status = "running"
    row.error_code = row.error_message = None
    db.commit()
    try:
        listing = _listing_text(snapshot["source_url"], snapshot["job_text"])
        prompt = json.dumps({
            "purpose": "İş ilanını adayın güncel CV'siyle karşılaştır ve CV iyileştirme önerileri üret",
            "rules": [
                "required_skills yalnız ilanda açıkça istenen teknik veya mesleki yeteneklerdir",
                "matched_skills yalnız CV içinde doğrulanabilen required_skills öğeleridir",
                "missing_skills required_skills içinde olup CV'de doğrulanamayan öğelerdir",
                "match_score CV ile ilan arasındaki gerçek uyumu 0-100 aralığında gösterir",
                "rewrite yalnız CV'deki mevcut gerçeği daha açık anlatır; yeni deneyim veya başarı uydurmaz",
                "add yalnız CV metninde zaten doğrulanabilen bir bilgiyi eksik bölüme taşır",
                "develop eksik yetenek kazanma önerisidir ve safe_to_apply false olmalıdır",
                "CV'de doğrulanamayan sertifika, deneyim, süre, sayı, araç veya yetenek ekleme",
                "Her öneri ilanla ilişkili, somut ve Türkçe olmalıdır",
            ],
            "listing": listing[:16000],
            "cv_text": (snapshot["cv_text"] or "")[:16000],
            "current_role": snapshot["current_role"],
            "profile": snapshot["profile"],
            "skills": snapshot["skills"],
        }, ensure_ascii=False)
        output = _invoke(prompt, JobOpportunityAI)
        data = output.model_dump(mode="json")
        suggestions = []
        for item in data["cv_suggestions"]:
            item["id"] = str(uuid4())
            if item["action"] == "develop":
                item["safe_to_apply"] = False
            suggestions.append(item)
    except JobListingError as exc:
        return _fail_by_id(db, snapshot["job_id"], "invalid_listing", str(exc))
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        code = "ai_unavailable" if isinstance(exc, AIUnavailableError) else ("ai_invalid_output" if isinstance(exc, AIOutputError) else "ai_provider_error")
        return _fail_by_id(db, snapshot["job_id"], code, str(exc))

    with db.begin():
        persisted = db.get(JobOpportunity, snapshot["job_id"])
        if persisted is None:
            raise RuntimeError("İlan analizi kaydı bulunamadı")
        persisted.status = "ready"
        persisted.title = output.title
        persisted.company = output.company
        persisted.source = output.source or (
            urlparse(snapshot["source_url"]).hostname if snapshot["source_url"] else "İlan metni"
        )
        persisted.required_skills = data["required_skills"]
        persisted.matched_skills = data["matched_skills"]
        persisted.missing_skills = data["missing_skills"]
        persisted.match_score = output.match_score
        persisted.cv_suggestions = suggestions
        persisted.error_code = persisted.error_message = None
    db.refresh(persisted)
    return persisted


def apply_suggestions(db: Session, row: JobOpportunity, suggestion_ids: list[str]) -> JobOpportunity:
    analysis = current_analysis(db, row.user_id)
    if analysis is None:
        return _apply_fail(db, row, "Hazır CV analizi bulunamadı")
    indexed = {item.get("id"): item for item in (row.cv_suggestions or [])}
    selected = [indexed[item_id] for item_id in dict.fromkeys(suggestion_ids) if item_id in indexed]
    if not selected or any(not item.get("safe_to_apply") for item in selected):
        return _apply_fail(db, row, "Yalnız güvenli ve mevcut öneriler uygulanabilir")

    row.apply_status = "running"
    db.commit()
    try:
        output = _invoke(json.dumps({
            "purpose": "Seçilen güvenli önerileri CV'ye uygula",
            "rules": [
                "CV'deki bilgi ve gerçekleri koru",
                "Yeni deneyim, yetenek, sertifika, eğitim, sayı, süre veya başarı uydurma",
                "Yalnız seçilen önerileri uygula; tam CV metnini döndür",
            ],
            "current_cv": (analysis.cv_text or "")[:20000],
            "selected_suggestions": selected,
        }, ensure_ascii=False), CvRewriteAI)
        new_analysis = create_analysis(db, row.user_id, output.revised_cv_text, "job_suggestion", None)
        analyze_row(db, new_analysis)
        if new_analysis.status != "ready":
            return _apply_fail(db, row, new_analysis.error_message or "CV yeniden analiz edilemedi")
        row.result_analysis_id = new_analysis.id
        row.applied_suggestion_ids = [item["id"] for item in selected]
        row.apply_status = "ready"
        db.commit()
        db.refresh(row)
        return analyze_job(db, row, new_analysis)
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        return _apply_fail(db, row, str(exc))


def create_cv_version_for_job(
    db: Session,
    row: JobOpportunity,
    suggestion_ids: list[str],
    source_cv_version_id: str | None,
    language: str,
) -> CandidateCvVersion:
    if row.status != "ready":
        raise JobCvVersionError("CV taslağı yalnız tamamlanan ilan analizi için oluşturulabilir")

    indexed = {item.get("id"): item for item in (row.cv_suggestions or [])}
    selected = [indexed.get(item_id) for item_id in dict.fromkeys(suggestion_ids)]
    if not selected or any(item is None or not item.get("safe_to_apply") for item in selected):
        raise JobCvVersionError("Yalnız güvenli CV önerileri kullanılabilir")

    source_version = None
    if source_cv_version_id:
        source_version = db.scalar(
            select(CandidateCvVersion).where(
                CandidateCvVersion.id == source_cv_version_id,
                CandidateCvVersion.user_id == row.user_id,
            )
        )
        if source_version is None:
            raise JobCvVersionError("Kaynak CV sürümü bulunamadı")

    analysis = current_analysis(db, row.user_id)
    if source_version is None and analysis is None:
        raise JobCvVersionError("Hazır CV analizi bulunamadı")

    requested_language = source_version.language if source_version is not None else language
    target_language = requested_language if requested_language in {"tr", "en"} else "tr"
    source_cv: dict[str, Any] = (
        {"type": "saved_version", "payload": source_version.payload}
        if source_version is not None
        else {
            "type": "active_analysis",
            "file_name": analysis.file_name,
            "cv_text": (analysis.cv_text or "")[:24000],
            "profile": analysis.profile or {},
            "skills": analysis.skills or [],
        }
    )
    output = _invoke(json.dumps({
        "purpose": "Onaylanan ilan önerilerine göre düzenlenebilir yeni bir CV sürümü oluştur",
        "language": target_language,
        "rules": [
            "Kaynak CV'deki kişi, kurum, tarih, eğitim, deneyim, proje, sertifika ve iletişim gerçeklerini koru",
            "Kaynakta olmayan deneyim, beceri, sertifika, sayı, süre, görev veya başarı uydurma",
            "Yalnız seçilen güvenli önerileri uygula ve ilanla ilgili mevcut gerçekleri daha görünür yaz",
            "Eksik ilan becerilerini kişi kazanmış gibi CV'ye ekleme",
            "Tüm CV'yi CV Merkezi düzenleyici alanlarına ayırarak döndür",
        ],
        "job": {
            "title": row.title,
            "company": row.company,
            "job_text": (row.job_text or "")[:16000],
            "required_skills": row.required_skills or [],
            "matched_skills": row.matched_skills or [],
            "missing_skills": row.missing_skills or [],
        },
        "source_cv": source_cv,
        "selected_suggestions": selected,
    }, ensure_ascii=False), CvBuilderDraftAI, language=target_language)

    payload = output.model_dump(mode="json")
    for section in ("education", "experience", "skills", "projects", "certificates"):
        for item in payload[section]:
            item["id"] = str(uuid4())
    payload["enabledOptional"] = []
    payload["optional"] = {}

    version_name = f"CV for {row.title or 'Job'}" if target_language == "en" else f"{row.title or 'İlan'} için CV"
    version = CandidateCvVersion(
        id=str(uuid4()),
        user_id=row.user_id,
        version_name=version_name[:160],
        language=target_language,
        is_main=False,
        payload=payload,
    )
    db.add(version)
    db.commit()
    db.refresh(version)
    return version


def serialize_job(row: JobOpportunity) -> dict[str, Any]:
    return {
        "id": row.id, "status": row.status, "source_url": row.source_url,
        "title": row.title, "company": row.company, "source": row.source,
        "required_skills": row.required_skills or [], "matched_skills": row.matched_skills or [],
        "missing_skills": row.missing_skills or [], "match_score": row.match_score,
        "cv_suggestions": row.cv_suggestions or [], "saved": bool(row.saved),
        "apply_status": row.apply_status, "applied_suggestion_ids": row.applied_suggestion_ids or [],
        "result_analysis_id": row.result_analysis_id,
        "source_analysis_id": row.source_analysis_id, "source_cv_file_name": row.source_cv_file_name,
        "error_code": row.error_code,
        "error_message": row.error_message, "created_at": row.created_at,
    }


def _listing_text(source_url: str | None, job_text: str | None) -> str:
    pasted_text = (job_text or "").strip()
    if len(pasted_text) >= 40:
        return pasted_text

    pieces = []
    if source_url:
        parsed = urlparse(source_url)
        if parsed.scheme not in {"http", "https"} or not parsed.hostname or not _public_host(parsed.hostname):
            raise JobListingError("İlan URL'si güvenli ve herkese açık bir adres olmalı")
        try:
            with httpx.Client(follow_redirects=False, timeout=8.0) as client:
                response = client.get(source_url, headers={"User-Agent": "CareerTalentJobAnalyzer/1.0"})
            if 300 <= response.status_code < 400:
                raise JobListingError("İlan URL'si yönlendiriliyor; ilan metnini yapıştırın")
            response.raise_for_status()
            if len(response.content) > 1_000_000:
                raise JobListingError("İlan sayfası çok büyük")
            text = unescape(re.sub(r"<[^>]+>", " ", response.text))
            pieces.append(re.sub(r"\s+", " ", text).strip())
        except JobListingError:
            raise
        except Exception as exc:
            raise JobListingError("İlan URL'si okunamadı; ilan metnini yapıştırın") from exc
    if pasted_text:
        pieces.append(pasted_text)
    combined = "\n\n".join(item for item in pieces if item)
    if len(combined) < 40:
        raise JobListingError("Analiz için yeterli ilan metni bulunamadı")
    return combined


def _fail(db: Session, row: JobOpportunity, code: str, message: str) -> JobOpportunity:
    row.status, row.error_code, row.error_message = "failed", code, message[:500]
    db.commit(); db.refresh(row)
    return row


def _fail_by_id(db: Session, job_id: str, code: str, message: str) -> JobOpportunity:
    with db.begin():
        row = db.get(JobOpportunity, job_id)
        if row is None:
            raise RuntimeError("İlan analizi kaydı bulunamadı")
        row.status, row.error_code, row.error_message = "failed", code, message[:500]
    db.refresh(row)
    return row


def _apply_fail(db: Session, row: JobOpportunity, message: str) -> JobOpportunity:
    row.apply_status, row.error_message = "failed", message[:500]
    db.commit(); db.refresh(row)
    return row
