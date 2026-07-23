"""Public position pages, tracked short links and candidate application."""
from datetime import UTC, datetime, timedelta
from typing import Annotated, Literal
from uuid import uuid4
from fastapi import APIRouter, Depends, HTTPException, Query, Response, status
from sqlalchemy import func, or_, select, update
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session
from app.core.database import get_db
from app.core.security import require_student_candidate_user
from app.models.career_engine import CareerAnalysis
from app.models.company_recruiting import RecruitingApplication, RecruitingPosition, RecruitingPositionCriteriaVersion, RecruitingPositionQuestion, RecruitingApplicationSnapshot, RecruitingShareLink
from app.models.engagement import CvDocument
from app.models.recruiting import Organization
from app.models.user import User
from app.schemas.company import CandidatePositionApplicationCreate, CandidatePositionApplicationResponse, CompanyPositionQuestionResponse, PublicOrganizationResponse, PublicPositionListResponse, PublicPositionPageResponse, PublicPositionResponse
from app.services.company_outbox import CANDIDATE_ANALYSIS_TASK, enqueue_company_task
from app.services.company_positions import add_activity, effective_ats_config

router=APIRouter(); DB=Annotated[Session,Depends(get_db)]; Candidate=Annotated[User,Depends(require_student_candidate_user)]
def _aware(value): return value if value.tzinfo else value.replace(tzinfo=UTC)

def _public_position(db, public_id):
    row=db.execute(select(Organization,RecruitingPosition).join(RecruitingPosition,RecruitingPosition.organization_id==Organization.id).where(RecruitingPosition.public_id==public_id,Organization.status.in_(["onboarding","active"]))).one_or_none()
    if not row: raise HTTPException(404,"Public position not found")
    return row[0],row[1]
def _visibility(position):
    if position.status == "draft": raise HTTPException(404,"Public position not found")
def _open(position):
    _visibility(position)
    if position.status=="paused": raise HTTPException(423,"Position applications are paused")
    if position.status in {"closed","archived"}: raise HTTPException(410,"Position applications are closed")
    if position.application_deadline and _aware(position.application_deadline)<=datetime.now(UTC): raise HTTPException(410,"Position application deadline has passed")
def _page(org,position,db:Session=None,source=None):
    settings=org.settings if isinstance(org.settings,dict) else {}; evaluation=position.evaluation_config if isinstance(position.evaluation_config,dict) else {}
    opened=position.status=="published" and (not position.application_deadline or _aware(position.application_deadline)>datetime.now(UTC))
    questions_list = []
    if db is not None:
        q_rows = db.scalars(select(RecruitingPositionQuestion).where(RecruitingPositionQuestion.organization_id==org.id, RecruitingPositionQuestion.position_id==position.id).order_by(RecruitingPositionQuestion.sort_order.asc(), RecruitingPositionQuestion.created_at.asc())).all()
        questions_list = [CompanyPositionQuestionResponse.model_validate(q, from_attributes=True) for q in q_rows]
    return PublicPositionPageResponse(organization=PublicOrganizationResponse(name=org.name,slug=org.slug,website=org.website,logo_url=settings.get("logo_url")),position=PublicPositionResponse(
        id=position.id,public_id=position.public_id,public_path=f"/apply/{org.slug}/{position.slug}-{position.public_id}",title=position.title,department=position.department,level=position.level,
        employment_type=position.employment_type,workplace_type=position.workplace_type,location=position.location,description=position.description,responsibilities=position.responsibilities,
        must_have_skills=position.must_have_skills or [],preferred_skills=position.preferred_skills or [],application_deadline=position.application_deadline,status=position.status,application_open=opened,
        estimated_application_minutes=int(evaluation.get("estimated_application_minutes",8)),estimated_assessment_minutes=int(evaluation["estimated_assessment_minutes"]) if evaluation.get("estimated_assessment_minutes") is not None else None,
        questions=questions_list),source=source)
def _source(db,position,code,lock=False):
    if not code:return None
    statement=select(RecruitingShareLink).where(RecruitingShareLink.short_code==code.upper(),RecruitingShareLink.organization_id==position.organization_id,RecruitingShareLink.position_id==position.id)
    row=db.scalar(statement.with_for_update() if lock else statement)
    if not row: raise HTTPException(422,"Share link does not belong to this position")
    if not row.is_active or (row.expires_at and _aware(row.expires_at)<=datetime.now(UTC)): raise HTTPException(410,"Share link is no longer active")
    if row.application_limit is not None:
        count=db.scalar(select(func.count()).select_from(RecruitingApplication).where(RecruitingApplication.organization_id==position.organization_id,RecruitingApplication.original_share_link_id==row.id)) or 0
        if count>=row.application_limit: raise HTTPException(410,"Share link application limit reached")
    return row

@router.get("/positions",response_model=PublicPositionListResponse)
def positions(
    db:DB,
    q:Annotated[str|None,Query(max_length=160)]=None,
    workplace_type:Annotated[Literal["onsite","hybrid","remote"]|None,Query()]=None,
    employment_type:Annotated[Literal["full_time","part_time","contract","internship"]|None,Query()]=None,
    limit:Annotated[int,Query(ge=1,le=100)]=24,
    offset:Annotated[int,Query(ge=0)]=0,
):
    now=datetime.now(UTC)
    filters=[
        Organization.status=="active",
        RecruitingPosition.status=="published",
        or_(RecruitingPosition.application_deadline.is_(None),RecruitingPosition.application_deadline>now),
    ]
    if q and (term:=q.strip()):
        pattern=f"%{term}%"
        filters.append(or_(
            RecruitingPosition.title.ilike(pattern),
            RecruitingPosition.department.ilike(pattern),
            RecruitingPosition.location.ilike(pattern),
            Organization.name.ilike(pattern),
        ))
    if workplace_type: filters.append(RecruitingPosition.workplace_type==workplace_type)
    if employment_type: filters.append(RecruitingPosition.employment_type==employment_type)

    joined=select(Organization,RecruitingPosition).join(RecruitingPosition,RecruitingPosition.organization_id==Organization.id).where(*filters)
    total=db.scalar(select(func.count()).select_from(joined.subquery())) or 0
    published_at=func.coalesce(RecruitingPosition.opened_at,RecruitingPosition.created_at)
    rows=db.execute(joined.order_by(published_at.desc(),RecruitingPosition.id.desc()).offset(offset).limit(limit)).all()
    items=[_page(org,position,db) for org,position in rows]
    return PublicPositionListResponse(items=items,total=total,limit=limit,offset=offset,has_more=offset+len(items)<total)

@router.get("/apply/{organization_slug}/{position_path}",response_model=PublicPositionPageResponse)
def page(organization_slug:str,position_path:str,db:DB):
    org,position=_public_position(db,position_path.rsplit("-",1)[-1].upper())
    if org.slug.casefold() != organization_slug.casefold(): raise HTTPException(404,"Public position not found")
    _visibility(position); return _page(org,position,db)

@router.get("/a/{short_code}",response_model=PublicPositionPageResponse)
def short(short_code:str,db:DB):
    link=db.scalar(select(RecruitingShareLink).where(RecruitingShareLink.short_code==short_code.upper()))
    if not link: raise HTTPException(404,"Share link not found")
    public_id=db.scalar(select(RecruitingPosition.public_id).where(RecruitingPosition.id==link.position_id,RecruitingPosition.organization_id==link.organization_id)) or ""
    org,position=_public_position(db,public_id); _visibility(position); link=_source(db,position,link.short_code)
    db.execute(update(RecruitingShareLink).where(RecruitingShareLink.id==link.id).values(click_count=RecruitingShareLink.click_count+1)); db.commit()
    return _page(org,position,db,{"short_code":link.short_code,"channel":link.channel,"label":link.label,"campaign":link.campaign})

@router.post("/positions/{public_id}/applications",response_model=CandidatePositionApplicationResponse,status_code=201)
def submit(public_id:str,payload:CandidatePositionApplicationCreate,response:Response,db:DB,candidate:Candidate):
    org,position=_public_position(db,public_id.upper()); _open(position); link=_source(db,position,payload.share_link_code,lock=True)
    cv=db.scalar(select(CvDocument).where(CvDocument.id==payload.cv_document_id,CvDocument.user_id==candidate.id))
    if not cv: raise HTTPException(404,"Candidate CV not found")
    existing=db.scalar(select(RecruitingApplication).where(RecruitingApplication.organization_id==org.id,RecruitingApplication.position_id==position.id,RecruitingApplication.candidate_user_id==candidate.id))
    if existing:
        if link: existing.last_share_link_id=link.id
        add_activity(db,position,"application.revisited",user_id=candidate.id,entity_type="application",entity_id=existing.id,details={"source_link_id":link.id if link else None}); db.commit(); response.status_code=200
        return CandidatePositionApplicationResponse(id=existing.id,position_id=position.id,current_stage=existing.current_stage,analysis_status=existing.analysis_status,created=False,applied_at=existing.applied_at)
    now=datetime.now(UTC)
    criteria=db.scalar(select(RecruitingPositionCriteriaVersion).where(RecruitingPositionCriteriaVersion.organization_id==org.id,RecruitingPositionCriteriaVersion.position_id==position.id,RecruitingPositionCriteriaVersion.status=="approved").order_by(RecruitingPositionCriteriaVersion.version_number.desc()))
    career=db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id==candidate.id,CareerAnalysis.cv_document_id==cv.id).order_by(CareerAnalysis.created_at.desc()))
    consent=dict(payload.consent); consent.update({"accepted_at":now.isoformat(),"candidate_user_id":candidate.id})
    cv_snapshot={"id":cv.id,"kind":cv.kind,"display_name":cv.display_name,"language":cv.language,"builder_data":cv.builder_data or {},"analysis_profile":career.profile if career else {},"analysis_skills":career.skills if career else [],"analysis_text":(career.cv_text or "")[:30000] if career else ""}
    app_snapshot={"cv":cv_snapshot,"selected_projects":payload.selected_projects,"application_answers":payload.application_answers,"criteria_version":{"id":criteria.id,"version":criteria.version_number,"criteria":criteria.criteria} if criteria else None}
    app=RecruitingApplication(id=str(uuid4()),organization_id=org.id,position_id=position.id,candidate_user_id=candidate.id,candidate_name=candidate.full_name,candidate_email=candidate.email,
        cv_document_id=cv.id,criteria_version_id=criteria.id if criteria else None,original_share_link_id=link.id if link else None,last_share_link_id=link.id if link else None,consent_snapshot=consent,
        application_snapshot=app_snapshot,
        ats_context_snapshot=effective_ats_config(db,position).model_dump(mode="json"),analysis_status="queued",analysis_result={},current_stage="new",applied_at=now,retention_expires_at=now+timedelta(days=position.retention_days))
    db.add(app)
    try:
        db.flush()
        snapshot=RecruitingApplicationSnapshot(id=str(uuid4()),application_id=app.id,schema_version=1,payload=app_snapshot,consent_scope="all")
        db.add(snapshot)
        add_activity(db,position,"application.submitted",user_id=candidate.id,entity_type="application",entity_id=app.id,details={"source_link_id":link.id if link else None})
        enqueue_company_task(db,organization_id=org.id,task_name=CANDIDATE_ANALYSIS_TASK,aggregate_type="recruiting_application",aggregate_id=app.id)
        db.commit()
    except IntegrityError:
        db.rollback()
        existing=db.scalar(select(RecruitingApplication).where(RecruitingApplication.organization_id==org.id,RecruitingApplication.position_id==position.id,RecruitingApplication.candidate_user_id==candidate.id))
        if existing is None: raise
        response.status_code=200
        return CandidatePositionApplicationResponse(id=existing.id,position_id=position.id,current_stage=existing.current_stage,analysis_status=existing.analysis_status,created=False,applied_at=existing.applied_at)
    db.refresh(app)
    return CandidatePositionApplicationResponse(id=app.id,position_id=position.id,current_stage=app.current_stage,analysis_status=app.analysis_status,created=True,applied_at=app.applied_at)
