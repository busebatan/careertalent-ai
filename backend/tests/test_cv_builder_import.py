from collections.abc import Generator
import json

import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker
from sqlalchemy.pool import StaticPool

from app.core.database import Base
from app.models.career_engine import CareerAnalysis
from app.models.engagement import CvDocument
from app.schemas.career import CvBuilderDraftAI, CvBuilderSourceDraftAI
from app.services import cv_builder_import as service


@pytest.fixture()
def db() -> Generator[Session, None, None]:
    engine = create_engine("sqlite://", connect_args={"check_same_thread": False}, poolclass=StaticPool)
    Base.metadata.create_all(engine)
    session = sessionmaker(bind=engine)()
    try:
        yield session
    finally:
        session.close()
        Base.metadata.drop_all(engine)


def _source(db: Session) -> tuple[CvDocument, CareerAnalysis]:
    document = CvDocument(
        id="uploaded-cv-1",
        user_id=1,
        kind="uploaded",
        display_name="Buse Batan.pdf",
        original_name="Buse Batan.pdf",
        file_path="/tmp/uploaded-cv-1.pdf",
        file_size=123,
        is_current=True,
    )
    analysis = CareerAnalysis(
        id="analysis-1",
        user_id=1,
        cv_document_id=document.id,
        status="ready",
        source="upload",
        file_name=document.original_name,
        cv_text="Buse Batan; Veri Analisti; SQL ve Python; İstanbul.",
    )
    db.add_all([document, analysis])
    db.commit()
    return document, analysis


def _draft(
    *,
    full_name: str = "Jane Doe",
    location: str,
    summary: str,
    title: str,
    bullet: str,
    skill_category: str,
) -> CvBuilderDraftAI:
    return CvBuilderDraftAI(
        personal={
            "full_name": full_name,
            "email": "jane@example.com",
            "phone": "+90 555 111 22 33",
            "location": location,
            "linkedin": "https://linkedin.com/in/janedoe",
            "summary": summary,
        },
        education=[],
        experience=[{
            "organization": "Google LLC",
            "title": title,
            "start": "2022",
            "end": "2024",
            "bullets": [bullet],
        }],
        skills=[{"category": skill_category, "items": "SQL, Python"}],
        projects=[],
        certificates=[],
    )


def _draft_source_text(draft: CvBuilderDraftAI) -> str:
    strings: list[str] = []

    def collect(value):
        if isinstance(value, dict):
            for item in value.values():
                collect(item)
        elif isinstance(value, list):
            for item in value:
                collect(item)
        elif isinstance(value, str) and value:
            strings.append(value)

    collect(draft.model_dump(mode="json"))
    return "\n".join(strings)


@pytest.mark.parametrize(
    ("source_language", "source_draft", "translated_draft", "target_language"),
    [
        (
            "en",
            _draft(
                location="Istanbul",
                summary="Senior data analyst focused on customer analytics.",
                title="Senior Data Analyst",
                bullet="Built SQL dashboards for customer analytics.",
                skill_category="Technical",
            ),
            _draft(
                location="İstanbul",
                summary="Müşteri analitiğine odaklanan kıdemli veri analisti.",
                title="Kıdemli Veri Analisti",
                bullet="Müşteri analitiği için SQL panoları oluşturdu.",
                skill_category="Teknik",
            ),
            "tr",
        ),
        (
            "tr",
            _draft(
                location="İstanbul",
                summary="Müşteri analitiğine odaklanan kıdemli veri analisti.",
                title="Kıdemli Veri Analisti",
                bullet="Müşteri analitiği için SQL panoları oluşturdu.",
                skill_category="Teknik",
            ),
            _draft(
                location="Istanbul",
                summary="Senior data analyst focused on customer analytics.",
                title="Senior Data Analyst",
                bullet="Built SQL dashboards for customer analytics.",
                skill_category="Technical",
            ),
            "en",
        ),
    ],
)
def test_import_preserves_source_locale_and_translates_opposite_locale(
    db,
    monkeypatch,
    source_language,
    source_draft,
    translated_draft,
    target_language,
):
    document, analysis = _source(db)
    raw_cv_text = _draft_source_text(source_draft)
    extraction_calls: list[bool] = []
    calls: list[tuple[dict, type, str]] = []

    def fake_extract(data, anonymize=True):
        assert data == b"%PDF raw source"
        extraction_calls.append(anonymize)
        return raw_cv_text

    def fake_invoke(prompt, schema, language="tr"):
        parsed = json.loads(prompt)
        calls.append((parsed, schema, language))
        if schema is CvBuilderSourceDraftAI:
            return CvBuilderSourceDraftAI(source_language=source_language, draft=source_draft)
        assert schema is CvBuilderDraftAI
        return translated_draft

    monkeypatch.setattr(service.Path, "read_bytes", lambda _path: b"%PDF raw source")
    monkeypatch.setattr(service, "extract_text_from_pdf", fake_extract)
    monkeypatch.setattr(service, "_invoke", fake_invoke)

    result = service.import_cv_to_builder(db, document, analysis)

    assert extraction_calls == [False]
    assert [call[2] for call in calls] == ["cv_source", f"cv_{target_language}"]
    assert calls[0][0]["source"]["cv_text"] == raw_cv_text
    assert calls[1][0]["source_language"] == source_language
    assert calls[1][0]["target_language"] == target_language
    assert calls[1][0]["source_draft"]["personal"]["summary"] == source_draft.personal.summary
    assert result.builder_draft_status == "ready"
    assert result.builder_draft_error is None
    assert result.builder_draft_analysis_id == analysis.id
    assert result.is_current is True
    assert result.builder_data[source_language]["personal"]["summary"] == source_draft.personal.summary
    assert result.builder_data[target_language]["personal"]["summary"] == translated_draft.personal.summary
    assert result.builder_data["tr"]["personal"]["full_name"] == "Jane Doe"
    assert result.builder_data["en"]["personal"]["full_name"] == "Jane Doe"
    assert result.builder_data["tr"]["personal"]["email"] == "jane@example.com"
    assert result.builder_data["en"]["personal"]["email"] == "jane@example.com"
    assert result.builder_data["tr"]["experience"][0]["organization"] == "Google LLC"
    assert result.builder_data["en"]["experience"][0]["organization"] == "Google LLC"
    assert result.builder_data["tr"]["skills"][0]["items"] == "SQL, Python"
    assert result.builder_data["en"]["skills"][0]["items"] == "SQL, Python"
    assert result.builder_data["tr"]["experience"][0]["id"]
    assert result.builder_data["tr"]["enabledOptional"] == []
    assert result.builder_data["tr"]["optional"] == {}
    assert result.builder_data["_meta"]["source_language"] == source_language
    assert result.builder_data["_meta"]["source_document_id"] == document.id
    assert result.builder_data["_meta"]["source_analysis_id"] == analysis.id
    assert result.builder_data["_meta"]["source_file_name"] == "Buse Batan.pdf"


def test_import_preserves_empty_values_and_rows_are_independently_identified(db, monkeypatch):
    document, analysis = _source(db)
    source = _draft(
        location="İstanbul",
        summary="Veri analisti",
        title="Veri Analisti",
        bullet="SQL kullandı.",
        skill_category="Teknik",
    )
    source.personal.email = ""
    source.personal.linkedin = ""
    translated = _draft(
        location="Istanbul",
        summary="Data analyst",
        title="Data Analyst",
        bullet="Used SQL.",
        skill_category="Technical",
    )
    translated.personal.email = ""
    translated.personal.linkedin = ""

    monkeypatch.setattr(service.Path, "read_bytes", lambda _path: b"%PDF")
    monkeypatch.setattr(
        service,
        "extract_text_from_pdf",
        lambda _data, anonymize=True: _draft_source_text(source),
    )
    monkeypatch.setattr(
        service,
        "_invoke",
        lambda _prompt, schema, language="tr": (
            CvBuilderSourceDraftAI(source_language="tr", draft=source)
            if schema is CvBuilderSourceDraftAI
            else translated
        ),
    )

    service.import_cv_to_builder(db, document, analysis)
    payload = document.builder_data

    assert payload["tr"]["personal"]["email"] == ""
    assert payload["tr"]["personal"]["linkedin"] == ""
    assert payload["tr"]["education"] == []
    assert payload["tr"]["projects"] == []
    assert payload["tr"]["certificates"] == []
    assert payload["tr"]["experience"][0]["id"] != payload["en"]["experience"][0]["id"]


def test_import_failure_marks_draft_failed_without_touching_uploaded_document(db, monkeypatch):
    document, analysis = _source(db)
    old_data = {"legacy": "keep"}
    document.builder_data = old_data
    db.commit()

    def unavailable(*_args, **_kwargs):
        raise RuntimeError("provider unavailable")

    monkeypatch.setattr(service.Path, "read_bytes", lambda _path: b"%PDF")
    monkeypatch.setattr(service, "extract_text_from_pdf", lambda _data, anonymize=True: "Source CV text")
    monkeypatch.setattr(service, "_invoke", unavailable)

    with pytest.raises(RuntimeError, match="provider unavailable"):
        service.import_cv_to_builder(db, document, analysis)

    db.expire_all()
    persisted = db.get(CvDocument, document.id)
    assert persisted.builder_draft_status == "failed"
    assert persisted.builder_draft_error == "CV alanları AI ile hazırlanamadı. Lütfen tekrar deneyin."
    assert persisted.builder_data == old_data
    assert persisted.file_path == "/tmp/uploaded-cv-1.pdf"
    assert persisted.is_current is True


def test_import_rejects_translation_that_changes_identity_data(db, monkeypatch):
    document, analysis = _source(db)
    old_data = {"legacy": "keep"}
    document.builder_data = old_data
    db.commit()
    source = _draft(
        location="Istanbul",
        summary="Senior data analyst.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical",
    )
    translated = _draft(
        location="İstanbul",
        summary="Kıdemli veri analisti.",
        title="Kıdemli Veri Analisti",
        bullet="SQL panoları oluşturdu.",
        skill_category="Teknik",
    )
    translated.experience[0].organization = "Invented Company"

    monkeypatch.setattr(service.Path, "read_bytes", lambda _path: b"%PDF")
    monkeypatch.setattr(
        service,
        "extract_text_from_pdf",
        lambda _data, anonymize=True: _draft_source_text(source),
    )
    monkeypatch.setattr(
        service,
        "_invoke",
        lambda _prompt, schema, language="tr": (
            CvBuilderSourceDraftAI(source_language="en", draft=source)
            if schema is CvBuilderSourceDraftAI
            else translated
        ),
    )

    with pytest.raises(service.AIOutputError, match="experience\\[0\\]\\.organization"):
        service.import_cv_to_builder(db, document, analysis)

    db.expire_all()
    persisted = db.get(CvDocument, document.id)
    assert persisted.builder_draft_status == "failed"
    assert persisted.builder_data == old_data
    assert persisted.file_path == "/tmp/uploaded-cv-1.pdf"
    assert persisted.is_current is True


def test_import_rejects_source_wording_not_present_in_pdf(db, monkeypatch):
    document, analysis = _source(db)
    old_data = {"legacy": "keep"}
    document.builder_data = old_data
    db.commit()
    source = _draft(
        location="Istanbul",
        summary="Invented executive summary.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical",
    )
    calls = []
    raw_cv_text = _draft_source_text(source).replace(source.personal.summary, "")

    monkeypatch.setattr(service.Path, "read_bytes", lambda _path: b"%PDF")
    monkeypatch.setattr(
        service,
        "extract_text_from_pdf",
        lambda _data, anonymize=True: raw_cv_text,
    )

    def fake_invoke(_prompt, schema, language="tr"):
        calls.append(schema)
        return CvBuilderSourceDraftAI(source_language="en", draft=source)

    monkeypatch.setattr(service, "_invoke", fake_invoke)

    with pytest.raises(service.AIOutputError, match="draft\\.personal\\.summary"):
        service.import_cv_to_builder(db, document, analysis)

    assert calls == [CvBuilderSourceDraftAI]
    db.expire_all()
    persisted = db.get(CvDocument, document.id)
    assert persisted.builder_draft_status == "failed"
    assert persisted.builder_data == old_data


def test_source_fidelity_allows_ai_to_group_skills_under_a_category():
    source = _draft(
        location="Istanbul",
        summary="Senior data analyst.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical Skills",
    )
    raw_cv_text = _draft_source_text(source).replace("Technical Skills", "")

    service._validate_source_fidelity(source, raw_cv_text)
