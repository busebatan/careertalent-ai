"""PDF CV metin çıkarımı ve anonimleştirme."""

from __future__ import annotations

import io

import pdfplumber

from app.services.anonymizer import anonymize_cv_text

def extract_text_from_pdf(data: bytes, anonymize: bool = True) -> str:
    chunks: list[str] = []

    with pdfplumber.open(io.BytesIO(data)) as pdf:
        for page in pdf.pages:
            text = page.extract_text() or ""
            if text.strip():
                chunks.append(text.strip())

    raw_text = "\n\n".join(chunks).strip()

    if anonymize:
        return anonymize_cv_text(raw_text)

    return raw_text
