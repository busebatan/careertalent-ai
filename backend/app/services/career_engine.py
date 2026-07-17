"""Unified CV analysis and target-plan AI service."""

from __future__ import annotations

import json
import ipaddress
import os
import re
import socket
import subprocess
from copy import deepcopy
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
from app.schemas.career import (
    CareerAnalysisAI,
    CareerAnalysisLocalizationsAI,
    CareerPlanAI,
    CareerPlanLocalizationsAI,
    EvidenceReviewAI,
)
from app.services.ai_factory import AIOutputError, AIProviderError, AIUnavailableError, ai_configured, create_chat_model
from app.services.education_search import TrainingSearchUnavailable, search_training

T = TypeVar("T", bound=BaseModel)
SUPPORTED_PANEL_LOCALES = ("tr", "en")


class CareerLocalizationError(RuntimeError):
    """Raised when stored career content cannot be made panel-locale safe."""


def normalize_panel_locale(locale: str | None) -> str:
    return locale if locale in SUPPORTED_PANEL_LOCALES else "tr"


def _has_complete_localizations(value: object) -> bool:
    return isinstance(value, dict) and all(isinstance(value.get(locale), dict) for locale in SUPPORTED_PANEL_LOCALES)

def _invoke(prompt: str, schema: type[T]) -> T:
    if not ai_configured():
        raise AIUnavailableError("AI sağlayıcısı yapılandırılmamış")
    try:
        contract = prompt + "\n\nZorunlu JSON Schema:\n" + json.dumps(schema.model_json_schema(), ensure_ascii=False)
        messages = [
            SystemMessage(content="Yalnızca verilen JSON Schema ile birebir uyumlu tek JSON nesnesi üret; markdown, kod bloğu veya açıklama ekleme."),
            HumanMessage(content=contract),
        ]
        model = create_chat_model()
        last_output_error: Exception | None = None
        for _attempt in range(2):
            response = model.invoke(messages)
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
            try:
                payload = json.loads(raw)
                return schema.model_validate(payload)
            except (ValidationError, ValueError, TypeError, json.JSONDecodeError) as exc:
                last_output_error = exc
        raise AIOutputError("AI yanıtı beklenen kariyer JSON şemasına uymuyor") from last_output_error
    except (AIUnavailableError, AIOutputError):
        raise
    except Exception as exc:
        raise AIProviderError("AI sağlayıcısından kariyer yanıtı alınamadı") from exc


def _analysis_source(row: CareerAnalysis) -> dict[str, Any]:
    return {
        "current_role": row.current_role,
        "profile": row.profile or {},
        "skills": row.skills or [],
        "radar": row.radar or [],
        "roles": row.career_ladder or [],
    }


def _analysis_localization_prompt(source: dict[str, Any]) -> str:
    return json.dumps({
        "purpose": "Aynı kariyer analizinin Türkçe ve İngilizce gösterim metinlerini üret",
        "rules": [
            "CV'nin dili çıktı dilini belirlemez; tr alanı Türkçe, en alanı İngilizce olmalı",
            "Anlamı, sıra sayısını, rol sayısını ve SWOT madde sayısını değiştirme",
            "Python, SQL, AWS, şirket adları ve sertifika adları gibi özel adları orijinal bırak",
            "Yalnız kullanıcıya gösterilen metinleri çevir; puan veya readiness üretme",
        ],
        "source": source,
    }, ensure_ascii=False)


def _build_analysis_localizations(
    source: dict[str, Any],
    output: CareerAnalysisLocalizationsAI,
) -> dict[str, dict[str, Any]]:
    skills = source.get("skills") if isinstance(source.get("skills"), list) else []
    radar = source.get("radar") if isinstance(source.get("radar"), list) else []
    roles = source.get("roles") if isinstance(source.get("roles"), list) else []
    snapshots: dict[str, dict[str, Any]] = {}

    for locale in SUPPORTED_PANEL_LOCALES:
        localized = getattr(output, locale)
        if len(localized.skill_names) != len(skills):
            raise AIOutputError("Yerelleştirilmiş yetenek sayısı kaynak analizle uyuşmuyor")
        if len(localized.radar_labels) != len(radar):
            raise AIOutputError("Yerelleştirilmiş radar sayısı kaynak analizle uyuşmuyor")
        if len(localized.roles) != len(roles):
            raise AIOutputError("Yerelleştirilmiş rol sayısı kaynak analizle uyuşmuyor")

        localized_roles = []
        for index, role in enumerate(roles):
            if not isinstance(role, dict):
                raise AIOutputError("Kariyer rolü beklenen nesne yapısında değil")
            role_text = localized.roles[index]
            source_swot = role.get("swot") if isinstance(role.get("swot"), dict) else {}
            for field in ("strengths", "weaknesses", "opportunities", "threats"):
                source_items = source_swot.get(field) if isinstance(source_swot.get(field), list) else []
                if len(getattr(role_text, field)) != len(source_items):
                    raise AIOutputError("Yerelleştirilmiş SWOT madde sayısı kaynak analizle uyuşmuyor")
            localized_roles.append({
                "tier": role.get("tier"),
                "title": role_text.title,
                "readiness": role.get("readiness", 0),
                "swot": {
                    "strengths": role_text.strengths,
                    "weaknesses": role_text.weaknesses,
                    "opportunities": role_text.opportunities,
                    "threats": role_text.threats,
                },
            })

        snapshots[locale] = {
            "current_role": localized.current_role,
            "profile": localized.profile,
            "skills": [
                {**item, "name": localized.skill_names[index]}
                for index, item in enumerate(skills)
                if isinstance(item, dict)
            ],
            "radar": [
                {**item, "label": localized.radar_labels[index]}
                for index, item in enumerate(radar)
                if isinstance(item, dict)
            ],
            "career_ladder": localized_roles,
        }

    return snapshots


def _localize_analysis(source: dict[str, Any]) -> dict[str, dict[str, Any]]:
    output = _invoke(_analysis_localization_prompt(source), CareerAnalysisLocalizationsAI)
    return _build_analysis_localizations(source, output)


def _plan_source(target_title: str, tasks: list[dict[str, Any]]) -> dict[str, Any]:
    return {
        "target_title": target_title,
        "tasks": [{
            "id": str(item.get("id", "")),
            "title": str(item.get("title", "")),
            "hint": str(item.get("hint", "")),
            "skill_impacts": item.get("skill_impacts") if isinstance(item.get("skill_impacts"), list) else [],
            "feedback": item.get("feedback") if isinstance(item.get("feedback"), str) else None,
        } for item in tasks],
    }


def _plan_localization_prompt(source: dict[str, Any]) -> str:
    return json.dumps({
        "purpose": "Aynı hedef rol ve görevlerin Türkçe ve İngilizce gösterim metinlerini üret",
        "rules": [
            "tr alanı Türkçe, en alanı İngilizce olmalı",
            "Görev id değerlerini, görev sırasını ve anlamını değiştirme",
            "Python, SQL, AWS, şirket adları ve sertifika adları gibi özel adları orijinal bırak",
            "Eğitim/kurs adlarını çevirme; bunlar bu çeviri sözleşmesinin dışında kalır",
        ],
        "source": source,
    }, ensure_ascii=False)


def _localize_plan(source: dict[str, Any]) -> CareerPlanLocalizationsAI:
    output = _invoke(_plan_localization_prompt(source), CareerPlanLocalizationsAI)
    expected_ids = [str(item.get("id", "")) for item in source.get("tasks", [])]
    for locale in SUPPORTED_PANEL_LOCALES:
        actual_ids = [item.id for item in getattr(output, locale).tasks]
        if actual_ids != expected_ids:
            raise AIOutputError("Yerelleştirilmiş görev kimlikleri kaynak planla uyuşmuyor")
    return output


def ensure_career_localizations(
    db: Session,
    user_id: int,
    *,
    analysis_id: str | None = None,
    target_id: str | None = None,
    include_analysis: bool = True,
    include_targets: bool = True,
) -> None:
    analysis = None
    if include_analysis:
        analysis_query = select(CareerAnalysis).where(
            CareerAnalysis.user_id == user_id,
            CareerAnalysis.status == "ready",
        )
        if analysis_id is not None:
            analysis_query = analysis_query.where(CareerAnalysis.id == analysis_id)
        else:
            analysis_query = analysis_query.order_by(CareerAnalysis.created_at.desc())
        analysis = db.scalar(analysis_query)

    targets: list[CareerTarget] = []
    if include_targets:
        target_query = select(CareerTarget).where(CareerTarget.user_id == user_id)
        if target_id is not None:
            target_query = target_query.where(CareerTarget.id == target_id)
        else:
            target_query = target_query.order_by(CareerTarget.created_at.desc())
        targets = list(db.scalars(target_query).all())

    analysis_work = None
    if analysis is not None and not _has_complete_localizations(analysis.localizations):
        analysis_work = {
            "id": analysis.id,
            "source": deepcopy(_analysis_source(analysis)),
        }

    target_work: list[dict[str, Any]] = []
    for target in targets:
        tasks = list(db.scalars(
            select(CareerTask)
            .where(CareerTask.user_id == user_id, CareerTask.target_id == target.id)
            .order_by(CareerTask.created_at)
        ).all())
        target_missing = not _has_complete_localizations(target.localizations)
        tasks_missing = any(not _has_complete_localizations(task.localizations) for task in tasks)
        if target_missing or tasks_missing:
            target_work.append({
                "id": target.id,
                "source": deepcopy(_plan_source(target.title, [{
                    "id": task.id,
                    "title": task.title,
                    "hint": task.hint,
                    "skill_impacts": task.skill_impacts or [],
                    "feedback": task.feedback,
                } for task in tasks])),
            })

    if analysis_work is None and not target_work:
        return

    db.rollback()
    try:
        analysis_localizations = None
        if analysis_work is not None:
            analysis_localizations = _localize_analysis(analysis_work["source"])

        target_localizations = [
            {
                "id": work["id"],
                "source": work["source"],
                "localized": _localize_plan(work["source"]),
            }
            for work in target_work
        ]

        if analysis_work is not None and analysis_localizations is not None:
            current_analysis = db.scalar(select(CareerAnalysis).where(
                CareerAnalysis.id == analysis_work["id"],
                CareerAnalysis.user_id == user_id,
                CareerAnalysis.status == "ready",
            ))
            if current_analysis is not None and not _has_complete_localizations(current_analysis.localizations):
                if _analysis_source(current_analysis) != analysis_work["source"]:
                    raise CareerLocalizationError("Kariyer analizi çeviri sırasında değişti")
                current_analysis.localizations = analysis_localizations

        for work in target_localizations:
            current_target = db.scalar(select(CareerTarget).where(
                CareerTarget.id == work["id"],
                CareerTarget.user_id == user_id,
            ))
            if current_target is None:
                continue
            current_tasks = list(db.scalars(
                select(CareerTask)
                .where(CareerTask.user_id == user_id, CareerTask.target_id == current_target.id)
                .order_by(CareerTask.created_at)
            ).all())
            if _has_complete_localizations(current_target.localizations) and all(
                _has_complete_localizations(task.localizations) for task in current_tasks
            ):
                continue
            current_source = _plan_source(current_target.title, [{
                "id": task.id,
                "title": task.title,
                "hint": task.hint,
                "skill_impacts": task.skill_impacts or [],
                "feedback": task.feedback,
            } for task in current_tasks])
            if current_source != work["source"]:
                raise CareerLocalizationError("Kariyer planı çeviri sırasında değişti")

            localized = work["localized"]
            current_target.localizations = {
                locale: {
                    "title": getattr(localized, locale).target_title,
                    "task_titles": {
                        item.id: item.title
                        for item in getattr(localized, locale).tasks
                    },
                }
                for locale in SUPPORTED_PANEL_LOCALES
            }
            for locale in SUPPORTED_PANEL_LOCALES:
                by_id = {item.id: item for item in getattr(localized, locale).tasks}
                for task in current_tasks:
                    current = dict(task.localizations or {})
                    item = by_id[task.id]
                    current[locale] = {
                        "title": item.title,
                        "hint": item.hint,
                        "skill_impacts": item.skill_impacts,
                        "feedback": item.feedback,
                    }
                    task.localizations = current
        db.commit()
    except CareerLocalizationError:
        db.rollback()
        raise
    except (AIUnavailableError, AIOutputError, AIProviderError, KeyError) as exc:
        db.rollback()
        raise CareerLocalizationError("Kariyer içerikleri panel diline çevrilemedi") from exc


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
    row_id = row.id
    user_id = row.user_id
    source_kind = row.source
    cv_text = (row.cv_text or "")[:12000]
    row.status = "running"
    db.commit()
    prompt = json.dumps({
        "purpose": "CV ve doğrulanmış kanıtlardan kişiye özel kariyer analizi üret",
        "rules": [
            "CV'nin dili çıktı dilini belirlemez; bu ilk analizde kullanıcıya gösterilen tüm metinleri Türkçe üret",
            "current_role yalnız CV içindeki kronolojik olarak en son iş deneyiminin meslek unvanıdır; deneyim yoksa null",
            "skills ve radar yalnız CV ile accepted_evidence içindeki doğrulanabilir yeteneklere dayanır",
            "radar score mevcut seviyeyi, target seçilebilir kariyer hedefleri için beklenen seviyeyi gösterir",
            "roles toplam 3-15 öğedir ve A, B, C katmanlarının her biri en az bir rol içerir; katman başına birden çok rol üretilebilir",
            "A mevcut yeteneklerle şimdi hazır olunan roller, B kısa gelişimle yakın roller, C ulaşılabilecek en yüksek zirve rollerdir",
            "Her rolün SWOT alanları kişiye ve role özel somut AI analizidir; genel/geçici metin kullanma",
            "Accepted evidence yeni bir yeteneği doğruluyorsa ilgili skill ve radar score değerini yeniden değerlendir",
        ],
        "cv_text": cv_text,
        "accepted_evidence": evidence_context or [],
    }, ensure_ascii=False)
    result: dict[str, Any]
    try:
        output = _invoke(prompt, CareerAnalysisAI)
        data = output.model_dump(mode="json")
        source = {
            "current_role": data["current_role"],
            "profile": data["profile"],
            "skills": data["skills"],
            "radar": data["radar"],
            "roles": data["roles"],
        }
        localizations = _localize_analysis(source)
        canonical = localizations["tr"]
        result = {
            "status": "ready",
            "current_role": canonical["current_role"],
            "profile": canonical["profile"],
            "skills": canonical["skills"],
            "radar": canonical["radar"],
            "career_ladder": canonical["career_ladder"],
            "localizations": localizations,
            "error_code": None,
            "error_message": None,
        }
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        result = {
            "status": "failed",
            "error_code": "ai_unavailable" if isinstance(exc, AIUnavailableError) else ("ai_invalid_output" if isinstance(exc, AIOutputError) else "ai_provider_error"),
            "error_message": str(exc)[:500],
        }

    persisted = db.scalar(select(CareerAnalysis).where(CareerAnalysis.id == row_id))
    if persisted is None:
        return row
    for field, value in result.items():
        setattr(persisted, field, value)
    if result["status"] == "ready" and source_kind in {"archive_uploaded", "archive_generated"}:
        _delete_target_plan(db, user_id)
    db.commit()
    db.refresh(persisted)
    return persisted


def select_target(db: Session, user_id: int, title: str, source: str, job_url: str | None) -> CareerTarget:
    now = datetime.now(timezone.utc)
    db.execute(update(CareerTarget).where(CareerTarget.user_id == user_id, CareerTarget.status.in_(["queued", "ready", "active"])).values(status="closed", closed_at=now))
    owner = db.get(User, user_id)
    locale = normalize_panel_locale(owner.preferred_locale if owner else "tr")
    target = CareerTarget(
        id=str(uuid4()),
        user_id=user_id,
        title=title,
        source=source,
        job_url=job_url,
        status="queued",
        localizations={locale: {"title": title}},
    )
    db.add(target)
    db.commit()
    db.refresh(target)
    return target


def plan_target(db: Session, target: CareerTarget) -> CareerTarget:
    target_id = target.id
    user_id = target.user_id
    target_title = target.title
    if target.status != "queued":
        return target
    analysis = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == user_id, CareerAnalysis.status == "ready").order_by(CareerAnalysis.created_at.desc()))
    if analysis is None:
        target.status = "failed"
        target.plan = {"error_code": "analysis_required"}
        db.commit()
        return target
    analysis_context = {
        "current_role": analysis.current_role,
        "skills": deepcopy(analysis.skills or []),
        "radar": deepcopy(analysis.radar or []),
    }
    prompt = json.dumps({
        "purpose": "Seçilen mesleğe ulaşmak için kişiye özel görev ve eğitim planı üret",
        "target_role": target_title,
        "current_role": analysis_context["current_role"],
        "skills": analysis_context["skills"],
        "radar": analysis_context["radar"],
        "rules": [
            "target_title, görev title, hint ve diğer kullanıcı metinlerini Türkçe üret; CV veya hedef rolün giriş dili bunu değiştirmez",
            "Görevler somut, ölçülebilir ve hedef role doğrudan bağlı olmalı",
            "Kanıt gerektiren görevde evidence_required true ve uygun link/file türleri olmalı",
            "skill_impacts yalnız görev tamamlandığında yeniden puanlanacak yetenekleri içermeli",
            "Eğitim gerektiğinde training_queries içine hedef rol ve yeteneği içeren özgül web arama sorgusu yaz",
            "Eğitim gerekmeyen görevde training_queries boş olmalı",
            "Sorgular sertifika, resmi eğitim, kurs veya uygulamalı öğrenme kaynağı bulmaya uygun olmalı",
            "training_queries.query alanını İngilizce yaz; eğitim sonucu başlığı bulunduğu orijinal dilde kalacak",
        ],
    }, ensure_ascii=False)
    db.rollback()
    try:
        output = _invoke(prompt, CareerPlanAI)
        current_target = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id))
        if current_target is None or current_target.status != "queued":
            return current_target or target
        db.rollback()
        localized = _localize_plan(_plan_source(output.target_title, [{
            "id": str(index),
            "title": item.title,
            "hint": item.hint,
            "skill_impacts": item.skill_impacts,
            "feedback": None,
        } for index, item in enumerate(output.tasks)]))
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
        target = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id))
        if target is None:
            return current_target
        if target.status != "queued":
            return target
        tasks = []
        target.title = localized.tr.target_title
        target_localizations = {
            locale: {
                "title": getattr(localized, locale).target_title,
                "task_titles": {},
            }
            for locale in SUPPORTED_PANEL_LOCALES
        }
        for index, (item, suggestions) in enumerate(task_specs):
            text_by_locale = {
                locale: getattr(localized, locale).tasks[index]
                for locale in SUPPORTED_PANEL_LOCALES
            }
            task = CareerTask(
                id=str(uuid4()),
                user_id=target.user_id,
                target_id=target.id,
                title=text_by_locale["tr"].title,
                hint=text_by_locale["tr"].hint,
                status="pending",
                evidence_required=item.evidence_required,
                evidence_types=item.evidence_types,
                skill_impacts=text_by_locale["tr"].skill_impacts,
                training_suggestions=suggestions,
                localizations={
                    locale: {
                        "title": text_by_locale[locale].title,
                        "hint": text_by_locale[locale].hint,
                        "skill_impacts": text_by_locale[locale].skill_impacts,
                        "feedback": text_by_locale[locale].feedback,
                    }
                    for locale in SUPPORTED_PANEL_LOCALES
                },
            )
            db.add(task)
            for locale in SUPPORTED_PANEL_LOCALES:
                target_localizations[locale]["task_titles"][task.id] = text_by_locale[locale].title
            tasks.append({"id": task.id, "title": task.title, "training_suggestions": suggestions})
        target.localizations = target_localizations
        target.plan = {"tasks": tasks, "training_search": {"provider": settings.EDUCATION_SEARCH_PROVIDER, "status": "ready" if not search_errors else "partial", "errors": search_errors[:3]}}
        target.status = "active"
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        db.rollback()
        target = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id))
        if target is None:
            return current_target if "current_target" in locals() else target
        if target.status != "queued":
            return target
        target.status = "failed"
        target.plan = {"error_code": "ai_unavailable" if isinstance(exc, AIUnavailableError) else "ai_error", "message": str(exc)[:500]}
    db.commit()
    db.refresh(target)
    return target


def serialize_analysis(row: CareerAnalysis, locale: str = "tr") -> dict[str, Any]:
    locale = normalize_panel_locale(locale)
    snapshot = (row.localizations or {}).get(locale)
    if not isinstance(snapshot, dict):
        snapshot = {
            "current_role": row.current_role,
            "profile": row.profile or {},
            "skills": row.skills or [],
            "radar": row.radar or [],
            "career_ladder": row.career_ladder or [],
        }
    return {
        "id": row.id,
        "status": row.status,
        "source": row.source,
        "file_name": row.file_name,
        "cv_document_id": row.cv_document_id,
        "current_role": snapshot.get("current_role"),
        "profile": snapshot.get("profile") or {},
        "skills": snapshot.get("skills") or [],
        "radar": snapshot.get("radar") or [],
        "career_ladder": snapshot.get("career_ladder") or [],
        "error_code": row.error_code,
        "error_message": row.error_message,
        "created_at": (row.created_at or datetime.now(timezone.utc)).isoformat(),
        "locale": locale,
    }


def serialize_target(row: CareerTarget, locale: str = "tr") -> dict[str, Any]:
    locale = normalize_panel_locale(locale)
    localized = (row.localizations or {}).get(locale)
    title = localized.get("title") if isinstance(localized, dict) else row.title
    plan = deepcopy(row.plan or {})
    task_titles = localized.get("task_titles") if isinstance(localized, dict) else None
    if isinstance(task_titles, dict) and isinstance(plan.get("tasks"), list):
        plan["tasks"] = [
            {**item, "title": task_titles.get(str(item.get("id")), item.get("title"))}
            if isinstance(item, dict) else item
            for item in plan["tasks"]
        ]
    return {"id": row.id, "title": title, "source": row.source, "status": row.status, "plan": plan, "created_at": (row.created_at or datetime.now(timezone.utc)).isoformat(), "locale": locale}


def serialize_task(row: CareerTask, db: Session | None = None, locale: str = "tr") -> dict[str, Any]:
    locale = normalize_panel_locale(locale)
    localized = (row.localizations or {}).get(locale)
    text = localized if isinstance(localized, dict) else {}
    has_evidence = evidence_verified = evidence_pending = False
    if db is not None:
        evidence_rows = db.scalars(select(Evidence).where(Evidence.task_id == row.id)).all()
        has_evidence = len(evidence_rows) > 0
        evidence_verified = any(item.status == "accepted" for item in evidence_rows)
        evidence_pending = any(item.status == "pending" for item in evidence_rows)
    return {
        "id": row.id,
        "target_id": row.target_id,
        "title": text.get("title", row.title),
        "hint": text.get("hint", row.hint),
        "note": row.note or "",
        "status": row.status,
        "evidence_required": row.evidence_required,
        "evidence_types": row.evidence_types or [],
        "skill_impacts": text.get("skill_impacts", row.skill_impacts or []),
        "training_suggestions": row.training_suggestions or [],
        "feedback": text.get("feedback", row.feedback),
        "has_evidence": has_evidence,
        "evidence_verified": evidence_verified,
        "evidence_pending": evidence_pending,
        "locale": locale,
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
    needle = skill.strip().casefold()
    if needle == "":
        return None
    rows = db.scalars(select(CareerTask).where(CareerTask.user_id == user_id, CareerTask.target_id == target_id)).all()
    for row in rows:
        impacts = [str(item).casefold() for item in (row.skill_impacts or [])]
        for localized in (row.localizations or {}).values():
            if isinstance(localized, dict):
                impacts.extend(str(item).casefold() for item in (localized.get("skill_impacts") or []))
        if needle in impacts:
            return row
    return None


def _localized_skill_names(db: Session, user_id: int, skill: str) -> dict[str, str]:
    fallback = skill.strip()
    names = {locale: fallback for locale in SUPPORTED_PANEL_LOCALES}
    needle = fallback.casefold()
    analysis = db.scalar(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id == user_id, CareerAnalysis.status == "ready")
        .order_by(CareerAnalysis.created_at.desc())
    )
    if analysis is None or not _has_complete_localizations(analysis.localizations):
        return names

    for collection, key in (("skills", "name"), ("radar", "label")):
        localized_items = {
            locale: (analysis.localizations[locale].get(collection) or [])
            for locale in SUPPORTED_PANEL_LOCALES
        }
        count = min(len(localized_items[locale]) for locale in SUPPORTED_PANEL_LOCALES)
        for index in range(count):
            values = {
                locale: str(localized_items[locale][index].get(key, "")).strip()
                for locale in SUPPORTED_PANEL_LOCALES
                if isinstance(localized_items[locale][index], dict)
            }
            if any(value.casefold() == needle for value in values.values()):
                return {locale: values.get(locale) or fallback for locale in SUPPORTED_PANEL_LOCALES}
    return names


def ensure_skill_evidence_task(db: Session, user_id: int, target_id: str, skill: str) -> CareerTask:
    existing = _find_skill_task(db, user_id, target_id, skill)
    if existing is not None:
        return existing
    target = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id, CareerTarget.user_id == user_id))
    if target is None:
        raise ValueError("Hedef bulunamadı")
    skill_names = _localized_skill_names(db, user_id, skill)
    localizations = {
        "tr": {
            "title": f"{skill_names['tr']} kanıtı",
            "hint": f"{skill_names['tr']} yeteneğini kanıtlayan GitHub, sertifika, portföy veya doğrulanabilir bağlantı yükle.",
            "skill_impacts": [skill_names["tr"]],
            "feedback": None,
        },
        "en": {
            "title": f"{skill_names['en']} evidence",
            "hint": f"Upload a GitHub link, certificate, portfolio, or verifiable URL that proves your {skill_names['en']} skill.",
            "skill_impacts": [skill_names["en"]],
            "feedback": None,
        },
    }
    task = CareerTask(
        id=str(uuid4()),
        user_id=user_id,
        target_id=target_id,
        title=localizations["tr"]["title"],
        hint=localizations["tr"]["hint"],
        status="pending",
        evidence_required=False,
        evidence_types=["link", "file"],
        skill_impacts=localizations["tr"]["skill_impacts"],
        training_suggestions=[],
        localizations=localizations,
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
    localizations = deepcopy(task.localizations or {})
    for localized in localizations.values():
        if isinstance(localized, dict):
            localized["feedback"] = None
    task.localizations = localizations
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
        task.localizations = {}
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
        task.localizations = {}
    except (AIUnavailableError, AIOutputError, AIProviderError) as exc:
        evidence.status = "revision_required"
        evidence.confidence = 0
        evidence.feedback = str(exc)[:500]
        task.status = "revision_required"
        task.feedback = evidence.feedback
        task.localizations = {}
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
