"""AI-backed job listing and CV improvement workflow."""

from __future__ import annotations

import json
import re
from html import unescape
from typing import Any
from urllib.parse import urlparse
from uuid import uuid4

import httpx
from sqlalchemy import select
from sqlalchemy.orm import Session

from app.models.career_engine import CareerAnalysis, JobOpportunity
from app.schemas.career import CvRewriteAI, JobOpportunityAI
from app.services.ai_factory import AIOutputError, AIProviderError, AIUnavailableError
from app.services.career_engine import _invoke, _public_host, analyze_row, create_analysis


class JobListingError(ValueError):
    """The supplied listing cannot safely be read."""


def current_analysis(db: Session, user_id: int) -> CareerAnalysis | None:
    return db.scalar(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id == user_id, CareerAnalysis.status == "ready")
        .order_by(CareerAnalysis.created_at.desc())
    )


def create_job(db: Session, user_id: int, source_url: str | None, job_text: str | None) -> JobOpportunity:
    row = JobOpportunity(
        id=str(uuid4()),
        user_id=user_id,
        status="queued",
        source_url=(source_url or "").strip() or None,
        job_text=(job_text or "").strip() or None,
    )
    db.add(row)
    db.commit()
    db.refresh(row)
    return row


def analyze_job(db: Session, row: JobOpportunity, analysis: CareerAnalysis | None = None) -> JobOpportunity:
    analysis = analysis or current_analysis(db, row.user_id)
    if analysis is None:
        return _fail(db, row, "cv_required", "İlan analizi için hazır CV analizi gerekli")

    row.status = "running"
    db.commit()
    try:
        listing = _listing_text(row.source_url, row.job_text)
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
            "cv_text": (analysis.cv_text or "")[:16000],
            "current_role": analysis.current_role,
            "profile": analysis.profile,
            "skills": analysis.skills or [],
        }, ensure_ascii=False)
        output = _invoke(prompt, JobOpportunityAI)
        data = output.model_dump(mode="json")
        suggestions = []
        for item in data["cv_suggestions"]:
            item["id"] = str(uuid4())
            if item["action"] == "develop":
                item["safe_to_apply"] = False
            suggestions.append(item)
        row.status = "ready"
        row.title = output.title
        row.company = output.company
        row.source = output.source or (urlparse(row.source_url).hostname if row.source_url else "İlan metni")
        row.required_skills = data["required_skills"]
        row.matched_skills = data["matched_skills"]
        row.missing_skills = data["missing_skills"]
        row.match_score = output.match_score
        row.cv_suggestions = suggestions
        row.error_code = row.error_message = None
    except JobListingError as exc:
        return _fail(db, row, "invalid_listing", str(exc))
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        code = "ai_unavailable" if isinstance(exc, AIUnavailableError) else ("ai_invalid_output" if isinstance(exc, AIOutputError) else "ai_provider_error")
        return _fail(db, row, code, str(exc))
    db.commit()
    db.refresh(row)
    return row


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


def serialize_job(row: JobOpportunity) -> dict[str, Any]:
    return {
        "id": row.id, "status": row.status, "source_url": row.source_url,
        "title": row.title, "company": row.company, "source": row.source,
        "required_skills": row.required_skills or [], "matched_skills": row.matched_skills or [],
        "missing_skills": row.missing_skills or [], "match_score": row.match_score,
        "cv_suggestions": row.cv_suggestions or [], "saved": bool(row.saved),
        "apply_status": row.apply_status, "applied_suggestion_ids": row.applied_suggestion_ids or [],
        "result_analysis_id": row.result_analysis_id, "error_code": row.error_code,
        "error_message": row.error_message, "created_at": row.created_at,
    }


def _listing_text(source_url: str | None, job_text: str | None) -> str:
    pieces = []
    if source_url:
        parsed = urlparse(source_url)
        if parsed.scheme not in {"http", "https"} or not parsed.hostname or not _public_host(parsed.hostname):
            raise JobListingError("İlan URL'si güvenli ve herkese açık bir adres olmalı")
        try:
            with httpx.Client(follow_redirects=False, timeout=8.0) as client:
                response = client.get(source_url, headers={"User-Agent": "CareerTalentJobAnalyzer/1.0"})
            if 300 <= response.status_code < 400:
                raise JobListingError("Yönlendiren ilan URL'leri desteklenmiyor")
            response.raise_for_status()
            if len(response.content) > 1_000_000:
                raise JobListingError("İlan sayfası çok büyük")
            text = unescape(re.sub(r"<[^>]+>", " ", response.text))
            pieces.append(re.sub(r"\s+", " ", text).strip())
        except JobListingError:
            raise
        except Exception as exc:
            raise JobListingError("İlan URL'si okunamadı; ilan metnini yapıştırın") from exc
    if job_text:
        pieces.append(job_text.strip())
    combined = "\n\n".join(item for item in pieces if item)
    if len(combined) < 40:
        raise JobListingError("Analiz için yeterli ilan metni bulunamadı")
    return combined


def _fail(db: Session, row: JobOpportunity, code: str, message: str) -> JobOpportunity:
    row.status, row.error_code, row.error_message = "failed", code, message[:500]
    db.commit(); db.refresh(row)
    return row


def _apply_fail(db: Session, row: JobOpportunity, message: str) -> JobOpportunity:
    row.apply_status, row.error_message = "failed", message[:500]
    db.commit(); db.refresh(row)
    return row
