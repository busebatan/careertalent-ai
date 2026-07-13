"""Panel veri endpoint'leri.

Sprint 2: Laravel paneli statik PHP demo verisinden çıkarıp FastAPI kaynaklı
tek veri kontratına bağlar. Profil ve CV builder taslağı frontend tarafında kalır.
"""

from __future__ import annotations

from datetime import datetime, timezone
from uuid import uuid4

from fastapi import APIRouter, HTTPException, Depends
from pydantic import BaseModel

from app.schemas.panel import (
    ApplicationsResponse,
    CareerLadderResponse,
    ChatResponse,
    DashboardResponse,
    InterviewResponse,
    JobMatchAnalyzeResponse,
    JobMatchesResponse,
    JobListingParseRequest,
    JobListingParseResponse,
    TargetRoleRequest,
    TargetRoleResponse,
    JobRadarResponse,
    LearningResponse,
    MentorsResponse,
    RoadmapResponse,
    SkillPassportResponse,
    TasksResponse,
)
from app.services.job_listing_parser import parse_job_listing
from app.services.panel_target_store import get_target, put_target
from app.core.security import require_admin
from app.models.user import User

router = APIRouter()


class JobMatchRequest(BaseModel):
    url: str


def _stats() -> dict:
    return {
        "readiness": 42,
        "career": "Veri Analisti",
        "weekly_tasks_total": 3,
        "weekly_tasks_done": 1,
    }


def _learning_resources() -> list[dict]:
    return [
        {
            "id": "1",
            "title": "Google Data Analytics Certificate",
            "provider": "Coursera",
            "url": "https://www.coursera.org/professional-certificates/google-data-analytics",
            "price_type": "paid",
            "price_label": "1.400 ₺/ay",
            "price_range": "500-2000",
            "has_certificate": True,
            "skills": ["SQL", "Spreadsheet", "R"],
        },
        {
            "id": "2",
            "title": "SQL Tutorial — Full Database Course",
            "provider": "YouTube · freeCodeCamp",
            "url": "https://www.youtube.com/watch?v=HXV3zeQKqGY",
            "price_type": "free",
            "price_label": "Ücretsiz",
            "price_range": "0-500",
            "has_certificate": False,
            "skills": ["SQL"],
        },
        {
            "id": "3",
            "title": "Python for Data Analysis",
            "provider": "Udemy",
            "url": "https://www.udemy.com/course/python-for-data-analysis/",
            "price_type": "paid",
            "price_label": "899 ₺",
            "price_range": "500-2000",
            "has_certificate": True,
            "skills": ["Python", "Pandas"],
        },
        {
            "id": "4",
            "title": "Khan Academy — Statistics & Probability",
            "provider": "Khan Academy",
            "url": "https://www.khanacademy.org/math/statistics-probability",
            "price_type": "free",
            "price_label": "Ücretsiz",
            "price_range": "0-500",
            "has_certificate": False,
            "skills": ["Statistics"],
        },
        {
            "id": "5",
            "title": "AWS Certified Cloud Practitioner",
            "provider": "AWS",
            "url": "https://aws.amazon.com/certification/certified-cloud-practitioner/",
            "price_type": "paid",
            "price_label": "2.500 ₺ sınav",
            "price_range": "2000+",
            "has_certificate": True,
            "skills": ["Cloud"],
        },
        {
            "id": "6",
            "title": "Data Analyst Bootcamp",
            "provider": "Udemy",
            "url": "https://www.udemy.com/course/the-data-analyst-course/",
            "price_type": "paid",
            "price_label": "449 ₺",
            "price_range": "0-500",
            "has_certificate": True,
            "skills": ["Excel", "SQL", "Python"],
        },
    ]


def _weekly_tasks() -> list[dict]:
    return [
        {"id": "1", "title": "SQL modülü 1: SELECT ve JOIN", "done": False, "hint": "Haftalık 2 saat ayır; modül sonunda mini quiz çöz."},
        {"id": "2", "title": "Mini proje: satış verisi analizi", "done": False, "hint": "Jupyter veya Google Sheets ile örnek veri seti üzerinde çalış."},
        {"id": "3", "title": "CV'ni güncel yeteneklerle yenile", "done": True, "hint": "CV oluşturucuda kaydet; yetenek radarını güncelle."},
    ]


def _career_ladder() -> list[dict]:
    return [
        {
            "id": "junior-da",
            "tier": "ready",
            "tier_label": "A — Hazır",
            "title": "Junior Veri Analisti",
            "readiness": 78,
            "gap_count": 3,
            "gaps_summary": "Tableau, İngilizce B2, portfolio",
            "weeks_estimate": None,
            "swot": {
                "strengths": ["SQL", "Excel", "staj deneyimi"],
                "weaknesses": ["Tableau", "portfolio eksik"],
                "opportunities": ["TR'de junior DA talebi yüksek"],
                "threats": ["Çok sayıda bootcamp mezunu aday"],
            },
        },
        {
            "id": "bi-analyst",
            "tier": "near",
            "tier_label": "B — Yakın",
            "title": "BI Analisti",
            "readiness": 61,
            "gap_count": 5,
            "gaps_summary": "Power BI, DAX, veri modelleme, dashboard, sunum",
            "weeks_estimate": "4–8 hafta",
            "swot": {
                "strengths": ["SQL", "Excel", "analitik düşünme"],
                "weaknesses": ["Power BI", "DAX"],
                "opportunities": ["KOBİ'lerde BI rolü artıyor"],
                "threats": ["Otomatik dashboard araçları"],
            },
        },
        {
            "id": "ml-engineer",
            "tier": "reachable",
            "tier_label": "C — Ulaşılabilir",
            "title": "Makine Öğrenmesi Mühendisi",
            "readiness": 28,
            "gap_count": 12,
            "gaps_summary": "Python ileri, sklearn, deploy, matematik, proje",
            "weeks_estimate": "~6 ay",
            "swot": {
                "strengths": ["Temel Python", "istatistik giriş"],
                "weaknesses": ["ML framework", "model deploy", "derin öğrenme"],
                "opportunities": ["YZTA bootcamp ML modülleri"],
                "threats": ["Senior ML aday yoğunluğu"],
            },
        },
    ]


def _career_tier_meta() -> dict:
    return {
        "ready": {"heading": "A — Hazır", "hint": "%70 ve üzeri · şimdi başvuruya yakın"},
        "near": {"heading": "B — Yakın", "hint": "4–8 haftalık planla ulaşılabilir"},
        "reachable": {"heading": "C — Ulaşılabilir", "hint": "Uzun vade · eksikler tamamlanınca mümkün"},
    }


def _skill_passport() -> dict:
    return {
        "score": 68,
        "verified": 5,
        "total": 8,
        "items": [
            {"skill": "SQL", "level": "İleri", "evidence": "E-ticaret satış analizi GitHub projesi", "type": "GitHub", "status": "verified", "impact": "10K+ sipariş üzerinde cohort/RFM analizi"},
            {"skill": "Python", "level": "Orta", "evidence": "Pandas notebook ve veri temizleme scriptleri", "type": "Proje", "status": "verified", "impact": "Eksik veri temizleme + görselleştirme"},
            {"skill": "Dashboard", "level": "Başlangıç", "evidence": "Tableau satış panosu taslağı", "type": "Portfolio", "status": "review", "impact": "3 KPI, 2 segment grafiği"},
            {"skill": "İngilizce", "level": "B2", "evidence": "Sunum videosu ve teknik özet", "type": "Video", "status": "missing", "impact": "Kanıt linki eklenmeli"},
        ],
        "gaps": ["Power BI dashboard linki", "Canlı proje demosu", "İngilizce teknik sunum kaydı"],
    }


def _interview() -> dict:
    return {
        "questions": [
            {"role": "Junior Veri Analisti", "type": "Teknik", "question": "Bir satış tablosunda tekrar eden müşteri kayıtlarını nasıl temizler ve raporlarsın?", "score": 82, "feedback": "SQL DISTINCT/JOIN, Pandas drop_duplicates ve kalite kontrol adımlarını söylediğinde güçlü cevap olur."},
            {"role": "Junior Veri Analisti", "type": "Davranışsal", "question": "Eksik veri yüzünden teslim tarihi riskli olduğunda ekibe nasıl bilgi verirsin?", "score": 76, "feedback": "Risk, seçenek ve net sonraki adım formatında cevap ver. STAR tekniği ekle."},
            {"role": "BI Analisti", "type": "Vaka", "question": "Yönetim düşen dönüşüm oranını soruyor. İlk bakacağın 3 metrik nedir?", "score": 71, "feedback": "Funnel kırılımı, trafik kaynağı ve segment bazlı dönüşüm analizi iyi başlangıç."},
        ],
        "rubric": ["Problem çerçevesi", "Teknik doğruluk", "Ölçülebilir etki", "İletişim netliği"],
    }


def _applications() -> dict:
    return {
        "metrics": {"active": 6, "interviews": 2, "offers": 1},
        "columns": [
            {"id": "saved", "label": "Kaydedildi", "items": [
                {"company": "Trendyol", "role": "Junior Data Analyst", "date": "7 Tem", "next": "CV uyumunu kontrol et"},
                {"company": "Getir", "role": "Product Data Intern", "date": "8 Tem", "next": "Portfolio linki ekle"},
            ]},
            {"id": "applied", "label": "Başvuruldu", "items": [
                {"company": "Hepsiburada", "role": "BI Analyst Intern", "date": "3 Tem", "next": "Takip maili: 10 Tem"},
            ]},
            {"id": "interview", "label": "Mülakat", "items": [
                {"company": "Logo Yazılım", "role": "Data Analyst", "date": "11 Tem", "next": "SQL case pratiği"},
            ]},
        ],
    }


def _job_radar() -> dict:
    return {
        "roles": ["Junior Veri Analisti", "BI Analisti", "Product Analyst"],
        "sources": ["LinkedIn", "Kariyer.net", "Remote"],
        "alerts": [
            {"role": "Junior Veri Analisti", "company": "Trendyol", "source": "LinkedIn", "match": 84, "salary": "35-45K", "gaps": ["Tableau"], "action": "Başvuruya hazır; Tableau projesini CV’ye ekle."},
            {"role": "BI Analisti", "company": "Logo Yazılım", "source": "Kariyer.net", "match": 67, "salary": "30-40K", "gaps": ["Power BI", "DAX"], "action": "2 haftalık Power BI mini proje sonrası başvur."},
            {"role": "Product Analyst", "company": "Remote EU Startup", "source": "Remote", "match": 58, "salary": "€1.8-2.5K", "gaps": ["A/B test", "İngilizce sunum"], "action": "Önce vaka çalışması ve İngilizce pitch hazırla."},
        ],
    }


def _mentors() -> dict:
    return {
        "packages": [
            {"name": "CV hızlı kontrol", "price": "₺299", "delivery": "24 saat"},
            {"name": "Portfolio review", "price": "₺499", "delivery": "48 saat"},
            {"name": "Mülakat provası", "price": "₺699", "delivery": "Canlı 45 dk"},
        ],
        "experts": [
            {"name": "Ece Kara", "title": "Senior Data Analyst", "company": "Fintech", "rating": 4.9, "focus": "CV + SQL case", "slots": "Salı 20:00"},
            {"name": "Mert Aydın", "title": "BI Lead", "company": "E-commerce", "rating": 4.8, "focus": "Power BI portfolio", "slots": "Perşembe 19:30"},
            {"name": "Selin Demir", "title": "Talent Partner", "company": "SaaS", "rating": 4.7, "focus": "HR mülakat", "slots": "Cumartesi 11:00"},
        ],
    }


def _chat_assistant() -> dict:
    return {
        "prompts": [
            {"q": "Bu hafta hangi göreve odaklanmalıyım?", "a": "Önce SQL mini projesini bitir. Çünkü Junior Veri Analisti için en yüksek gap Tableau değil, kanıtlanmış proje çıktısı."},
            {"q": "Trendyol ilanına başvurmalı mıyım?", "a": "Evet, %84 uyum var. Başvurmadan önce CV’ye satış analizi projesinin GitHub linkini ve Tableau ekran görüntüsünü ekle."},
            {"q": "Mülakatta zayıf yanımı nasıl anlatayım?", "a": "Power BI deneyiminin temel seviyede olduğunu söyle; bunu 2 haftalık dashboard projesiyle kapattığını somut örnekle bağla."},
        ],
    }


def _user_skills() -> list[str]:
    return ["SQL", "Python", "Pandas", "Excel", "Tableau", "Statistics", "İngilizce"]


def _job_catalog() -> list[dict]:
    return [
        {"url_contains": "kariyer.net/is-ilani/junior-veri-analisti", "title": "Junior Veri Analisti", "company": "FinTech A.Ş.", "source": "kariyer.net", "required_skills": ["SQL", "Python", "Excel", "Tableau", "İngilizce"]},
        {"url_contains": "kariyer.net/is-ilani/bi-analisti", "title": "BI Analisti", "company": "Perakende Grubu", "source": "kariyer.net", "required_skills": ["SQL", "Power BI", "DAX", "Excel", "Veri modelleme"]},
        {"url_contains": "linkedin.com/jobs/view", "title": "Data Analyst (Remote)", "company": "Global SaaS Co.", "source": "LinkedIn", "required_skills": ["SQL", "Python", "Statistics", "Tableau", "İngilizce"]},
    ]


def _normalize_url(url: str) -> str:
    url = url.strip()
    if not url:
        raise HTTPException(status_code=422, detail="URL boş olamaz.")
    if not url.lower().startswith(("http://", "https://")):
        url = f"https://{url}"
    if "." not in url.split("//", 1)[-1].split("/", 1)[0]:
        raise HTTPException(status_code=422, detail="Geçerli bir ilan linki girin.")
    return url


def _analyze_job(url: str) -> dict:
    normalized = _normalize_url(url)
    lower = normalized.lower()
    entry = next((item for item in _job_catalog() if item["url_contains"].lower() in lower), None)
    if entry is None:
        host = lower.split("//", 1)[-1].split("/", 1)[0].replace("www.", "")
        entry = {"title": "İş ilanı", "company": host.title(), "source": host, "required_skills": ["SQL", "Python", "Excel", "İletişim", "Problem çözme"]}

    user = set(_user_skills())
    required = entry["required_skills"]
    matched = [skill for skill in required if skill in user]
    missing = [skill for skill in required if skill not in user]
    overlap = len(matched) / len(required) if required else 0.5
    score = round(min(95, max(18, (overlap * 70) + (_stats()["readiness"] * 0.3))))

    recommendation = "apply" if score >= 70 else ("prepare" if score >= 50 else "wait")
    return {
        "id": str(uuid4()),
        "url": normalized,
        "title": entry["title"],
        "company": entry["company"],
        "source": entry["source"],
        "match_score": score,
        "matched_skills": matched,
        "missing_skills": missing,
        "recommendation": recommendation,
        "analyzed_at": datetime.now(timezone.utc).isoformat(),
    }


@router.get("/dashboard", response_model=DashboardResponse)
def dashboard(current_user : User = Depends(require_admin),) -> DashboardResponse:
    return {"stats": _stats(), "weekly_tasks": _weekly_tasks(), "learning_resources": _learning_resources()}


@router.get("/roadmap", response_model=RoadmapResponse)
def roadmap() -> RoadmapResponse:
    return {"stats": _stats(), "weekly_tasks": _weekly_tasks()}


@router.get("/tasks", response_model=TasksResponse)
def tasks() -> TasksResponse:
    return {"stats": _stats(), "weekly_tasks": _weekly_tasks()}


@router.get("/learning", response_model=LearningResponse)
def learning() -> LearningResponse:
    return {"learning_resources": _learning_resources()}


@router.get("/career-ladder", response_model=CareerLadderResponse)
def career_ladder() -> CareerLadderResponse:
    return {"career_ladder": _career_ladder(), "career_tier_meta": _career_tier_meta()}


@router.get("/skill-passport", response_model=SkillPassportResponse)
def skill_passport() -> SkillPassportResponse:
    return {"passport": _skill_passport()}


@router.get("/interview", response_model=InterviewResponse)
def interview() -> InterviewResponse:
    return {"interview": _interview()}


@router.get("/applications", response_model=ApplicationsResponse)
def applications() -> ApplicationsResponse:
    return {"applications": _applications()}


@router.get("/job-radar", response_model=JobRadarResponse)
def job_radar() -> JobRadarResponse:
    return {"radar": _job_radar()}


@router.get("/mentors", response_model=MentorsResponse)
def mentors() -> MentorsResponse:
    return {"mentors": _mentors()}


@router.get("/chat", response_model=ChatResponse)
def chat() -> ChatResponse:
    return {"assistant": _chat_assistant()}


@router.get("/job-matches", response_model=JobMatchesResponse)
def job_matches() -> JobMatchesResponse:
    seed_urls = [
        "https://www.kariyer.net/is-ilani/junior-veri-analisti-fintech",
        "https://www.linkedin.com/jobs/view/data-analyst-remote-123456",
    ]
    return {"seed_jobs": [_analyze_job(url) for url in seed_urls], "user_skills": _user_skills(), "readiness": _stats()["readiness"]}


@router.post("/job-matches/analyze", response_model=JobMatchAnalyzeResponse)
def analyze_job_match(body: JobMatchRequest) -> JobMatchAnalyzeResponse:
    return {"job": _analyze_job(body.url)}


@router.get("/target", response_model=TargetRoleResponse)
def target_role() -> TargetRoleResponse:
    return {"target": get_target()}


@router.put("/target", response_model=TargetRoleResponse)
def save_target_role(body: TargetRoleRequest) -> TargetRoleResponse:
    title = (body.title or "").strip()
    if not title:
        raise HTTPException(status_code=422, detail="Hedef rol başlığı gerekli")

    target = {
        "source": body.source,
        "role_id": body.role_id or f"{body.source}-{uuid4()}",
        "title": title,
        "readiness": body.readiness if body.readiness is not None else (30 if body.source == "job_url" else 35),
        "gap_count": body.gap_count if body.gap_count is not None else 4,
        "gaps_summary": body.gaps_summary or "Rol gereksinimleri, portfolio, CV uyumu, başvuru planı",
        "weeks_estimate": body.weeks_estimate or ("2–4 hafta" if body.source == "job_url" else "4–8 hafta"),
        "selected_at": datetime.now(timezone.utc).isoformat(),
        "required_skills": body.required_skills,
        "parsed_from": body.parsed_from,
    }
    if body.job_url:
        target["job_url"] = body.job_url
    if body.swot:
        target["swot"] = body.swot.model_dump()

    return {"target": put_target(target)}


@router.post("/job-listings/parse", response_model=JobListingParseResponse)
def parse_job_listing_endpoint(body: JobListingParseRequest) -> JobListingParseResponse:
    try:
        return parse_job_listing(body.url)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
