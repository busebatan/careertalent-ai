import re
import spacy

# NLP modelini yükle
try:
    nlp = spacy.load("xx_ent_wiki_sm")
except OSError:
    nlp = None

def mask_deterministic_data(text: str) -> str:
    # Email, Telefon, Link temizliği
    text = re.sub(r'[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+', '[EMAIL_GIZLENDI]', text)
    text = re.sub(r'(\+?\d{1,3}[\s-]?)?\(?\d{3}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}', '[TELEFON_GIZLENDI]', text)
    text = re.sub(r'https?://(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&//=]*)', '[LINK_GIZLENDI]', text)
    return text

def mask_contextual_data(text: str) -> str:
    if not nlp: return text
    doc = nlp(text)
    masked_text = text
    for ent in reversed(doc.ents):
        if ent.label_ == "PER":
            masked_text = masked_text[:ent.start_char] + "[ADAY_ISMI]" + masked_text[ent.end_char:]
        elif ent.label_ == "ORG":
            masked_text = masked_text[:ent.start_char] + "[KURUM_ISMI]" + masked_text[ent.end_char:]
    return masked_text

def anonymize_cv_text(raw_text: str) -> str:
    return mask_contextual_data(mask_deterministic_data(raw_text))