"""Demo recruiting seed payloads and helpers."""

from __future__ import annotations

from datetime import UTC, date, datetime, timedelta
from decimal import Decimal
from uuid import uuid4

from sqlalchemy import select

from app.models.company_recruiting import (
    RecruitingPosition,
    RecruitingPositionActivity,
    RecruitingPositionAiAnalysis,
    RecruitingPositionCriteriaVersion,
    RecruitingShareLink,
)
from app.models.recruiting import OrganizationMembership

DEMO_MARKER = "demo-seed-2026-07-20"
DEMO_TITLE_PREFIX = "Demo:"


def demo_analysis_result(score: int, *, skill: str) -> dict:
    return {
        "overall_score": score,
        "overall_status": "human_review_required",
        "human_review_required": True,
        "criteria_scores": [
            {"criterion": skill, "score": score, "weight": 60},
            {"criterion": "İletişim", "score": max(score - 8, 40), "weight": 40},
        ],
        "cv_evidence": [
            {"evidence": f"CV'de {skill} projeleri ve üretim deneyimi belirtilmiş."},
            {"evidence": "Takım çalışması ve kod inceleme süreçlerine katılım örnekleri var."},
        ],
        "uncertainties": [
            "Sektör deneyimi süresi net değil.",
            "Uzaktan çalışma tercihi CV'de açık yazılmamış.",
        ],
    }


def rich_position_fields(spec: dict) -> dict:
    return {
        "department": spec["department"],
        "level": spec.get("level", "Mid-Level"),
        "employment_type": spec["employment_type"],
        "workplace_type": spec["workplace_type"],
        "location": spec.get("location", "İstanbul, Türkiye"),
        "salary_min": Decimal(str(spec.get("salary_min", 45000))),
        "salary_max": Decimal(str(spec.get("salary_max", 75000))),
        "salary_currency": spec.get("salary_currency", "TRY"),
        "description": spec["description"],
        "responsibilities": spec.get(
            "responsibilities",
            "• Ekip ile ürün roadmap planlaması\n• Kod kalitesi ve test disiplini\n• Teknik dokümantasyon ve bilgi paylaşımı",
        ),
        "must_have_skills": spec.get("must_have_skills", ["Python", "SQL", "Git"]),
        "preferred_skills": spec.get("preferred_skills", ["Docker", "CI/CD", "Agile"]),
        "learnable_skills": spec.get("learnable_skills", ["Kubernetes", "Observability"]),
        "experience_expectation": spec.get(
            "experience_expectation",
            "En az 3 yıl ilgili alanda deneyim. Startup veya scale-up ortamında çalışmış olması artı.",
        ),
        "language_work_authorization": spec.get(
            "language_work_authorization",
            "Türkçe ve İngilizce (B2+). Türkiye'de çalışma izni veya vatandaşlık.",
        ),
        "ats_terms": spec.get("ats_terms", ["Python=backend", "FastAPI=web framework"]),
        "ats_notes": spec.get("ats_notes", "Demo ATS notu: adayın açık kaynak katkıları değerlendirilsin."),
        "evaluation_config": spec.get("evaluation_config", {"auto_shortlist_threshold": 75}),
        "retention_days": spec.get("retention_days", 180),
        "target_start_date": spec.get("target_start_date", date.today() + timedelta(days=45)),
    }


def position_specs() -> list[dict]:
    return [
        {
            "title": f"{DEMO_TITLE_PREFIX} Backend Developer",
            "department": "Mühendislik",
            "employment_type": "full_time",
            "workplace_type": "hybrid",
            "description": "Python/FastAPI ile API geliştirme. Mikroservis ve PostgreSQL deneyimi artı.",
            "status": "published",
            "deadline_days": 21,
            "must_have_skills": ["Python", "FastAPI", "PostgreSQL"],
            "preferred_skills": ["Redis", "Docker", "pytest"],
            "skill": "Python",
        },
        {
            "title": f"{DEMO_TITLE_PREFIX} QA Engineer",
            "department": "Kalite",
            "employment_type": "full_time",
            "workplace_type": "remote",
            "description": "Manuel ve otomasyon test süreçlerinde deneyimli QA mühendisi.",
            "status": "published",
            "deadline_days": 14,
            "must_have_skills": ["Test Otomasyonu", "Selenium", "API Test"],
            "preferred_skills": ["Playwright", "CI/CD"],
            "skill": "Test Otomasyonu",
        },
        {
            "title": f"{DEMO_TITLE_PREFIX} Product Designer",
            "department": "Ürün",
            "employment_type": "full_time",
            "workplace_type": "onsite",
            "location": "Ankara, Türkiye",
            "description": "B2B SaaS arayüzleri ve design system deneyimi.",
            "status": "paused",
            "deadline_days": 30,
            "must_have_skills": ["Figma", "Design System", "UX Research"],
            "skill": "Figma",
        },
        {
            "title": f"{DEMO_TITLE_PREFIX} Data Analyst (Taslak)",
            "department": "Analitik",
            "employment_type": "contract",
            "workplace_type": "hybrid",
            "description": "İşe alım hunisi ve aday metrikleri için SQL/BI odaklı rol.",
            "status": "draft",
            "deadline_days": None,
            "must_have_skills": ["SQL", "Power BI", "Python"],
            "skill": "SQL",
        },
        {
            "title": f"{DEMO_TITLE_PREFIX} Mobile Developer (Kapalı)",
            "department": "Mobil",
            "employment_type": "full_time",
            "workplace_type": "hybrid",
            "location": "İzmir, Türkiye",
            "description": "React Native ile cross-platform mobil uygulama geliştirme.",
            "status": "closed",
            "deadline_days": 7,
            "closed_days_ago": 5,
            "must_have_skills": ["React Native", "TypeScript", "REST API"],
            "skill": "React Native",
        },
        {
            "title": f"{DEMO_TITLE_PREFIX} DevOps Engineer (Arşiv)",
            "department": "Altyapı",
            "employment_type": "full_time",
            "workplace_type": "remote",
            "description": "Kubernetes, Terraform ve gözlemlenebilirlik odaklı DevOps rolü.",
            "status": "archived",
            "deadline_days": None,
            "closed_days_ago": 45,
            "opened_days_ago": 120,
            "must_have_skills": ["Kubernetes", "Terraform", "AWS"],
            "skill": "Kubernetes",
        },
    ]


def candidate_specs() -> list[dict]:
    prefix = DEMO_TITLE_PREFIX
    return [
        {"position_title": f"{prefix} Backend Developer", "name": "Ayşe Yılmaz", "email": "demo.ayse@ygtlabs.ai", "stage": "new", "applied_days_ago": 1, "analysis_status": "queued", "score": None, "assessment": None},
        {"position_title": f"{prefix} Backend Developer", "name": "Mehmet Kaya", "email": "demo.mehmet@ygtlabs.ai", "stage": "assessment_pending", "applied_days_ago": 3, "analysis_status": "completed", "score": 72, "assessment": {"status": "assigned", "title": "Python Temel Değerlendirme"}},
        {"position_title": f"{prefix} Backend Developer", "name": "Zeynep Demir", "email": "demo.zeynep@ygtlabs.ai", "stage": "technical_review", "applied_days_ago": 6, "analysis_status": "completed", "score": 84, "assessment": {"status": "completed", "title": "Sistem Tasarımı Vaka"}, "scorecard_pending": True},
        {"position_title": f"{prefix} Backend Developer", "name": "Burak Şahin", "email": "demo.burak@ygtlabs.ai", "stage": "offer", "applied_days_ago": 9, "analysis_status": "completed", "score": 91, "assessment": {"status": "completed", "title": "Kod İnceleme Oturumu"}},
        {"position_title": f"{prefix} Backend Developer", "name": "Selin Avcı", "email": "demo.selin@ygtlabs.ai", "stage": "rejected", "applied_days_ago": 11, "analysis_status": "completed", "score": 48, "assessment": {"status": "completed", "title": "Python Temel Değerlendirme"}},
        {"position_title": f"{prefix} QA Engineer", "name": "Can Öztürk", "email": "demo.can@ygtlabs.ai", "stage": "assessment_in_progress", "applied_days_ago": 4, "analysis_status": "processing", "score": 65, "assessment": {"status": "in_progress", "title": "Test Otomasyonu"}},
        {"position_title": f"{prefix} QA Engineer", "name": "Elif Arslan", "email": "demo.elif@ygtlabs.ai", "stage": "shortlisted", "applied_days_ago": 10, "analysis_status": "completed", "score": 88, "assessment": {"status": "completed", "title": "API Test Senaryoları"}},
        {"position_title": f"{prefix} QA Engineer", "name": "Oğuz Yıldız", "email": "demo.oguz@ygtlabs.ai", "stage": "hired", "applied_days_ago": 20, "analysis_status": "completed", "score": 93, "assessment": {"status": "completed", "title": "Regresyon Test Planı"}},
        {"position_title": f"{prefix} QA Engineer", "name": "Merve Koç", "email": "demo.merve@ygtlabs.ai", "stage": "withdrawn", "applied_days_ago": 8, "analysis_status": "completed", "score": 70, "assessment": None},
        {"position_title": f"{prefix} Product Designer", "name": "Deniz Akın", "email": "demo.deniz@ygtlabs.ai", "stage": "interview", "applied_days_ago": 12, "analysis_status": "completed", "score": 79, "assessment": {"status": "completed", "title": "Portfolio İncelemesi"}},
        {"position_title": f"{prefix} Mobile Developer (Kapalı)", "name": "Kerem Polat", "email": "demo.kerem@ygtlabs.ai", "stage": "rejected", "applied_days_ago": 18, "analysis_status": "completed", "score": 55, "assessment": {"status": "expired", "title": "Mobil Vaka Çalışması"}},
        {"position_title": f"{prefix} Mobile Developer (Kapalı)", "name": "Ece Tunç", "email": "demo.ece@ygtlabs.ai", "stage": "interview", "applied_days_ago": 22, "analysis_status": "completed", "score": 82, "assessment": {"status": "completed", "title": "React Native Quiz"}},
    ]


def ensure_criteria(db, position: RecruitingPosition, owner: OrganizationMembership, skill: str) -> RecruitingPositionCriteriaVersion:
    approved = db.scalar(
        select(RecruitingPositionCriteriaVersion).where(
            RecruitingPositionCriteriaVersion.organization_id == position.organization_id,
            RecruitingPositionCriteriaVersion.position_id == position.id,
            RecruitingPositionCriteriaVersion.status == "approved",
        )
    )
    if approved is not None:
        return approved
    now = datetime.now(UTC)
    row = RecruitingPositionCriteriaVersion(
        id=str(uuid4()),
        organization_id=position.organization_id,
        position_id=position.id,
        version_number=1,
        status="approved",
        criteria={"must_have": [skill, "İletişim"], "weights": {skill: 60, "İletişim": 40}},
        ai_suggestions={"recommended_focus": [skill, "takım çalışması"]},
        created_by_membership_id=owner.id,
        approved_by_membership_id=owner.id,
        approved_at=now,
    )
    db.add(row)
    db.flush()
    return row


def ensure_share_links(db, position: RecruitingPosition, owner: OrganizationMembership) -> list[RecruitingShareLink]:
    existing = list(db.scalars(select(RecruitingShareLink).where(
        RecruitingShareLink.organization_id == position.organization_id,
        RecruitingShareLink.position_id == position.id,
    )).all())
    if existing:
        return existing
    now = datetime.now(UTC)
    specs = [
        {"channel": "linkedin", "label": "LinkedIn Kampanyası", "campaign": "demo-q3-hiring", "clicks": 142},
        {"channel": "employee_referral", "label": "Çalışan Önerisi", "employee_reference": "EMP-DEMO-01", "clicks": 28},
        {"channel": "agency", "label": "Ajans Kanalı", "agency_reference": "AGY-DEMO-77", "clicks": 61},
    ]
    links: list[RecruitingShareLink] = []
    for spec in specs:
        link = RecruitingShareLink(
            id=str(uuid4()),
            organization_id=position.organization_id,
            position_id=position.id,
            channel=spec["channel"],
            label=spec["label"],
            campaign=spec.get("campaign"),
            agency_reference=spec.get("agency_reference"),
            employee_reference=spec.get("employee_reference"),
            source_description="Demo takip bağlantısı — CRUD test verisi.",
            expires_at=now + timedelta(days=90),
            application_limit=500,
            is_active=True,
            click_count=spec.get("clicks", 0),
            created_by_membership_id=owner.id,
        )
        db.add(link)
        links.append(link)
    db.flush()
    return links


def ensure_activities(db, position: RecruitingPosition, owner: OrganizationMembership) -> int:
    if db.scalar(select(RecruitingPositionActivity.id).where(
        RecruitingPositionActivity.organization_id == position.organization_id,
        RecruitingPositionActivity.position_id == position.id,
    ).limit(1)):
        return 0
    now = datetime.now(UTC)
    events = [
        ("position.created", {"status": position.status}),
        ("position.updated", {"fields": ["description", "responsibilities"]}),
        ("position.published", {"status": "published"}),
        ("share_link.created", {"channel": "linkedin"}),
        ("application.received", {"source": "demo"}),
    ]
    for index, (event_type, details) in enumerate(events):
        db.add(RecruitingPositionActivity(
            id=str(uuid4()),
            organization_id=position.organization_id,
            position_id=position.id,
            event_type=event_type,
            entity_type="position" if event_type.startswith("position") else "application",
            actor_membership_id=owner.id,
            details=details,
            occurred_at=now - timedelta(days=7 - index),
        ))
    return len(events)


def ensure_ai_analysis(db, position: RecruitingPosition, criteria: RecruitingPositionCriteriaVersion, owner: OrganizationMembership) -> int:
    if db.scalar(select(RecruitingPositionAiAnalysis.id).where(
        RecruitingPositionAiAnalysis.organization_id == position.organization_id,
        RecruitingPositionAiAnalysis.position_id == position.id,
    ).limit(1)):
        return 0
    now = datetime.now(UTC)
    db.add(RecruitingPositionAiAnalysis(
        id=str(uuid4()),
        organization_id=position.organization_id,
        position_id=position.id,
        criteria_version_id=criteria.id,
        status="completed",
        input_snapshot={"title": position.title, "source": DEMO_MARKER},
        result={
            "ambiguous_requirements": ["Deneyim yılı net değil"],
            "measurable_skills": position.must_have_skills or [],
            "recommended_weights": (criteria.criteria or {}).get("weights", {}),
        },
        requested_by_membership_id=owner.id,
        completed_at=now,
    ))
    return 1
