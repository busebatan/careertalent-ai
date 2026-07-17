"""AI görevleri için canlı web eğitim araması."""

from hashlib import sha256
from html.parser import HTMLParser
import ipaddress
import socket
from urllib.parse import parse_qs, unquote, urlparse

import httpx

from app.core.config import settings


class TrainingSearchUnavailable(RuntimeError):
    pass


def search_training(query: str, skill: str, max_results: int = 3) -> list[dict]:
    provider = settings.EDUCATION_SEARCH_PROVIDER.strip().lower()
    if provider == "tavily":
        rows = _tavily(query, max_results)
    elif provider == "brave":
        rows = _brave(query, max_results)
    elif provider == "duckduckgo":
        rows = _duckduckgo(query, max_results)
    else:
        raise TrainingSearchUnavailable("Desteklenmeyen eğitim arama sağlayıcısı")
    results = []
    for rank, row in enumerate(rows, 1):
        url = str(row.get("url") or "").strip()
        title = str(row.get("title") or "").strip()
        if not title or not _safe_public_url(url):
            continue
        host = (urlparse(url).hostname or "").removeprefix("www.")
        results.append({
            "catalog_id": "web-" + sha256(url.encode()).hexdigest()[:16], "title": title,
            "provider": host, "url": url, "snippet": str(row.get("snippet") or "")[:600],
            "skills": [skill], "source": "web", "rank": rank,
        })
    return results


def _tavily(query: str, max_results: int) -> list[dict]:
    if not settings.TAVILY_API_KEY:
        raise TrainingSearchUnavailable("Tavily API anahtarı yapılandırılmamış")
    response = httpx.post("https://api.tavily.com/search", headers={"Authorization": f"Bearer {settings.TAVILY_API_KEY}"}, json={"query": query, "search_depth": "basic", "max_results": max_results, "include_answer": False, "include_raw_content": False, "topic": "general"}, timeout=20)
    response.raise_for_status()
    return [{"title": item.get("title"), "url": item.get("url"), "snippet": item.get("content")} for item in response.json().get("results", [])]


def _brave(query: str, max_results: int) -> list[dict]:
    if not settings.BRAVE_SEARCH_API_KEY:
        raise TrainingSearchUnavailable("Brave Search API anahtarı yapılandırılmamış")
    response = httpx.get("https://api.search.brave.com/res/v1/web/search", headers={"Accept": "application/json", "X-Subscription-Token": settings.BRAVE_SEARCH_API_KEY}, params={"q": query, "count": max_results, "safesearch": "moderate"}, timeout=20)
    response.raise_for_status()
    return [{"title": item.get("title"), "url": item.get("url"), "snippet": item.get("description")} for item in response.json().get("web", {}).get("results", [])]


def _duckduckgo(query: str, max_results: int) -> list[dict]:
    response = httpx.get("https://html.duckduckgo.com/html/", params={"q": query}, headers={"User-Agent": "CareerTalentAI/1.0 (+https://careertalent.ygtlabs.ai)"}, timeout=20, follow_redirects=True)
    response.raise_for_status()
    parser = _DuckDuckGoParser(max_results)
    parser.feed(response.text)
    return parser.results


def _safe_public_url(url: str) -> bool:
    parsed = urlparse(url)
    if parsed.scheme != "https" or not parsed.hostname or parsed.hostname == "localhost":
        return False
    try:
        addresses = {item[4][0] for item in socket.getaddrinfo(parsed.hostname, 443, type=socket.SOCK_STREAM)}
        return bool(addresses) and all(not ipaddress.ip_address(address).is_private and not ipaddress.ip_address(address).is_loopback and not ipaddress.ip_address(address).is_link_local for address in addresses)
    except (OSError, ValueError):
        return False


class _DuckDuckGoParser(HTMLParser):
    def __init__(self, limit: int):
        super().__init__(); self.limit = limit; self.results = []; self._anchor = None; self._text = []

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == "a" and "result__a" in values.get("class", "") and len(self.results) < self.limit:
            self._anchor = _direct_url(values.get("href", "")); self._text = []

    def handle_data(self, data):
        if self._anchor: self._text.append(data)

    def handle_endtag(self, tag):
        if tag == "a" and self._anchor:
            self.results.append({"title": " ".join("".join(self._text).split()), "url": self._anchor, "snippet": ""})
            self._anchor = None; self._text = []


def _direct_url(url: str) -> str:
    parsed = urlparse(url)
    redirected = parse_qs(parsed.query).get("uddg", [])
    return unquote(redirected[0]) if redirected else url
