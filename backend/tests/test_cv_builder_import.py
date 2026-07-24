from collections.abc import Generator
import json

import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker
from sqlalchemy.pool import StaticPool

from app.core.database import Base
from app.models.career_engine import CareerAnalysis
from app.models.engagement import CvDocument
from app.schemas.career import (
    CvBuilderDraftAI,
    CvBuilderSourceDraftAI,
    CvCertificateDraftAI,
    CvProjectDraftAI,
)
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
    extraction_calls: list[tuple[bool, bool]] = []
    calls: list[tuple[dict, type, str]] = []

    def fake_extract(data, anonymize=True, layout=False):
        assert data == b"%PDF raw source"
        extraction_calls.append((anonymize, layout))
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

    assert extraction_calls == [(False, True)]
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
        lambda _data, anonymize=True, layout=False: _draft_source_text(source),
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
    monkeypatch.setattr(
        service,
        "extract_text_from_pdf",
        lambda _data, anonymize=True, layout=False: "Source CV text",
    )
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
        lambda _data, anonymize=True, layout=False: _draft_source_text(source),
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


def test_source_fidelity_clears_wording_not_present_in_pdf():
    source = _draft(
        location="Istanbul",
        summary="Invented executive summary.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical",
    )
    raw_cv_text = (
        _draft_source_text(source)
        .replace(source.personal.summary, "")
        .replace(source.personal.location, "")
    )

    sanitized = service._sanitize_source_fidelity(source, raw_cv_text)

    assert sanitized.personal.summary == ""
    assert sanitized.personal.location == ""
    assert sanitized.personal.full_name == "Jane Doe"
    assert sanitized.experience[0].title == "Senior Data Analyst"


def test_source_fidelity_allows_ai_to_group_skills_under_a_category():
    source = _draft(
        location="Istanbul",
        summary="Senior data analyst.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical Skills",
    )
    raw_cv_text = _draft_source_text(source).replace("Technical Skills", "")

    sanitized = service._sanitize_source_fidelity(source, raw_cv_text)

    assert sanitized.skills[0].category == "Technical Skills"
    assert sanitized.skills[0].items == "SQL, Python"


def test_source_fidelity_preserves_text_split_by_another_pdf_column():
    source = _draft(
        location="Istanbul",
        summary="Senior data analyst.",
        title="Senior Data Analyst",
        bullet="I plan health tourism projects",
        skill_category="Technical Skills",
    )
    raw_cv_text = _draft_source_text(source).replace(
        source.experience[0].bullets[0],
        "I plan Contact phone health tourism projects",
    )

    sanitized = service._sanitize_source_fidelity(source, raw_cv_text)

    assert sanitized.experience[0].bullets == ["I plan health tourism projects"]


def test_source_fidelity_rejects_words_scattered_across_unrelated_sections():
    source = _draft(
        location="Istanbul",
        summary="Invented distant claim",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical Skills",
    )
    unrelated = " ".join(f"source{i}" for i in range(80))
    raw_cv_text = _draft_source_text(source).replace(
        source.personal.summary,
        f"Invented {unrelated} distant claim",
    )

    sanitized = service._sanitize_source_fidelity(source, raw_cv_text)

    assert sanitized.personal.summary == ""


def test_normalize_payload_keeps_all_optional_sections_and_assigns_ids():
    source = CvBuilderDraftAI.model_validate({
        **_draft(
            location="Istanbul",
            summary="Senior data analyst.",
            title="Senior Data Analyst",
            bullet="Built SQL dashboards.",
            skill_category="Technical Skills",
        ).model_dump(mode="json"),
        "optional": {
            "awards": [{"title": "Innovation Award", "issuer": "YGT", "date": "2024", "details": "First place"}],
            "volunteer": [{"organization": "Red Cross", "role": "Volunteer", "location": "Istanbul", "start": "2020", "end": "2021", "bullets": ["Supported events"]}],
            "publications": [{"title": "AI Study", "publisher": "Tech Journal", "date": "2023", "link": "https://example.com/study", "description": "Research paper"}],
            "courses": [{"name": "Leadership", "institution": "ITU", "date": "2022", "description": "Executive course"}],
            "languages": [{"language": "English", "level": "B1"}],
            "leadership": [{"organization": "Student Club", "role": "President", "location": "Istanbul", "start": "2019", "end": "2020", "bullets": ["Led 20 members"]}],
            "affiliations": [{"name": "PMI", "role": "Member", "start": "2021", "end": "Present"}],
            "references": [{"name": "Ada Lovelace", "title": "Director", "organization": "YGT", "contact": "ada@example.com"}],
            "interests": [{"items": "Artificial intelligence, sailing"}],
            "research": [{"title": "Health AI", "institution": "ITU", "start": "2022", "end": "2023", "description": "Clinical AI research"}],
            "additional": [{"body": "Driving License: Class B"}],
        },
    })

    payload, _missing = service._normalize_payload(source)

    assert payload["enabledOptional"] == [
        "awards",
        "volunteer",
        "publications",
        "courses",
        "languages",
        "leadership",
        "affiliations",
        "references",
        "interests",
        "research",
        "additional",
    ]
    assert set(payload["optional"]) == set(payload["enabledOptional"])
    assert all(
        row["id"]
        for key in payload["enabledOptional"]
        for row in payload["optional"][key]
    )
    assert payload["optional"]["references"][0]["contact"] == "ada@example.com"


def test_unmapped_source_content_is_kept_in_additional_section():
    source = _draft(
        location="Istanbul",
        summary="Senior data analyst.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical Skills",
    )
    raw_cv_text = f"{_draft_source_text(source)}\nDriving License: Class B"

    enriched = service._append_unmapped_content(source, raw_cv_text)

    assert enriched.optional.additional[0].body == "Driving License: Class B"
    assert "Jane Doe" not in enriched.optional.additional[0].body


def test_translation_rejects_changed_reference_contact():
    payload = {
        **_draft(
            location="Istanbul",
            summary="Senior data analyst.",
            title="Senior Data Analyst",
            bullet="Built SQL dashboards.",
            skill_category="Technical Skills",
        ).model_dump(mode="json"),
        "optional": {
            "references": [{
                "name": "Ada Lovelace",
                "title": "Director",
                "organization": "YGT",
                "contact": "ada@example.com",
            }],
        },
    }
    source = CvBuilderDraftAI.model_validate(payload)
    translated = source.model_copy(deep=True)
    translated.optional.references[0].contact = "invented@example.com"

    with pytest.raises(service.AIOutputError, match="optional\\.references\\[0\\]\\.contact"):
        service._validate_translation(source, translated)


def test_translation_allows_natural_language_project_and_certificate_names():
    source = _draft(
        location="Istanbul",
        summary="Senior data analyst.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical Skills",
    )
    source.projects = [CvProjectDraftAI(
        name="Müşteri Analitiği Projesi",
        link="https://example.com/project",
        description="Satış verilerini analiz etti.",
    )]
    source.certificates = [CvCertificateDraftAI(
        name="İleri Veri Analizi Eğitimi",
        issuer="Google LLC",
        date="2024",
    )]
    translated = _draft(
        location="Istanbul",
        summary="Senior data analyst.",
        title="Senior Data Analyst",
        bullet="Built SQL dashboards.",
        skill_category="Technical Skills",
    )
    translated.projects = [CvProjectDraftAI(
        name="Customer Analytics Project",
        link="https://example.com/project",
        description="Analyzed sales data.",
    )]
    translated.certificates = [CvCertificateDraftAI(
        name="Advanced Data Analytics Training",
        issuer="Google LLC",
        date="2024",
    )]

    service._validate_translation(source, translated)
