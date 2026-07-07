"""İş ilanı URL parse yardımcıları.

Öncelik: HTML meta/title/body içinden deterministic extraction. AI anahtarı varsa
LLM sadece çıkarılan metni yapılandırmak için best-effort kullanılır; ağ/AI hatası URL
fallback akışını bozmaz.
"""

from __future__ import annotations

import json
import re
from html import unescape
from html.parser import HTMLParser
from urllib.parse import urlparse

import httpx

from app.services.ai_factory import ai_configured
from app.services.llm import get_chat_model

_SKILL_PATTERNS: dict[str, list[str]] = {
    "SQL": ["sql", "mysql", "postgres", "postgresql", "t-sql"],
    "Python": ["python"],
    "Excel": ["excel", "spreadsheet", "google sheets"],
    "Power BI": ["power bi", "powerbi", "dax"],
    "Tableau": ["tableau"],
    "Pandas": ["pandas"],
    "React": ["react", "react.js", "reactjs"],
    "JavaScript": ["javascript", "node.js", "nodejs"],
    "TypeScript": ["typescript"],
    "Docker": ["docker", "container"],
    "PostgreSQL": ["postgresql", "postgres"],
    "FastAPI": ["fastapi"],
    "Django": ["django"],
    "Git": ["git", "github", "gitlab"],
    "Agile / Scrum": ["agile", "scrum", "kanban"],
    "İletişim": ["iletişim", "communication", "stakeholder", "sunum", "presentation"],
    "Veri Görselleştirme": ["visualization", "görselleştirme", "dashboard", "chart", "raporlama"],
    "REST API": ["rest api", "api", "endpoint"],
    "Scikit-learn": ["scikit", "sklearn", "machine learning", "ml"],
    "A/B Test": ["a/b", "ab test", "experiment"],
    "Product Analytics": ["product analytics", "funnel", "cohort", "retention", "conversion"],
    "Roadmap": ["roadmap", "prioritization", "önceliklendirme"],
    "Jira": ["jira"],
    "Figma": ["figma", "wireframe", "prototype"],
    "Cloud": ["aws", "azure", "gcp", "cloud"],
}

_ROLE_HINTS: list[tuple[str, list[str]]] = [
    ("Product Manager", ["product manager", "ürün yöneticisi", "product owner"]),
    ("Product Analyst", ["product analyst", "ürün analisti"]),
    ("Data Analyst", ["data analyst", "veri analisti"]),
    ("Business Analyst", ["business analyst", "iş analisti"]),
    ("Backend Developer", ["backend developer", "backend geliştirici"]),
    ("Frontend Developer", ["frontend developer", "frontend geliştirici"]),
    ("Machine Learning Engineer", ["machine learning engineer", "ml engineer", "makine öğrenmesi"]),
]


class _JobHtmlParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self._tag_stack: list[str] = []
        self._skip_depth = 0
        self.title_parts: list[str] = []
        self.body_parts: list[str] = []
        self.meta: dict[str, str] = {}
        self.json_ld_parts: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        tag = tag.lower()
        attrs_dict = {key.lower(): value or "" for key, value in attrs}
        self._tag_stack.append(tag)
        if tag in {"script", "style", "noscript"}:
            # JSON-LD scriptleri ayrıca okunur, diğer script text body'ye karışmaz.
            if attrs_dict.get("type", "").lower() != "application/ld+json":
                self._skip_depth += 1
        if tag == "meta":
            name = (attrs_dict.get("property") or attrs_dict.get("name") or "").lower()
            content = attrs_dict.get("content") or ""
            if name and content:
                self.meta[name] = unescape(content)

    def handle_endtag(self, tag: str) -> None:
        tag = tag.lower()
        if tag in {"script", "style", "noscript"} and self._skip_depth > 0:
            self._skip_depth -= 1
        if self._tag_stack:
            self._tag_stack.pop()

    def handle_data(self, data: str) -> None:
        text = _squash(data)
        if not text:
            return
        current = self._tag_stack[-1] if self._tag_stack else ""
        if current == "title":
            self.title_parts.append(text)
        elif current == "script" and ('"@type"' in text or "JobPosting" in text):
            self.json_ld_parts.append(text)
        elif self._skip_depth == 0:
            self.body_parts.append(text)


def parse_job_listing(url: str) -> dict:
    normalized = _normalize_url(url)
    fetched = _fetch_listing_text(normalized)
    combined_text = fetched["combined_text"] if fetched else _title_from_url(normalized)
    ai_payload = _extract_with_llm(combined_text) if fetched else {}
    html_title = fetched.get("title") if fetched else ""
    title = _clean_title(str(ai_payload.get("title") or html_title)) or (_infer_title(combined_text) if fetched else None) or _title_from_url(normalized)
    host = urlparse(normalized).netloc.replace("www.", "") or "ilan"
    required_skills = _merge_skills(
        ai_payload.get("required_skills", []),
        _skills_from_text(combined_text),
        _skills_from_text(title),
    )

    return {
        "url": normalized,
        "title": title,
        "company": str(ai_payload.get("company") or _company_from_host(host))[:80],
        "source": host,
        "role_id": "job-" + _slug(host + "-" + title),
        "required_skills": required_skills,
        "parsed_from": fetched["parsed_from"] if fetched else "url",
    }


def _normalize_url(url: str) -> str:
    clean = url.strip()
    if not clean:
        raise ValueError("URL boş olamaz")
    if not clean.lower().startswith(("http://", "https://")):
        clean = "https://" + clean
    parsed = urlparse(clean)
    if not parsed.netloc or "." not in parsed.netloc:
        raise ValueError("Geçerli bir ilan linki girin")
    return clean


def _fetch_listing_text(url: str) -> dict | None:
    try:
        response = httpx.get(
            url,
            follow_redirects=True,
            timeout=8,
            headers={"User-Agent": "Mozilla/5.0 CareerTalentAI/1.0"},
        )
        if response.status_code >= 400:
            return None
    except httpx.HTTPError:
        return None

    parser = _JobHtmlParser()
    parser.feed(response.text[:500_000])
    json_ld_text = _json_ld_text(parser.json_ld_parts)
    title = parser.meta.get("og:title") or parser.meta.get("twitter:title") or " ".join(parser.title_parts)
    description = parser.meta.get("description") or parser.meta.get("og:description") or parser.meta.get("twitter:description") or ""
    body = " ".join(parser.body_parts[:1200])
    combined = _squash(" ".join([title, description, json_ld_text, body]))

    return {
        "title": _clean_title(title),
        "combined_text": combined[:20_000],
        "parsed_from": "html" if combined else "url",
    }


def _json_ld_text(parts: list[str]) -> str:
    out: list[str] = []
    for part in parts[:5]:
        try:
            payload = json.loads(part)
        except json.JSONDecodeError:
            continue
        out.extend(_walk_json_text(payload))
    return " ".join(out)


def _walk_json_text(payload) -> list[str]:
    values: list[str] = []
    if isinstance(payload, dict):
        for key, value in payload.items():
            if key in {"title", "name", "description", "skills", "qualifications", "responsibilities", "jobBenefits", "industry"} and isinstance(value, str):
                values.append(value)
            else:
                values.extend(_walk_json_text(value))
    elif isinstance(payload, list):
        for item in payload:
            values.extend(_walk_json_text(item))
    return values


def _extract_with_llm(text: str) -> dict:
    if not ai_configured() or len(text) < 80:
        return {}
    prompt = (
        "Aşağıdaki iş ilanı metninden JSON döndür. Sadece JSON: "
        '{"title":"...","company":"...","required_skills":["..."]}. '
        "required_skills en fazla 8 adet, teknik/mesleki yetenek adı olsun.\n\n"
        + text[:8000]
    )
    try:
        response = get_chat_model().invoke(prompt)
        content = getattr(response, "content", "")
        if isinstance(content, list):
            content = " ".join(str(item) for item in content)
        match = re.search(r"\{.*\}", str(content), re.S)
        if not match:
            return {}
        payload = json.loads(match.group(0))
    except Exception:
        return {}

    return payload if isinstance(payload, dict) else {}


def _title_from_url(url: str) -> str:
    parsed = urlparse(url)
    parts = [part for part in parsed.path.split("/") if part]
    candidate = parts[-1] if parts else parsed.netloc
    candidate = re.sub(r"\d{4,}$", "", candidate)
    return _clean_title(candidate.replace("-", " ").replace("_", " ")) or "İş ilanı"


def _infer_title(text: str) -> str | None:
    lower = text.lower()
    for title, needles in _ROLE_HINTS:
        if any(needle in lower for needle in needles):
            return title
    return None


def _clean_title(title: str) -> str:
    title = _squash(title).strip(" -|•\t\n\r")
    for sep in [" | ", " - ", " — ", " – "]:
        if sep in title:
            title = title.split(sep)[0].strip()
    return title[:120]


def _company_from_host(host: str) -> str:
    first = host.split(".")[0]
    return first.replace("-", " ").title()


def _skills_from_text(text: str) -> list[str]:
    lower = text.lower()
    found: list[str] = []
    for skill, patterns in _SKILL_PATTERNS.items():
        if any(pattern in lower for pattern in patterns):
            found.append(skill)
    return found


def _merge_skills(*groups) -> list[str]:
    merged: list[str] = []
    for group in groups:
        if not isinstance(group, list):
            continue
        for skill in group:
            if not isinstance(skill, str):
                continue
            clean = _canonical_skill(skill)
            if clean and clean not in merged:
                merged.append(clean)
    return merged[:10] or ["Rol gereksinimleri", "CV anahtar kelimeleri", "Portfolio kanıtı"]


def _canonical_skill(skill: str) -> str:
    clean = _squash(skill).strip(" ,.;:-")
    if not clean:
        return ""
    lower = clean.lower()
    for canonical, patterns in _SKILL_PATTERNS.items():
        if lower == canonical.lower() or any(pattern == lower for pattern in patterns):
            return canonical
    return clean[:60]


def _squash(value: str) -> str:
    return re.sub(r"\s+", " ", unescape(value or "")).strip()


def _slug(value: str) -> str:
    slug = re.sub(r"[^a-zA-Z0-9]+", "-", value.lower()).strip("-")
    return slug[:80] or "target"
