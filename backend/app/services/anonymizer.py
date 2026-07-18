"""CV metnindeki kişisel bilgileri AI işleminden önce maskele."""

from __future__ import annotations

import re
from typing import Any

try:
    import spacy
except ImportError:  # Optional contextual masking; deterministic masking stays active.
    spacy = None


def _load_contextual_model() -> Any | None:
    if spacy is None:
        return None
    try:
        return spacy.load("xx_ent_wiki_sm")
    except OSError:
        return None


nlp = _load_contextual_model()


def mask_deterministic_data(text: str) -> str:
    text = re.sub(
        r"[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+",
        "[EMAIL_GIZLENDI]",
        text,
    )
    text = re.sub(
        r"(\+?\d{1,3}[\s-]?)?\(?\d{3}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}",
        "[TELEFON_GIZLENDI]",
        text,
    )
    return re.sub(
        r"https?://(?:www\.)?[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_+.~#?&//=]*)",
        "[LINK_GIZLENDI]",
        text,
    )


def mask_contextual_data(text: str) -> str:
    if nlp is None:
        return text
    document = nlp(text)
    masked = text
    for entity in reversed(document.ents):
        replacement = {"PER": "[ADAY_ISMI]", "ORG": "[KURUM_ISMI]"}.get(entity.label_)
        if replacement:
            masked = masked[:entity.start_char] + replacement + masked[entity.end_char:]
    return masked


def anonymize_cv_text(raw_text: str) -> str:
    return mask_contextual_data(mask_deterministic_data(raw_text))
