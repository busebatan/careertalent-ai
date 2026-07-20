"""Company positions: serialization, ATS snapshots, versioned AI drafts and audit."""
from __future__ import annotations
from datetime import UTC, datetime
import re, unicodedata
from typing import Literal
from uuid import uuid4

from pydantic import BaseModel, Field
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from app.models.company_recruiting import OrganizationAtsConfiguration, RecruitingApplication, RecruitingAssessment, RecruitingPosition, RecruitingPositionActivity, RecruitingPositionAiAnalysis, RecruitingPositionCriteriaVersion, RecruitingShareLink
from app.models.recruiting import Organization, OrganizationMembership
from app.models.user import User
from app.schemas.company import CompanyAtsConfigResponse, CompanyCriteriaVersionResponse, CompanyEffectiveAtsConfig, CompanyPositionActivityResponse, CompanyPositionAiAnalysisResponse, CompanyPositionCounts, CompanyPositionResponse, CompanyShareLinkResponse
from app.services.career_engine import _invoke


class PositionAnalysisOutput(BaseModel):
    ambiguous_requirements: list[str] = Field(default_factory=list)
    contradictions: list[str] = Field(default_factory=list)
    excessive_experience_expectations: list[str] = Field(default_factory=list)
    weakly_related_requirements: list[str] = Field(default_factory=list)
    measurable_skills: list[str] = Field(default_factory=list)
    recommended_weights: dict[str, int] = Field(default_factory=dict)


class CandidateAtsAnalysisOutput(BaseModel):
    overall_score: int = Field(ge=0, le=100)
    overall_status: Literal["human_review_required"] = "human_review_required"
    criteria_scores: list[dict] = Field(default_factory=list)
    cv_evidence: list[dict] = Field(default_factory=list)
    uncertainties: list[str] = Field(default_factory=list)
    human_review_required: bool = True


def slugify(value: str) -> str:
    value = value.translate(str.maketrans({"ı":"i","İ":"I","ş":"s","Ş":"S","ğ":"g","Ğ":"G","ü":"u","Ü":"U","ö":"o","Ö":"O","ç":"c","Ç":"C"}))
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "-", value).strip("-")[:160] or "pozisyon"


def add_activity(db: Session, position: RecruitingPosition, event_type: str, *, membership_id=None, user_id=None, entity_type="position", entity_id=None, details=None):
    row = RecruitingPositionActivity(id=str(uuid4()), organization_id=position.organization_id, position_id=position.id, event_type=event_type, entity_type=entity_type, entity_id=entity_id, actor_membership_id=membership_id, actor_user_id=user_id, details=details or {})
    db.add(row); return row


def position_counts(db: Session, position: RecruitingPosition) -> CompanyPositionCounts:
    applications = db.scalar(select(func.count()).select_from(RecruitingApplication).where(RecruitingApplication.organization_id==position.organization_id, RecruitingApplication.position_id==position.id)) or 0
    completed = db.scalar(select(func.count(func.distinct(RecruitingAssessment.application_id))).join(RecruitingApplication, RecruitingApplication.id==RecruitingAssessment.application_id).where(RecruitingApplication.organization_id==position.organization_id, RecruitingApplication.position_id==position.id, RecruitingAssessment.status=="completed")) or 0
    shortlisted = db.scalar(select(func.count()).select_from(RecruitingApplication).where(RecruitingApplication.organization_id==position.organization_id, RecruitingApplication.position_id==position.id, RecruitingApplication.current_stage=="shortlisted")) or 0
    return CompanyPositionCounts(applications=applications, assessment_completed=completed, shortlisted=shortlisted)


def _member_name(db, membership_id, organization_id):
    if not membership_id: return None
    return db.scalar(select(User.full_name).join(OrganizationMembership, OrganizationMembership.user_id==User.id).where(OrganizationMembership.id==membership_id, OrganizationMembership.organization_id==organization_id))


def position_response(db: Session, organization: Organization, position: RecruitingPosition, *, counts=None, recruiter_name=None, technical_manager_name=None, resolve_members=True):
    counts = counts or position_counts(db, position)
    return CompanyPositionResponse(
        id=position.id, slug=position.slug, public_id=position.public_id, public_path=f"/apply/{organization.slug}/{position.slug}-{position.public_id}", title=position.title,
        department=position.department, level=position.level, employment_type=position.employment_type, workplace_type=position.workplace_type, location=position.location,
        salary_min=position.salary_min, salary_max=position.salary_max, salary_currency=position.salary_currency, description=position.description, responsibilities=position.responsibilities,
        must_have_skills=position.must_have_skills or [], preferred_skills=position.preferred_skills or [], learnable_skills=position.learnable_skills or [],
        experience_expectation=position.experience_expectation, language_work_authorization=position.language_work_authorization, source_text=position.source_text,
        ats_terms=position.ats_terms or [], ats_notes=position.ats_notes, evaluation_config=position.evaluation_config or {}, application_form_id=position.application_form_id,
        assessment_template_id=position.assessment_template_id, recruiter_membership_id=position.recruiter_membership_id,
        recruiter_name=_member_name(db, position.recruiter_membership_id, organization.id) if resolve_members else recruiter_name,
        technical_manager_membership_id=position.technical_manager_membership_id,
        technical_manager_name=_member_name(db, position.technical_manager_membership_id, organization.id) if resolve_members else technical_manager_name,
        retention_days=position.retention_days, status=position.status, application_deadline=position.application_deadline, target_start_date=position.target_start_date,
        opened_at=position.opened_at, closed_at=position.closed_at, application_count=counts.applications, assessment_completed_count=counts.assessment_completed,
        shortlisted_count=counts.shortlisted, created_at=position.created_at, updated_at=position.updated_at)


def ats_config_response(config, organization_id):
    return CompanyAtsConfigResponse(organization_id=organization_id, provider=config.provider if config else "generic", system_name=config.system_name if config else None,
        terms=(config.terms or []) if config else [], notes=config.notes if config else None, candidate_analysis_instructions=config.candidate_analysis_instructions if config else None,
        updated_at=config.updated_at if config else None)


def effective_ats_config(db, position):
    config=db.get(OrganizationAtsConfiguration, position.organization_id); org_terms=(config.terms or []) if config else []; pos_terms=position.ats_terms or []
    effective_by_key = {}
    effective_order = []
    for term in [*org_terms, *pos_terms]:
        normalized = str(term).strip()
        key = re.split(r"\s*(?:=|:)\s*", normalized, maxsplit=1)[0].casefold()
        if key not in effective_by_key:
            effective_order.append(key)
        effective_by_key[key] = normalized
    return CompanyEffectiveAtsConfig(provider=config.provider if config else "generic", system_name=config.system_name if config else None,
        organization_terms=org_terms, position_terms=pos_terms, effective_terms=[effective_by_key[key] for key in effective_order],
        organization_notes=config.notes if config else None, position_notes=position.ats_notes,
        candidate_analysis_instructions=config.candidate_analysis_instructions if config else None)


def criteria_response(row):
    return CompanyCriteriaVersionResponse(id=row.id, version_number=row.version_number, status=row.status, criteria=row.criteria or {}, ai_suggestions=row.ai_suggestions or {}, approved_by_membership_id=row.approved_by_membership_id, approved_at=row.approved_at, created_at=row.created_at, updated_at=row.updated_at)
def analysis_response(row):
    return CompanyPositionAiAnalysisResponse(id=row.id, criteria_version_id=row.criteria_version_id, status=row.status, result=row.result or {}, error_code=row.error_code, error_message=row.error_message, created_at=row.created_at, completed_at=row.completed_at)


def share_link_response(db, row):
    applications=db.scalar(select(func.count()).select_from(RecruitingApplication).where(RecruitingApplication.organization_id==row.organization_id, RecruitingApplication.original_share_link_id==row.id)) or 0
    completed=db.scalar(select(func.count(func.distinct(RecruitingAssessment.application_id))).join(RecruitingApplication, RecruitingApplication.id==RecruitingAssessment.application_id).where(RecruitingApplication.organization_id==row.organization_id, RecruitingApplication.original_share_link_id==row.id, RecruitingAssessment.status=="completed")) or 0
    shortlisted=db.scalar(select(func.count()).select_from(RecruitingApplication).where(RecruitingApplication.organization_id==row.organization_id, RecruitingApplication.original_share_link_id==row.id, RecruitingApplication.current_stage=="shortlisted")) or 0
    return CompanyShareLinkResponse(id=row.id, channel=row.channel, label=row.label, short_code=row.short_code, short_path=f"/a/{row.short_code}", campaign=row.campaign, expires_at=row.expires_at,
        agency_reference=row.agency_reference, employee_reference=row.employee_reference, application_limit=row.application_limit, source_description=row.source_description,
        is_active=row.is_active, click_count=row.click_count, application_count=applications, assessment_completed_count=completed, shortlisted_count=shortlisted, created_at=row.created_at)


def activity_response(row, actor_name=None):
    return CompanyPositionActivityResponse(id=row.id,event_type=row.event_type,entity_type=row.entity_type,entity_id=row.entity_id,actor_membership_id=row.actor_membership_id,actor_user_id=row.actor_user_id,actor_name=actor_name,details=row.details or {},occurred_at=row.occurred_at)


def request_position_analysis(db, position, membership):
    number=(db.scalar(select(func.max(RecruitingPositionCriteriaVersion.version_number)).where(RecruitingPositionCriteriaVersion.organization_id==position.organization_id, RecruitingPositionCriteriaVersion.position_id==position.id)) or 0)+1
    criteria=RecruitingPositionCriteriaVersion(id=str(uuid4()),organization_id=position.organization_id,position_id=position.id,version_number=number,status="draft",criteria={},ai_suggestions={},created_by_membership_id=membership.id)
    snapshot={"title":position.title,"department":position.department,"level":position.level,"description":position.description,"responsibilities":position.responsibilities,"must_have_skills":position.must_have_skills or [],"preferred_skills":position.preferred_skills or [],"learnable_skills":position.learnable_skills or [],"experience_expectation":position.experience_expectation,"language_work_authorization":position.language_work_authorization,"source_text":position.source_text,"ats":effective_ats_config(db,position).model_dump(mode="json")}
    analysis=RecruitingPositionAiAnalysis(id=str(uuid4()),organization_id=position.organization_id,position_id=position.id,criteria_version_id=criteria.id,status="queued",input_snapshot=snapshot,result={},requested_by_membership_id=membership.id)
    db.add_all([criteria,analysis]); add_activity(db,position,"position.ai_analysis_requested",membership_id=membership.id,entity_type="ai_analysis",entity_id=analysis.id,details={"criteria_version":number}); db.commit(); db.refresh(analysis); return analysis


def analyze_position(db, analysis):
    criteria=db.get(RecruitingPositionCriteriaVersion,analysis.criteria_version_id); position=db.scalar(select(RecruitingPosition).where(RecruitingPosition.id==analysis.position_id,RecruitingPosition.organization_id==analysis.organization_id))
    if not criteria or not position: return analysis
    analysis.status="processing"; db.commit()
    try:
        output=_invoke("İlanı belirsizlik, çelişki, aşırı deneyim, ilgisiz şart, ölçülecek yetenek ve toplamı 100 öneri ağırlıkları açısından incele. ATS sözlüğünü kullan. İnsan onayı olmadan aktif değildir. "+str(analysis.input_snapshot)[:30000],PositionAnalysisOutput)
        result=output.model_dump(mode="json"); analysis.result=result; analysis.status="completed"; analysis.error_code=analysis.error_message=None
        criteria.ai_suggestions=result; criteria.criteria={"must_have":position.must_have_skills or [],"preferred":position.preferred_skills or [],"learnable":position.learnable_skills or [],"weights":result.get("recommended_weights",{}),"preconditions":{"language_work_authorization":position.language_work_authorization}}
    except Exception as exc:
        analysis.status="failed"; analysis.error_code="ai_analysis_failed"; analysis.error_message=str(exc)[:500]
    analysis.completed_at=datetime.now(UTC); add_activity(db,position,f"position.ai_analysis_{analysis.status}",entity_type="ai_analysis",entity_id=analysis.id,details={"criteria_version_id":criteria.id}); db.commit(); db.refresh(analysis); return analysis


def analyze_candidate_application(db, application):
    position=db.scalar(select(RecruitingPosition).where(RecruitingPosition.id==application.position_id,RecruitingPosition.organization_id==application.organization_id)); snapshot=application.application_snapshot or {}; cv_snapshot=snapshot.get("cv") if isinstance(snapshot,dict) else None; criteria_snapshot=snapshot.get("criteria_version") if isinstance(snapshot,dict) else None
    if not position or not isinstance(cv_snapshot,dict):
        application.analysis_status="failed"; application.analysis_result={"error_code":"application_snapshot_missing"}; db.commit(); return application
    application.analysis_status="processing"; db.commit()
    try:
        output=_invoke("CV'yi yalnız başvuru anındaki snapshot ATS ve criteria sürümüne göre puanla. criteria_scores, cv_evidence, uncertainties döndür; overall_status human_review_required. Otomatik shortlist/ret yok. "+str({"position":{"title":position.title,"criteria_version":criteria_snapshot or {}},"ats":application.ats_context_snapshot or {},"cv":cv_snapshot})[:50000],CandidateAtsAnalysisOutput)
        result=output.model_dump(mode="json"); result["criteria_version_id"]=application.criteria_version_id; application.analysis_result=result; application.analysis_status="completed"
    except Exception as exc:
        application.analysis_status="failed"; application.analysis_result={"error_code":"candidate_analysis_failed","message":str(exc)[:500]}
    add_activity(db,position,f"application.analysis_{application.analysis_status}",user_id=application.candidate_user_id,entity_type="application",entity_id=application.id); db.commit(); db.refresh(application); return application
