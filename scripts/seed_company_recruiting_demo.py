#!/usr/bin/env python3
"""Seed company recruiting demo data (positions, candidates, assessments).

Usage:
  DEBUG=false backend/.venv/bin/python scripts/seed_company_recruiting_demo.py --org-slug test-sirketi
"""

from __future__ import annotations

import argparse
from datetime import UTC, datetime, timedelta
from pathlib import Path
import sys
from uuid import uuid4

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "backend"))

from sqlalchemy import select

from app.core.database import SessionLocal
from app.models.company_recruiting import (
    AssessmentUsageLedger,
    RecruitingApplication,
    RecruitingApplicationStageEvent,
    RecruitingAssessment,
    RecruitingPosition,
    RecruitingScorecard,
)
from app.models.recruiting import Organization, OrganizationMembership


DEMO_MARKER = "demo-seed-2026-07-20"


def _owner_membership(db, organization_id: str) -> OrganizationMembership:
    membership = db.scalar(
        select(OrganizationMembership)
        .where(
            OrganizationMembership.organization_id == organization_id,
            OrganizationMembership.role == "owner",
            OrganizationMembership.status == "active",
        )
        .limit(1)
    )
    if membership is None:
        raise RuntimeError(f"No active owner membership for organization {organization_id}")
    return membership


def _position_exists(db, organization_id: str, title: str) -> RecruitingPosition | None:
    return db.scalar(
        select(RecruitingPosition).where(
            RecruitingPosition.organization_id == organization_id,
            RecruitingPosition.title == title,
        )
    )


def _application_exists(db, organization_id: str, position_id: str, email: str) -> bool:
    return db.scalar(
        select(RecruitingApplication.id).where(
            RecruitingApplication.organization_id == organization_id,
            RecruitingApplication.position_id == position_id,
            RecruitingApplication.candidate_email == email,
        )
    ) is not None


def seed_organization(db, organization: Organization) -> dict[str, int]:
    now = datetime.now(UTC)
    owner_membership = _owner_membership(db, organization.id)
    created = {"positions": 0, "applications": 0, "assessments": 0, "scorecards": 0}

    position_specs = [
        {
            "title": "Demo: Backend Developer",
            "department": "Mühendislik",
            "employment_type": "full_time",
            "workplace_type": "hybrid",
            "description": "Python/FastAPI ile API geliştirme. Mikroservis ve PostgreSQL deneyimi artı.",
            "status": "published",
            "deadline_days": 21,
        },
        {
            "title": "Demo: QA Engineer",
            "department": "Kalite",
            "employment_type": "full_time",
            "workplace_type": "remote",
            "description": "Manuel ve otomasyon test süreçlerinde deneyimli QA mühendisi.",
            "status": "published",
            "deadline_days": 14,
        },
        {
            "title": "Demo: Product Designer",
            "department": "Ürün",
            "employment_type": "full_time",
            "workplace_type": "onsite",
            "description": "B2B SaaS arayüzleri ve design system deneyimi.",
            "status": "paused",
            "deadline_days": 30,
        },
        {
            "title": "Demo: Data Analyst (Taslak)",
            "department": "Analitik",
            "employment_type": "contract",
            "workplace_type": "hybrid",
            "description": "İşe alım hunisi ve aday metrikleri için SQL/BI odaklı rol.",
            "status": "draft",
            "deadline_days": None,
        },
    ]

    positions: dict[str, RecruitingPosition] = {}
    for spec in position_specs:
        existing = _position_exists(db, organization.id, spec["title"])
        if existing is not None:
            positions[spec["title"]] = existing
            continue
        position = RecruitingPosition(
            id=str(uuid4()),
            organization_id=organization.id,
            title=spec["title"],
            department=spec["department"],
            employment_type=spec["employment_type"],
            workplace_type=spec["workplace_type"],
            description=spec["description"],
            status=spec["status"],
            application_deadline=now + timedelta(days=spec["deadline_days"]) if spec["deadline_days"] else None,
            opened_at=now if spec["status"] == "published" else None,
            created_by_membership_id=owner_membership.id,
        )
        db.add(position)
        positions[spec["title"]] = position
        created["positions"] += 1

    db.flush()

    candidate_specs = [
        {
            "position_title": "Demo: Backend Developer",
            "name": "Ayşe Yılmaz",
            "email": "demo.ayse@ygtlabs.ai",
            "stage": "new",
            "applied_days_ago": 1,
            "assessment": None,
        },
        {
            "position_title": "Demo: Backend Developer",
            "name": "Mehmet Kaya",
            "email": "demo.mehmet@ygtlabs.ai",
            "stage": "assessment_pending",
            "applied_days_ago": 3,
            "assessment": {"status": "assigned", "title": "Python Temel Değerlendirme"},
        },
        {
            "position_title": "Demo: Backend Developer",
            "name": "Zeynep Demir",
            "email": "demo.zeynep@ygtlabs.ai",
            "stage": "technical_review",
            "applied_days_ago": 6,
            "assessment": {"status": "completed", "title": "Sistem Tasarımı Vaka"},
            "scorecard_pending": True,
        },
        {
            "position_title": "Demo: QA Engineer",
            "name": "Can Öztürk",
            "email": "demo.can@ygtlabs.ai",
            "stage": "assessment_in_progress",
            "applied_days_ago": 4,
            "assessment": {"status": "in_progress", "title": "Test Otomasyonu"},
        },
        {
            "position_title": "Demo: QA Engineer",
            "name": "Elif Arslan",
            "email": "demo.elif@ygtlabs.ai",
            "stage": "shortlisted",
            "applied_days_ago": 10,
            "assessment": {"status": "completed", "title": "API Test Senaryoları"},
        },
        {
            "position_title": "Demo: Product Designer",
            "name": "Deniz Akın",
            "email": "demo.deniz@ygtlabs.ai",
            "stage": "interview",
            "applied_days_ago": 12,
            "assessment": {"status": "completed", "title": "Portfolio İncelemesi"},
        },
    ]

    for index, spec in enumerate(candidate_specs, start=1):
        position = positions.get(spec["position_title"])
        if position is None:
            continue
        if _application_exists(db, organization.id, position.id, spec["email"]):
            continue

        applied_at = now - timedelta(days=spec["applied_days_ago"])
        application_id = str(uuid4())
        application = RecruitingApplication(
            id=application_id,
            organization_id=organization.id,
            position_id=position.id,
            candidate_name=spec["name"],
            candidate_email=spec["email"],
            current_stage=spec["stage"],
            first_reviewed_at=applied_at + timedelta(days=1) if spec["stage"] != "new" else None,
            applied_at=applied_at,
            retention_expires_at=now + timedelta(days=30 - spec["applied_days_ago"]),
        )
        db.add(application)
        created["applications"] += 1

        db.add(
            RecruitingApplicationStageEvent(
                id=str(uuid4()),
                organization_id=organization.id,
                position_id=position.id,
                application_id=application_id,
                from_stage=None,
                to_stage="new",
                reason_code=DEMO_MARKER,
                actor_membership_id=owner_membership.id,
                idempotency_key=f"{DEMO_MARKER}-app-{index}-new",
                occurred_at=applied_at,
            )
        )
        if spec["stage"] != "new":
            db.add(
                RecruitingApplicationStageEvent(
                    id=str(uuid4()),
                    organization_id=organization.id,
                    position_id=position.id,
                    application_id=application_id,
                    from_stage="new",
                    to_stage=spec["stage"],
                    reason_code=DEMO_MARKER,
                    actor_membership_id=owner_membership.id,
                    idempotency_key=f"{DEMO_MARKER}-app-{index}-stage",
                    occurred_at=applied_at + timedelta(days=1),
                )
            )

        assessment_spec = spec.get("assessment")
        if assessment_spec is None:
            continue

        assessment_id = str(uuid4())
        assigned_at = applied_at + timedelta(hours=6)
        completed_at = None
        started_at = None
        if assessment_spec["status"] in {"in_progress", "completed"}:
            started_at = assigned_at + timedelta(hours=2)
        if assessment_spec["status"] == "completed":
            completed_at = assigned_at + timedelta(days=1)

        db.add(
            RecruitingAssessment(
                id=assessment_id,
                organization_id=organization.id,
                application_id=application_id,
                title=assessment_spec["title"],
                required=True,
                status=assessment_spec["status"],
                assigned_at=assigned_at,
                started_at=started_at,
                completed_at=completed_at,
                expires_at=assigned_at + timedelta(days=7),
            )
        )
        created["assessments"] += 1
        db.flush()

        if assessment_spec["status"] in {"assigned", "in_progress", "completed"}:
            db.add(
                AssessmentUsageLedger(
                    id=str(uuid4()),
                    organization_id=organization.id,
                    assessment_id=assessment_id,
                    entry_type="consume",
                    units=1,
                    idempotency_key=f"{DEMO_MARKER}-usage-{index}",
                    reason_code="assessment_started",
                    occurred_at=assigned_at,
                )
            )

        if spec.get("scorecard_pending"):
            db.add(
                RecruitingScorecard(
                    id=str(uuid4()),
                    organization_id=organization.id,
                    application_id=application_id,
                    reviewer_membership_id=owner_membership.id,
                    scorecard_type="technical",
                    status="pending",
                    requested_at=completed_at or assigned_at + timedelta(days=1),
                )
            )
            created["scorecards"] += 1

    db.commit()
    return created


def main() -> None:
    parser = argparse.ArgumentParser(description="Seed company recruiting demo data")
    parser.add_argument("--org-slug", required=True, help="Organization slug, e.g. test-sirketi")
    args = parser.parse_args()

    db = SessionLocal()
    try:
        organization = db.scalar(select(Organization).where(Organization.slug == args.org_slug))
        if organization is None:
            raise SystemExit(f"Organization not found for slug: {args.org_slug}")

        created = seed_organization(db, organization)
        print(
            f"Demo seed complete for {organization.name} ({organization.slug}): "
            f"positions={created['positions']}, applications={created['applications']}, "
            f"assessments={created['assessments']}, scorecards={created['scorecards']}"
        )
    finally:
        db.close()


if __name__ == "__main__":
    main()
