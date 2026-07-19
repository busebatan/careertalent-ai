from types import SimpleNamespace

from app.services import anonymizer


def test_anonymizer_masks_deterministic_personal_data(monkeypatch):
    monkeypatch.setattr(anonymizer, "nlp", None)

    masked = anonymizer.anonymize_cv_text(
        "Email: aday@example.com\nTelefon: 0555 123 45 67\nLinkedIn: https://linkedin.com/in/aday"
    )

    assert "aday@example.com" not in masked
    assert "0555 123 45 67" not in masked
    assert "https://linkedin.com/in/aday" not in masked
    assert "[EMAIL_GIZLENDI]" in masked
    assert "[TELEFON_GIZLENDI]" in masked
    assert "[LINK_GIZLENDI]" in masked


def test_anonymizer_masks_contextual_person_and_organization(monkeypatch):
    text = "Ayşe Yılmaz, Acme şirketinde çalıştı."
    entities = [
        SimpleNamespace(label_="PER", start_char=0, end_char=11),
        SimpleNamespace(label_="ORG", start_char=13, end_char=17),
    ]
    monkeypatch.setattr(anonymizer, "nlp", lambda _text: SimpleNamespace(ents=entities))

    masked = anonymizer.mask_contextual_data(text)

    assert masked == "[ADAY_ISMI], [KURUM_ISMI] şirketinde çalıştı."
