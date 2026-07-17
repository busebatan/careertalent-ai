from __future__ import annotations
import io
import pdfplumber
# Yeni yazdığımız servisi import ediyoruz
from app.services.anonymizer import anonymize_cv_text 

def extract_text_from_pdf(data: bytes, anonymize: bool = True) -> str:
    chunks: list[str] = []

    with pdfplumber.open(io.BytesIO(data)) as pdf:
        for page in pdf.pages:
            text = page.extract_text() or ""
            if text.strip():
                chunks.append(text.strip())

    raw_text = "\n\n".join(chunks).strip()

    # Eğer anonimleştirme isteniyorsa metni maskele
    if anonymize:
        return anonymize_cv_text(raw_text)
        
    return raw_text