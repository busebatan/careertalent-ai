"""CV metninde analiz edilebilir mesleki içerik var mı?"""

import re

_CONTACT_LABELS = {
    "adres", "address", "email", "e-mail", "telefon", "phone", "konum", "location",
    "linkedin", "github", "portfolio", "portfolyo",
}


def has_meaningful_cv_content(cv_text: str, minimum_words: int = 8) -> bool:
    words: list[str] = []
    for raw_line in cv_text.splitlines():
        line = raw_line.strip()
        lowered = line.lower()
        if not line or "@" in line or "http://" in lowered or "https://" in lowered:
            continue
        if sum(character.isdigit() for character in line) >= 5:
            continue
        words.extend(
            word.lower()
            for word in re.findall(r"[^\W\d_]{2,}", line, flags=re.UNICODE)
            if word.lower() not in _CONTACT_LABELS
        )
    return len(words) >= minimum_words
