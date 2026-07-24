"""AI destekli dış CV -> CV Merkezi alanları aktarımı."""

from __future__ import annotations

import json
import re
import unicodedata
from pathlib import Path
from typing import Any
from uuid import uuid4

from sqlalchemy.orm import Session

from app.models.career_engine import CareerAnalysis
from app.models.engagement import CvDocument
from app.schemas.career import CvBuilderDraftAI, CvBuilderSourceDraftAI
from app.services.ai_factory import AIOutputError
from app.services.career_engine import _invoke
from app.services.cv_parser import extract_text_from_pdf


_BUILDER_SECTIONS = ("education", "experience", "skills", "projects", "certificates")
_PERSONAL_FIELDS = ("full_name", "email", "phone", "location", "linkedin", "summary")


def _source_prompt(document: CvDocument, analysis: CareerAnalysis, cv_text: str) -> dict[str, Any]:
    """Build the extraction contract sent to the career engine."""

    return {
        "purpose": "Kaynak CV dilini belirle ve metni o dilde CV Merkezi alanlarına birebir aktar",
        "rules": [
            "source_language değerini CV'nin baskın doğal anlatım diline göre tr veya en seç",
            "Yalnızca kaynak CV metninde açıkça bulunan gerçekleri kullan",
            "Kişi, kurum, tarih, eğitim, deneyim, proje, sertifika, iletişim bilgisi ve beceri uydurma",
            "Kaynakta bulunmayan başarı, sayı, görev, süre, teknoloji veya iletişim bilgisi ekleme",
            "Belirsiz veya eksik alanları boş string, boş liste ya da boş nesne olarak bırak",
            "Kaynakta olmayan bir alanı başka bir alandan tahmin ederek doldurma",
            "Tüm alanları CV Merkezi kutucuklarına uygun şekilde döndür",
            "Kaynak CV'deki doğal dilde yazılmış metinleri çevirmeden, özetlemeden ve yeniden ifade etmeden koru",
            "Ad, kurum, marka, teknoloji, kısaltma, tarih, e-posta, telefon ve URL değerlerini birebir koru",
        ],
        "source": {
            "file_name": document.original_name or document.display_name,
            "analysis_id": analysis.id,
            "cv_text": cv_text[:30000],
        },
    }


def _translation_prompt(source_language: str, target_language: str, source: CvBuilderDraftAI) -> dict[str, Any]:
    return {
        "purpose": "Yapılandırılmış kaynak CV'yi karşı dile eksiksiz çevir",
        "source_language": source_language,
        "target_language": target_language,
        "rules": [
            "Tüm doğal anlatım alanlarını hedef dilde yaz",
            "Hiçbir gerçek, satır, madde veya alan ekleme, silme, birleştirme ya da yeniden sıralama",
            "Boş alanları boş bırak; kaynakta olmayan bilgi üretme",
            "Ad, kurum, marka, teknoloji, kısaltma, tarih, e-posta, telefon ve URL değerlerini birebir koru",
            "Doğal dildeki unvan, özet, açıklama, madde, kategori, proje ve sertifika adlarını çevir",
            "Tescilli ürün, proje veya sertifika adlarını özel ad olarak koru",
        ],
        "source_draft": source.model_dump(mode="json"),
    }


def _sanitize_source_fidelity(source: CvBuilderDraftAI, cv_text: str) -> CvBuilderDraftAI:
    """Clear AI-inferred source values while keeping the rest of the draft usable."""

    def comparable(value: str) -> str:
        normalized = unicodedata.normalize("NFKC", value).casefold()
        return " ".join(re.findall(r"\w+", normalized, flags=re.UNICODE))

    comparable_cv = comparable(cv_text)

    def sanitize(value: Any, path: str) -> Any:
        # Skill category is an editor grouping label, not a candidate fact.
        # The AI may derive "Technical Skills" from an unlabelled SQL/Python list.
        if path.startswith("draft.skills[") and path.endswith("].category"):
            return value
        if isinstance(value, dict):
            return {key: sanitize(item, f"{path}.{key}") for key, item in value.items()}
        if isinstance(value, list):
            items = [sanitize(item, f"{path}[{index}]") for index, item in enumerate(value)]
            return [item for item in items if item != ""]
        if isinstance(value, str) and value.strip() and comparable(value) not in comparable_cv:
            return ""
        return value

    payload = sanitize(source.model_dump(mode="json"), "draft")
    meaningful_fields = {
        "education": ("institution", "degree", "details"),
        "experience": ("organization", "title", "bullets"),
        "skills": ("items",),
        "projects": ("name", "link", "description"),
        "certificates": ("name", "issuer"),
    }
    for section, fields in meaningful_fields.items():
        payload[section] = [
            row for row in payload[section]
            if any(row.get(field) for field in fields)
        ]
    return CvBuilderDraftAI.model_validate(payload)


def _validate_translation(source: CvBuilderDraftAI, translated: CvBuilderDraftAI) -> None:
    """Reject structural drift and identity mutations in the translated draft."""

    source_payload = source.model_dump(mode="json")
    translated_payload = translated.model_dump(mode="json")

    def validate_shape(source_value: Any, target_value: Any, path: str) -> None:
        if isinstance(source_value, dict):
            for key, value in source_value.items():
                validate_shape(value, target_value.get(key), f"{path}.{key}")
            return
        if isinstance(source_value, list):
            if not isinstance(target_value, list) or len(source_value) != len(target_value):
                raise AIOutputError(f"CV çevirisi kaynak satır yapısını değiştirdi: {path}")
            for index, value in enumerate(source_value):
                validate_shape(value, target_value[index], f"{path}[{index}]")
            return
        if isinstance(source_value, str) and not source_value.strip() and target_value:
            raise AIOutputError(f"CV çevirisi kaynakta olmayan alanı doldurdu: {path}")

    validate_shape(source_payload, translated_payload, "draft")

    identity_fields = ("full_name", "email", "phone", "linkedin")
    for field in identity_fields:
        if source_payload["personal"][field] != translated_payload["personal"][field]:
            raise AIOutputError(f"CV çevirisi kimlik alanını değiştirdi: personal.{field}")

    preserved_by_section = {
        "education": ("institution", "start", "end"),
        "experience": ("organization", "start", "end"),
        "projects": ("link",),
        "certificates": ("issuer", "date"),
    }
    for section, fields in preserved_by_section.items():
        for index, source_row in enumerate(source_payload[section]):
            translated_row = translated_payload[section][index]
            for field in fields:
                if source_row[field] != translated_row[field]:
                    raise AIOutputError(f"CV çevirisi sabit alanı değiştirdi: {section}[{index}].{field}")


def _normalize_payload(output: CvBuilderDraftAI) -> tuple[dict[str, Any], list[str]]:
    payload = output.model_dump(mode="json")

    # The editor owns row identity. Never trust/generated IDs from an AI payload.
    for section in _BUILDER_SECTIONS:
        rows = payload.get(section)
        if not isinstance(rows, list):
            rows = []
        for row in rows:
            if isinstance(row, dict):
                row["id"] = str(uuid4())
        payload[section] = rows

    payload["enabledOptional"] = []
    payload["optional"] = {}

    missing: list[str] = []
    personal = payload.get("personal")
    if not isinstance(personal, dict):
        personal = {field: "" for field in _PERSONAL_FIELDS}
        payload["personal"] = personal
    for field in _PERSONAL_FIELDS:
        value = personal.get(field, "")
        if isinstance(value, str):
            if not value.strip():
                personal[field] = ""
                missing.append(f"personal.{field}")
            else:
                personal[field] = value
        elif value is None:
            personal[field] = ""
            missing.append(f"personal.{field}")

    for section in _BUILDER_SECTIONS:
        rows = payload[section]
        row_has_blank = any(
            any(
                value is None
                or (isinstance(value, str) and not value.strip())
                or (isinstance(value, list) and not value)
                for key, value in row.items()
                if key != "id"
            )
            for row in rows
            if isinstance(row, dict)
        )
        if not rows or row_has_blank:
            missing.append(section)

    return payload, missing


def import_cv_to_builder(
    db: Session,
    document: CvDocument,
    analysis: CareerAnalysis,
) -> CvDocument:
    """Extract a ready CV analysis into bilingual, editor-ready builder data.

    The document is the uploaded PDF and is deliberately never replaced or made
    current by this operation. Only its builder draft fields are changed.
    """

    if document.kind != "uploaded":
        raise ValueError("Yalnız yüklenen PDF CV oluşturucuya aktarılabilir")
    if analysis.status != "ready":
        raise ValueError("CV analizi hazır değil")
    if document.user_id != analysis.user_id:
        raise ValueError("CV belgesi ve analiz aynı kullanıcıya ait değil")
    if analysis.cv_document_id is not None and analysis.cv_document_id != document.id:
        raise ValueError("CV belgesi ve analiz eşleşmiyor")

    try:
        raw_cv_text = extract_text_from_pdf(Path(document.file_path).read_bytes(), anonymize=False)
        if not raw_cv_text.strip():
            raise ValueError("Kaynak PDF metni boş")

        source_output = _invoke(
            json.dumps(_source_prompt(document, analysis, raw_cv_text), ensure_ascii=False),
            CvBuilderSourceDraftAI,
            language="cv_source",
        )
        if not isinstance(source_output, CvBuilderSourceDraftAI):
            source_output = CvBuilderSourceDraftAI.model_validate(source_output)
        source_draft = _sanitize_source_fidelity(source_output.draft, raw_cv_text)

        source_language = source_output.source_language
        target_language = "en" if source_language == "tr" else "tr"
        translated_output = _invoke(
            json.dumps(
                _translation_prompt(source_language, target_language, source_draft),
                ensure_ascii=False,
            ),
            CvBuilderDraftAI,
            language=f"cv_{target_language}",
        )
        if not isinstance(translated_output, CvBuilderDraftAI):
            translated_output = CvBuilderDraftAI.model_validate(translated_output)
        _validate_translation(source_draft, translated_output)

        localized: dict[str, dict[str, Any]] = {}
        missing_fields: dict[str, list[str]] = {}
        localized[source_language], missing_fields[source_language] = _normalize_payload(source_draft)
        localized[target_language], missing_fields[target_language] = _normalize_payload(translated_output)

        localized["_meta"] = {
            "source_document_id": document.id,
            "source_analysis_id": analysis.id,
            "source_file_name": analysis.file_name or document.original_name or document.display_name,
            "source_language": source_language,
            "missing_fields": missing_fields,
        }
        document.builder_data = localized
        document.builder_draft_analysis_id = analysis.id
        document.builder_draft_status = "ready"
        document.builder_draft_error = None
        db.commit()
        db.refresh(document)
        return document
    except Exception as exc:
        # Keep the uploaded PDF and its active/current flags untouched. Persist
        # only draft state so a later retry can use the same source safely.
        document.builder_draft_status = "failed"
        document.builder_draft_error = "CV alanları AI ile hazırlanamadı. Lütfen tekrar deneyin."
        db.commit()
        raise
