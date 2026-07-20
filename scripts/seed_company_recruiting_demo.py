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
sys.path.insert(0, str(Path(__file__).resolve().parent))

from sqlalchemy import select

from app.core.database import SessionLocal
from app.models.company_recruiting import (
    AssessmentUsageLedger,
    RecruitingApplication,
    RecruitingApplicationStageEvent,
    RecruitingAssessment,
    RecruitingPosition,
    RecruitingScorecard,
    RecruitingShareLink,
    new_public_id,
)
from app.models.recruiting import Organization, OrganizationMembership
from app.services.company_positions import add_activity, slugify

from lib.company_recruiting_demo_data import (
    DEMO_MARKER,
    candidate_specs,
    demo_analysis_result,
    ensure_activities,
    ensure_ai_analysis,
    ensure_criteria,
    ensure_share_links,
    position_specs,
    rich_position_fields,
)


def _owner_membership(db, organization_id: str) -> OrganizationMembership:
    membership = db.scalar(
        select(OrganizationMembership).where(
            OrganizationMembership.organization_id == organization_id,
            OrganizationMembership.role == "owner",
            OrganizationMembership.status == "active",
        ).limit(1)
    )
    if membership is None:
        raise RuntimeError(f"No active owner membership for organization {organization_id}")
    return membership


def _position_by_title(db, organization_id: str, title: str) -> RecruitingPosition | None:
    return db.scalar(select(RecruitingPosition).where(
        RecruitingPosition.organization_id == organization_id,
        RecruitingPosition.title == title,
    ))


def _application_by_email(db, organization_id: str, position_id: str, email: str) -> RecruitingApplication | None:
    return db.scalar(select(RecruitingApplication).where(
        RecruitingApplication.organization_id == organization_id,
        RecruitingApplication.position_id == position_id,
        RecruitingApplication.candidate_email == email,
    ))


def _upsert_position(db, organization, owner, spec: dict, now: datetime) -> tuple[RecruitingPosition, bool]:
    position = _position_by_title(db, organization.id, spec["title"])
    rich = rich_position_fields(spec)
    deadline_days = spec.get("deadline_days")
    application_deadline = now + timedelta(days=deadline_days) if deadline_days else None
    status = spec["status"]

    if position is None:
        position = RecruitingPosition(
            id=str(uuid4()),
            organization_id=organization.id,
            title=spec["title"],
            slug=slugify(spec["title"]),
            public_id=new_public_id(),
            status=status,
            application_deadline=application_deadline,
            opened_at=now - timedelta(days=spec.get("opened_days_ago", 14)) if status in {"published", "paused", "closed", "archived"} else None,
            closed_at=now - timedelta(days=spec.get("closed_days_ago", 3)) if status in {"closed", "archived"} else None,
            created_by_membership_id=owner.id,
            recruiter_membership_id=owner.id,
            technical_manager_membership_id=owner.id,
            **rich,
        )
        db.add(position)
        db.flush()
        return position, True

    for key, value in rich.items():
        setattr(position, key, value)
    position.status = status
    position.application_deadline = application_deadline
    position.recruiter_membership_id = owner.id
    position.technical_manager_membership_id = owner.id
    if status in {"published", "paused", "closed", "archived"} and position.opened_at is None:
        position.opened_at = now - timedelta(days=spec.get("opened_days_ago", 14))
    if status in {"closed", "archived"}:
        position.closed_at = now - timedelta(days=spec.get("closed_days_ago", 3))
    db.flush()
    return position, False


def seed_organization(db, organization: Organization, *, enrich: bool) -> dict[str, int]:
    now = datetime.now(UTC)
    owner = _owner_membership(db, organization.id)
    created = {k: 0 for k in ("positions", "applications", "assessments", "scorecards", "criteria", "share_links", "activities", "analyses", "enriched_positions")}

    positions: dict[str, RecruitingPosition] = {}
    criteria_by_title: dict[str, object] = {}
    links_by_title: dict[str, list[RecruitingShareLink]] = {}
    specs = position_specs()

    for spec in specs:
        position, was_created = _upsert_position(db, organization, owner, spec, now)
        positions[spec["title"]] = position
        created["positions" if was_created else "enriched_positions"] += 1

        if not enrich or position.status == "draft":
            continue

        skill = spec.get("skill", "Python")
        criteria = ensure_criteria(db, position, owner, skill)
        criteria_by_title[spec["title"]] = criteria

        had_links = db.scalar(select(RecruitingShareLink.id).where(
            RecruitingShareLink.organization_id == position.organization_id,
            RecruitingShareLink.position_id == position.id,
        ).limit(1)) is None
        links = ensure_share_links(db, position, owner)
        links_by_title[spec["title"]] = links
        if had_links:
            created["share_links"] += len(links)

        created["activities"] += ensure_activities(db, position, owner)
        created["analyses"] += ensure_ai_analysis(db, position, criteria, owner)

    skill_by_title = {s["title"]: s.get("skill", "Python") for s in specs}

    for index, spec in enumerate(candidate_specs(), start=1):
        position = positions.get(spec["position_title"])
        if position is None:
            continue

        criteria = criteria_by_title.get(spec["position_title"])
        share_link = (links_by_title.get(spec["position_title"]) or [None])[0]
        applied_at = now - timedelta(days=spec["applied_days_ago"])
        analysis_result = demo_analysis_result(spec["score"], skill=skill_by_title.get(spec["position_title"], "Python")) if spec.get("score") is not None else {}

        existing = _application_by_email(db, organization.id, position.id, spec["email"])
        if existing is not None:
            if enrich:
                existing.current_stage = spec["stage"]
                existing.analysis_status = spec.get("analysis_status", "completed")
                existing.analysis_result = analysis_result
                if criteria is not None:
                    existing.criteria_version_id = criteria.id
                if share_link is not None and existing.original_share_link_id is None:
                    existing.original_share_link_id = share_link.id
                    existing.last_share_link_id = share_link.id
            continue

        application_id = str(uuid4())
        db.add(RecruitingApplication(
            id=application_id,
            organization_id=organization.id,
            position_id=position.id,
            candidate_name=spec["name"],
            candidate_email=spec["email"],
            current_stage=spec["stage"],
            analysis_status=spec.get("analysis_status", "completed"),
            analysis_result=analysis_result,
            criteria_version_id=getattr(criteria, "id", None),
            original_share_link_id=share_link.id if share_link else None,
            last_share_link_id=share_link.id if share_link else None,
            first_reviewed_at=applied_at + timedelta(days=1) if spec["stage"] != "new" else None,
            applied_at=applied_at,
            retention_expires_at=now + timedelta(days=30 - spec["applied_days_ago"]),
        ))
        created["applications"] += 1

        db.add(RecruitingApplicationStageEvent(
            id=str(uuid4()), organization_id=organization.id, position_id=position.id, application_id=application_id,
            from_stage=None, to_stage="new", reason_code=DEMO_MARKER, actor_membership_id=owner.id,
            idempotency_key=f"{DEMO_MARKER}-{spec['email']}-new", occurred_at=applied_at,
        ))
        if spec["stage"] != "new":
            db.add(RecruitingApplicationStageEvent(
                id=str(uuid4()), organization_id=organization.id, position_id=position.id, application_id=application_id,
                from_stage="new", to_stage=spec["stage"], reason_code=DEMO_MARKER, actor_membership_id=owner.id,
                idempotency_key=f"{DEMO_MARKER}-{spec['email']}-stage", occurred_at=applied_at + timedelta(days=1),
            ))

        assessment_spec = spec.get("assessment")
        if assessment_spec is None:
            continue

        assessment_id = str(uuid4())
        assigned_at = applied_at + timedelta(hours=6)
        started_at = assigned_at + timedelta(hours=2) if assessment_spec["status"] in {"in_progress", "completed", "expired"} else None
        completed_at = assigned_at + timedelta(days=1) if assessment_spec["status"] == "completed" else None
        db.add(RecruitingAssessment(
            id=assessment_id, organization_id=organization.id, application_id=application_id,
            title=assessment_spec["title"], required=True, status=assessment_spec["status"],
            assigned_at=assigned_at, started_at=started_at, completed_at=completed_at,
            expires_at=assigned_at + timedelta(days=7),
        ))
        created["assessments"] += 1
        db.flush()

        if assessment_spec["status"] in {"assigned", "in_progress", "completed"}:
            db.add(AssessmentUsageLedger(
                id=str(uuid4()), organization_id=organization.id, assessment_id=assessment_id,
                entry_type="consume", units=1, idempotency_key=f"{DEMO_MARKER}-{spec['email']}-usage",
                reason_code="assessment_started", occurred_at=assigned_at,
            ))
        if spec.get("scorecard_pending"):
            db.add(RecruitingScorecard(
                id=str(uuid4()), organization_id=organization.id, application_id=application_id,
                reviewer_membership_id=owner.id, scorecard_type="technical", status="pending",
                requested_at=completed_at or assigned_at + timedelta(days=1),
            ))
            created["scorecards"] += 1

    if enrich:
        for position in positions.values():
            if position.status != "draft":
                add_activity(db, position, "position.demo_enriched", membership_id=owner.id, details={"marker": DEMO_MARKER})

    db.commit()
    return created


def main() -> None:
    parser = argparse.ArgumentParser(description="Seed company recruiting demo data")
    parser.add_argument("--org-slug", required=True)
    parser.add_argument("--no-enrich", action="store_true")
    args = parser.parse_args()

    db = SessionLocal()
    try:
        organization = db.scalar(select(Organization).where(Organization.slug == args.org_slug))
        if organization is None:
            raise SystemExit(f"Organization not found for slug: {args.org_slug}")
        created = seed_organization(db, organization, enrich=not args.no_enrich)
        print(
            f"Demo seed complete for {organization.name} ({organization.slug}): "
            f"positions={created['positions']}, enriched={created['enriched_positions']}, "
            f"applications={created['applications']}, assessments={created['assessments']}, "
            f"scorecards={created['scorecards']}, share_links={created['share_links']}, "
            f"activities={created['activities']}, analyses={created['analyses']}"
        )
    finally:
        db.close()


if __name__ == "__main__":
    main()
