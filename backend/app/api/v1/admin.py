"""Gerçek kayıtları yöneticilere sunan yetkili yönetim API'si."""

from collections.abc import Iterable
from typing import Annotated, Literal
from uuid import uuid4

from fastapi import APIRouter, Depends, HTTPException, Response, status
from sqlalchemy import delete, func, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.core.company_permissions import effective_company_permissions
from app.core.database import get_db
from app.core.security import (
    ADMIN_PERMISSION_KEYS,
    LEGACY_ADMIN_PERMISSION_ALIASES,
    effective_admin_permissions,
    ensure_admin_permission,
    hash_password,
    is_super_admin,
    normalize_admin_permissions,
    require_admin,
    require_admin_permission,
    require_super_admin,
    verify_password,
)
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence, JobOpportunity
from app.models.engagement import CareerInterview, CareerInterviewAnswer, CvDocument, JobApplication, UserProfile
from app.models.recruiting import Organization, OrganizationInvitation, OrganizationMembership
from app.models.user import User
from app.schemas.admin import (
    AdminAccountCreate,
    AdminAccountResponse,
    AdminAccountsResponse,
    AdminAccountUpdate,
    AdminApplicationCreate,
    AdminApplicationResponse,
    AdminApplicationsResponse,
    AdminApplicationUpdate,
    AdminDashboardResponse,
    AdminInterviewCreate,
    AdminInterviewResponse,
    AdminInterviewsResponse,
    AdminInterviewUpdate,
    AdminModuleResponse,
    AdminOrganizationCreate,
    AdminOrganizationDetailResponse,
    AdminOrganizationInvitationItem,
    AdminOrganizationMemberItem,
    AdminOrganizationResponse,
    AdminOrganizationsResponse,
    AdminOrganizationUpdate,
    AdminProfileResponse,
    AdminProfileUpdate,
    AdminStudentCreate,
    AdminStudentAnalysisItem,
    AdminStudentApplicationItem,
    AdminStudentCvItem,
    AdminStudentDetailResponse,
    AdminStudentInterviewItem,
    AdminStudentOption,
    AdminStudentProfileSummary,
    AdminStudentResponse,
    AdminStudentsResponse,
    AdminStudentTargetItem,
    AdminStudentUpdate,
    AdminTableRow,
)
from app.schemas.company import CompanyInviteCreate, CompanyInviteResponse
from app.services.company import (
    CompanyInvitationConflict,
    available_organization_slug,
    create_company_invitation,
    is_reserved_organization_slug,
)
from app.services.engagement import start_interview
from app.services.ai_factory import AIOutputError, AIProviderError, AIUnavailableError

router = APIRouter()
DB = Annotated[Session, Depends(get_db)]
ModuleKey = Literal["students", "readiness", "skill-passport", "job-radar", "applications", "interviews"]
STUDENT_FILTER = (User.is_active.is_(True), User.is_admin.is_(False), User.role != "company")
CANDIDATE_FILTER = (User.is_admin.is_(False), User.role != "company")
MAX_ROWS = 50
MODULE_PERMISSIONS = {
    "students": "students.view",
    "readiness": "readiness.view",
    "skill-passport": "skill_passport.view",
    "job-radar": "job_radar.view",
    "applications": "applications.view",
    "interviews": "interviews.view",
}


def _organization_response(db: Session, organization: Organization) -> AdminOrganizationResponse:
    members_count = db.scalar(
        select(func.count())
        .select_from(OrganizationMembership)
        .where(OrganizationMembership.organization_id == organization.id)
    ) or 0
    settings = organization.settings if isinstance(organization.settings, dict) else {}
    return AdminOrganizationResponse(
        id=organization.id,
        name=organization.name,
        slug=organization.slug,
        organization_type=organization.organization_type,
        size_band=organization.size_band,
        status=organization.status,
        plan_code=organization.plan_code,
        billing_email=organization.billing_email,
        website=organization.website,
        description=settings.get("description"),
        logo_url=settings.get("logo_url"),
        members_count=members_count,
        created_at=organization.created_at.isoformat(),
        updated_at=organization.updated_at.isoformat(),
    )


def _organization_or_404(db: Session, organization_id: str) -> Organization:
    organization = db.get(Organization, organization_id)
    if organization is None:
        raise HTTPException(status_code=404, detail="Organization not found")
    return organization


def _organization_detail_response(db: Session, organization: Organization) -> AdminOrganizationDetailResponse:
    base = _organization_response(db, organization)
    member_rows = db.execute(
        select(OrganizationMembership, User)
        .join(User, User.id == OrganizationMembership.user_id)
        .where(OrganizationMembership.organization_id == organization.id)
        .order_by(User.full_name.asc())
        .limit(MAX_ROWS)
    ).all()
    invitations = db.scalars(
        select(OrganizationInvitation)
        .where(OrganizationInvitation.organization_id == organization.id)
        .order_by(OrganizationInvitation.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return AdminOrganizationDetailResponse(
        **base.model_dump(),
        members=[
            AdminOrganizationMemberItem(
                id=membership.id,
                full_name=user.full_name,
                email=user.email,
                role=membership.role,
                status=membership.status,
                created_at=_date(membership.created_at),
            )
            for membership, user in member_rows
        ],
        invitations=[
            AdminOrganizationInvitationItem(
                id=invitation.id,
                email=invitation.email,
                role=invitation.role,
                expires_at=_date(invitation.expires_at),
                accepted_at=_date(invitation.accepted_at),
                created_at=_date(invitation.created_at),
            )
            for invitation in invitations
        ],
    )


def _permissions(values: list[str]) -> list[str]:
    known = set(ADMIN_PERMISSION_KEYS) | set(LEGACY_ADMIN_PERMISSION_ALIASES)
    unknown = sorted(set(values) - known)
    if unknown:
        raise HTTPException(status_code=422, detail=f"Unknown admin permissions: {', '.join(unknown)}")
    return normalize_admin_permissions(values)


def _admin_account(user: User) -> AdminAccountResponse:
    return AdminAccountResponse(
        id=user.id,
        full_name=user.full_name,
        email=user.email,
        role="super_admin" if is_super_admin(user) else user.role,
        is_active=user.is_active,
        admin_permissions=effective_admin_permissions(user),
        must_change_password=user.must_change_password,
        created_at=user.created_at.isoformat() if user.created_at else None,
    )


def _candidate_or_404(db: Session, user_id: int, *, active: bool = False) -> User:
    filters = [User.id == user_id, *CANDIDATE_FILTER]
    if active:
        filters.append(User.is_active.is_(True))
    user = db.scalar(select(User).where(*filters))
    if user is None:
        raise HTTPException(status_code=404, detail="Student not found")
    return user


def _student_response(user: User) -> AdminStudentResponse:
    return AdminStudentResponse(
        id=user.id,
        full_name=user.full_name,
        email=user.email,
        is_active=user.is_active,
        preferred_locale=user.preferred_locale,
        must_change_password=user.must_change_password,
        created_at=user.created_at.isoformat() if user.created_at else None,
    )


def _readiness_from_radar(radar: list) -> int | None:
    scores = [
        int(item["score"])
        for item in radar
        if isinstance(item, dict) and isinstance(item.get("score"), (int, float))
    ]
    return round(sum(scores) / len(scores)) if scores else None


def _student_detail_response(db: Session, user: User) -> AdminStudentDetailResponse:
    profile_row = db.get(UserProfile, user.id)
    profile = (
        AdminStudentProfileSummary(
            phone=profile_row.phone,
            location=profile_row.location,
            headline=profile_row.headline,
            linkedin=profile_row.linkedin,
        )
        if profile_row is not None
        else None
    )
    cv_documents = db.scalars(
        select(CvDocument)
        .where(CvDocument.user_id == user.id)
        .order_by(CvDocument.is_current.desc(), CvDocument.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    analyses = db.scalars(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id == user.id)
        .order_by(CareerAnalysis.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    interviews = db.scalars(
        select(CareerInterview)
        .where(CareerInterview.user_id == user.id)
        .order_by(CareerInterview.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    applications = db.scalars(
        select(JobApplication)
        .where(JobApplication.user_id == user.id)
        .order_by(JobApplication.applied_at.desc(), JobApplication.id.desc())
        .limit(MAX_ROWS)
    ).all()
    targets = db.scalars(
        select(CareerTarget)
        .where(CareerTarget.user_id == user.id)
        .order_by(CareerTarget.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    interview_items: list[AdminStudentInterviewItem] = []
    for interview in interviews:
        answer_count = db.scalar(
            select(func.count())
            .select_from(CareerInterviewAnswer)
            .where(CareerInterviewAnswer.interview_id == interview.id)
        ) or 0
        average_score = db.scalar(
            select(func.avg(CareerInterviewAnswer.score))
            .where(CareerInterviewAnswer.interview_id == interview.id)
        )
        interview_items.append(
            AdminStudentInterviewItem(
                id=interview.id,
                target_role=interview.target_role,
                status=interview.status,
                language=interview.language,
                question_count=len(interview.questions),
                answer_count=answer_count,
                average_score=round(average_score) if average_score is not None else None,
                created_at=_date(interview.created_at),
            )
        )

    base = _student_response(user)
    return AdminStudentDetailResponse(
        **base.model_dump(),
        profile=profile,
        cv_documents=[
            AdminStudentCvItem(
                id=document.id,
                display_name=document.display_name,
                kind=document.kind,
                is_current=document.is_current,
                created_at=_date(document.created_at),
            )
            for document in cv_documents
        ],
        analyses=[
            AdminStudentAnalysisItem(
                id=analysis.id,
                status=analysis.status,
                current_role=analysis.current_role,
                file_name=analysis.file_name,
                skill_count=len(analysis.skills) if isinstance(analysis.skills, list) else 0,
                readiness_score=_readiness_from_radar(analysis.radar if isinstance(analysis.radar, list) else []),
                created_at=_date(analysis.created_at),
            )
            for analysis in analyses
        ],
        interviews=interview_items,
        applications=[
            AdminStudentApplicationItem(
                id=application.id,
                company=application.company,
                role=application.role,
                stage=application.stage,
                applied_at=_date(application.applied_at),
            )
            for application in applications
        ],
        targets=[
            AdminStudentTargetItem(
                id=target.id,
                title=target.title,
                status=target.status,
                created_at=_date(target.created_at),
            )
            for target in targets
        ],
    )


def _student_options(db: Session) -> list[AdminStudentOption]:
    rows = db.scalars(
        select(User)
        .where(*CANDIDATE_FILTER, User.is_active.is_(True))
        .order_by(User.full_name.asc(), User.id.asc())
        .limit(MAX_ROWS)
    ).all()
    return [AdminStudentOption(id=row.id, full_name=row.full_name, email=row.email) for row in rows]


def _application_response(db: Session, application: JobApplication) -> AdminApplicationResponse:
    student = _candidate_or_404(db, application.user_id)
    return AdminApplicationResponse(
        id=application.id,
        user_id=student.id,
        student_name=student.full_name,
        student_email=student.email,
        company=application.company,
        role=application.role,
        stage=application.stage,
        next_action=application.next_action,
        note=application.note,
        applied_at=application.applied_at.isoformat() if application.applied_at else None,
    )


def _interview_response(db: Session, interview: CareerInterview) -> AdminInterviewResponse:
    student = _candidate_or_404(db, interview.user_id)
    answer_count = db.scalar(
        select(func.count())
        .select_from(CareerInterviewAnswer)
        .where(CareerInterviewAnswer.interview_id == interview.id)
    ) or 0
    return AdminInterviewResponse(
        id=interview.id,
        user_id=student.id,
        student_name=student.full_name,
        student_email=student.email,
        target_role=interview.target_role,
        status=interview.status,
        language=interview.language,
        question_count=len(interview.questions),
        answer_count=answer_count,
        created_at=interview.created_at.isoformat() if interview.created_at else None,
    )


@router.get("/profile", response_model=AdminProfileResponse)
def profile(current_user: User = Depends(require_admin)) -> AdminProfileResponse:
    return _admin_account(current_user)


@router.patch("/profile", response_model=AdminProfileResponse)
def update_profile(payload: AdminProfileUpdate, db: DB, current_user: User = Depends(require_admin)) -> AdminProfileResponse:
    if not verify_password(payload.current_password, current_user.hashed_password):
        raise HTTPException(status_code=422, detail="Current password is incorrect")
    email = str(payload.email).strip().lower()
    duplicate = db.scalar(select(User).where(func.lower(User.email) == email, User.id != current_user.id))
    if duplicate:
        raise HTTPException(status_code=409, detail="Email already registered")
    current_user.full_name = payload.full_name
    current_user.email = email
    if payload.new_password:
        current_user.hashed_password = hash_password(payload.new_password)
        current_user.must_change_password = False
        current_user.token_version += 1
    elif current_user.must_change_password:
        raise HTTPException(status_code=422, detail="New password is required")
    db.commit()
    db.refresh(current_user)
    return _admin_account(current_user)


@router.get("/accounts", response_model=AdminAccountsResponse)
def accounts(db: DB, _super_admin: User = Depends(require_super_admin)) -> AdminAccountsResponse:
    rows = db.scalars(
        select(User).where(User.is_admin.is_(True)).order_by(User.created_at.desc(), User.id.desc())
    ).all()
    return AdminAccountsResponse(permission_keys=list(ADMIN_PERMISSION_KEYS), accounts=[_admin_account(row) for row in rows])


@router.post("/accounts", response_model=AdminAccountResponse, status_code=status.HTTP_201_CREATED)
def create_account(payload: AdminAccountCreate, db: DB, _super_admin: User = Depends(require_super_admin)) -> AdminAccountResponse:
    email = str(payload.email).strip().lower()
    if db.scalar(select(User).where(func.lower(User.email) == email)):
        raise HTTPException(status_code=409, detail="Email already registered")
    user = User(
        full_name=payload.full_name,
        email=email,
        hashed_password=hash_password(payload.temporary_password),
        is_active=True,
        is_admin=True,
        role="admin",
        admin_permissions=_permissions(payload.permissions),
        must_change_password=True,
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return _admin_account(user)


@router.patch("/accounts/{user_id}", response_model=AdminAccountResponse)
def update_account(user_id: int, payload: AdminAccountUpdate, db: DB, _super_admin: User = Depends(require_super_admin)) -> AdminAccountResponse:
    user = db.scalar(select(User).where(User.id == user_id, User.is_admin.is_(True)))
    if user is None:
        raise HTTPException(status_code=404, detail="Admin account not found")
    if is_super_admin(user):
        raise HTTPException(status_code=422, detail="Super admin account must be updated from its profile")
    email = str(payload.email).strip().lower()
    if db.scalar(select(User).where(func.lower(User.email) == email, User.id != user.id)):
        raise HTTPException(status_code=409, detail="Email already registered")
    user.full_name = " ".join(payload.full_name.split())
    user.email = email
    user.is_active = payload.is_active
    user.admin_permissions = _permissions(payload.permissions)
    if payload.temporary_password:
        user.hashed_password = hash_password(payload.temporary_password)
        user.must_change_password = True
        user.token_version += 1
    db.commit()
    db.refresh(user)
    return _admin_account(user)


@router.delete("/accounts/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_account(
    user_id: int,
    db: DB,
    _super_admin: User = Depends(require_super_admin),
) -> Response:
    user = db.scalar(select(User).where(User.id == user_id, User.is_admin.is_(True)))
    if user is None:
        raise HTTPException(status_code=404, detail="Admin account not found")
    if is_super_admin(user):
        raise HTTPException(status_code=422, detail="Super admin account cannot be deleted")
    if user.is_active:
        user.is_active = False
        user.token_version += 1
        db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/organizations", response_model=AdminOrganizationsResponse)
def organizations(
    db: DB,
    _current_user: User = Depends(require_admin_permission("organizations.view")),
) -> AdminOrganizationsResponse:
    rows = db.scalars(
        select(Organization).order_by(Organization.created_at.desc(), Organization.name.asc())
    ).all()
    return AdminOrganizationsResponse(
        total=len(rows),
        organizations=[_organization_response(db, row) for row in rows],
    )


@router.get("/organizations/{organization_id}", response_model=AdminOrganizationDetailResponse)
def organization_detail(
    organization_id: str,
    db: DB,
    _current_user: User = Depends(require_admin_permission("organizations.view")),
) -> AdminOrganizationDetailResponse:
    organization = _organization_or_404(db, organization_id)
    return _organization_detail_response(db, organization)


@router.post(
    "/organizations",
    response_model=AdminOrganizationResponse,
    status_code=status.HTTP_201_CREATED,
)
def create_organization(
    payload: AdminOrganizationCreate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("organizations.write")),
) -> AdminOrganizationResponse:
    organization_slug = payload.slug or available_organization_slug(db, payload.name)
    if is_reserved_organization_slug(organization_slug):
        raise HTTPException(status_code=422, detail="Organization slug is reserved")
    if db.scalar(select(Organization.id).where(Organization.slug == organization_slug)):
        raise HTTPException(status_code=409, detail="Organization slug already exists")
    settings = {}
    if payload.description is not None:
        settings["description"] = payload.description
    if payload.logo_url is not None:
        settings["logo_url"] = str(payload.logo_url)
    organization = Organization(
        id=str(uuid4()),
        name=payload.name,
        slug=organization_slug,
        organization_type=payload.organization_type,
        size_band=payload.size_band,
        status=payload.status,
        plan_code=payload.plan_code,
        billing_email=str(payload.billing_email).lower(),
        website=str(payload.website) if payload.website is not None else None,
        settings=settings,
    )
    db.add(organization)
    try:
        db.commit()
    except IntegrityError as exception:
        db.rollback()
        raise HTTPException(status_code=409, detail="Organization slug already exists") from exception
    db.refresh(organization)
    return _organization_response(db, organization)


@router.patch("/organizations/{organization_id}", response_model=AdminOrganizationResponse)
def update_organization(
    organization_id: str,
    payload: AdminOrganizationUpdate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("organizations.write")),
) -> AdminOrganizationResponse:
    organization = _organization_or_404(db, organization_id)
    changes = payload.model_dump(exclude_unset=True)
    if "slug" in changes and changes["slug"] != organization.slug:
        if is_reserved_organization_slug(changes["slug"]):
            raise HTTPException(status_code=422, detail="Organization slug is reserved")
        if db.scalar(
            select(Organization.id).where(
                Organization.slug == changes["slug"], Organization.id != organization.id
            )
        ):
            raise HTTPException(status_code=409, detail="Organization slug already exists")
    if "billing_email" in changes and changes["billing_email"] is not None:
        changes["billing_email"] = str(changes["billing_email"]).lower()
    if "website" in changes and changes["website"] is not None:
        changes["website"] = str(changes["website"])
    settings = dict(organization.settings or {})
    for field in ("description", "logo_url"):
        if field not in changes:
            continue
        value = changes.pop(field)
        if value is None:
            settings.pop(field, None)
        else:
            settings[field] = str(value)
    organization.settings = settings
    for key, value in changes.items():
        setattr(organization, key, value)
    try:
        db.commit()
    except IntegrityError as exception:
        db.rollback()
        raise HTTPException(status_code=409, detail="Organization slug already exists") from exception
    db.refresh(organization)
    return _organization_response(db, organization)


@router.delete("/organizations/{organization_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_organization(
    organization_id: str,
    db: DB,
    _current_user: User = Depends(require_admin_permission("organizations.delete")),
) -> Response:
    organization = _organization_or_404(db, organization_id)
    if organization.status != "closed":
        organization.status = "closed"
        db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.post(
    "/organizations/{organization_id}/owner-invitations",
    response_model=CompanyInviteResponse,
    status_code=status.HTTP_201_CREATED,
)
def invite_organization_owner(
    organization_id: str,
    payload: CompanyInviteCreate,
    db: DB,
    current_user: User = Depends(require_admin_permission("organizations.write")),
) -> CompanyInviteResponse:
    if payload.role != "owner":
        raise HTTPException(status_code=422, detail="Initial organization invitation must be owner")
    organization = _organization_or_404(db, organization_id)
    try:
        invitation, token = create_company_invitation(
            db, organization, str(payload.email), "owner", current_user
        )
    except CompanyInvitationConflict as exception:
        raise HTTPException(status_code=409, detail=str(exception)) from exception
    return CompanyInviteResponse(
        token=token,
        email=invitation.email,
        role="owner",
        permissions=effective_company_permissions(invitation),
        organization_id=organization.id,
        organization_name=organization.name,
        expires_at=invitation.expires_at,
    )


@router.get("/students", response_model=AdminStudentsResponse)
def students(
    db: DB,
    _current_user: User = Depends(require_admin_permission("students.view")),
) -> AdminStudentsResponse:
    rows = db.scalars(
        select(User)
        .where(*CANDIDATE_FILTER)
        .order_by(User.created_at.desc(), User.id.desc())
        .limit(MAX_ROWS)
    ).all()
    total = db.scalar(select(func.count()).select_from(User).where(*CANDIDATE_FILTER)) or 0
    return AdminStudentsResponse(total=total, students=[_student_response(row) for row in rows])


@router.get("/students/{user_id}", response_model=AdminStudentDetailResponse)
def student_detail(
    user_id: int,
    db: DB,
    _current_user: User = Depends(require_admin_permission("students.view")),
) -> AdminStudentDetailResponse:
    student = _candidate_or_404(db, user_id)
    return _student_detail_response(db, student)


@router.post("/students", response_model=AdminStudentResponse, status_code=status.HTTP_201_CREATED)
def create_student(
    payload: AdminStudentCreate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("students.write")),
) -> AdminStudentResponse:
    email = str(payload.email).strip().lower()
    if db.scalar(select(User.id).where(func.lower(User.email) == email)):
        raise HTTPException(status_code=409, detail="Email already registered")
    student = User(
        full_name=payload.full_name,
        email=email,
        hashed_password=hash_password(payload.temporary_password),
        is_active=payload.is_active,
        is_admin=False,
        role="student",
        admin_permissions=[],
        must_change_password=False,
        preferred_locale=payload.preferred_locale,
    )
    db.add(student)
    db.commit()
    db.refresh(student)
    return _student_response(student)


@router.patch("/students/{user_id}", response_model=AdminStudentResponse)
def update_student(
    user_id: int,
    payload: AdminStudentUpdate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("students.write")),
) -> AdminStudentResponse:
    student = _candidate_or_404(db, user_id)
    changes = payload.model_dump(exclude_unset=True)
    if "email" in changes:
        email = str(changes["email"]).strip().lower()
        if db.scalar(select(User.id).where(func.lower(User.email) == email, User.id != user_id)):
            raise HTTPException(status_code=409, detail="Email already registered")
        changes["email"] = email
    temporary_password = changes.pop("temporary_password", None)
    if temporary_password:
        student.hashed_password = hash_password(temporary_password)
        student.must_change_password = False
        student.token_version += 1
    if "is_active" in changes and changes["is_active"] is False and student.is_active:
        student.token_version += 1
    for key, value in changes.items():
        setattr(student, key, value)
    db.commit()
    db.refresh(student)
    return _student_response(student)


@router.delete("/students/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_student(
    user_id: int,
    db: DB,
    _current_user: User = Depends(require_admin_permission("students.delete")),
) -> Response:
    student = _candidate_or_404(db, user_id)
    if student.is_active:
        student.is_active = False
        student.token_version += 1
        db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/applications", response_model=AdminApplicationsResponse)
def applications(
    db: DB,
    _current_user: User = Depends(require_admin_permission("applications.view")),
) -> AdminApplicationsResponse:
    rows = db.scalars(
        select(JobApplication)
        .join(User, User.id == JobApplication.user_id)
        .where(*CANDIDATE_FILTER)
        .order_by(JobApplication.applied_at.desc(), JobApplication.id.desc())
        .limit(MAX_ROWS)
    ).all()
    total = db.scalar(
        select(func.count())
        .select_from(JobApplication)
        .join(User, User.id == JobApplication.user_id)
        .where(*CANDIDATE_FILTER)
    ) or 0
    return AdminApplicationsResponse(
        total=total,
        applications=[_application_response(db, row) for row in rows],
        student_options=_student_options(db),
    )


@router.post("/applications", response_model=AdminApplicationResponse, status_code=status.HTTP_201_CREATED)
def create_application(
    payload: AdminApplicationCreate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("applications.write")),
) -> AdminApplicationResponse:
    student = _candidate_or_404(db, payload.user_id, active=True)
    application = JobApplication(
        id=str(uuid4()),
        user_id=student.id,
        company=payload.company,
        role=payload.role,
        stage=payload.stage,
        next_action=payload.next_action,
        note=payload.note,
    )
    db.add(application)
    db.commit()
    db.refresh(application)
    return _application_response(db, application)


@router.patch("/applications/{application_id}", response_model=AdminApplicationResponse)
def update_application(
    application_id: str,
    payload: AdminApplicationUpdate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("applications.write")),
) -> AdminApplicationResponse:
    application = db.get(JobApplication, application_id)
    if application is None:
        raise HTTPException(status_code=404, detail="Application not found")
    for key, value in payload.model_dump(exclude_unset=True).items():
        setattr(application, key, value)
    db.commit()
    db.refresh(application)
    return _application_response(db, application)


@router.delete("/applications/{application_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_application(
    application_id: str,
    db: DB,
    _current_user: User = Depends(require_admin_permission("applications.delete")),
) -> Response:
    application = db.get(JobApplication, application_id)
    if application is None:
        raise HTTPException(status_code=404, detail="Application not found")
    db.delete(application)
    db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/interviews", response_model=AdminInterviewsResponse)
def interviews(
    db: DB,
    _current_user: User = Depends(require_admin_permission("interviews.view")),
) -> AdminInterviewsResponse:
    rows = db.scalars(
        select(CareerInterview)
        .join(User, User.id == CareerInterview.user_id)
        .where(*CANDIDATE_FILTER)
        .order_by(CareerInterview.created_at.desc(), CareerInterview.id.desc())
        .limit(MAX_ROWS)
    ).all()
    total = db.scalar(
        select(func.count())
        .select_from(CareerInterview)
        .join(User, User.id == CareerInterview.user_id)
        .where(*CANDIDATE_FILTER)
    ) or 0
    return AdminInterviewsResponse(
        total=total,
        interviews=[_interview_response(db, row) for row in rows],
        student_options=_student_options(db),
    )


@router.post("/interviews", response_model=AdminInterviewResponse, status_code=status.HTTP_201_CREATED)
def create_interview(
    payload: AdminInterviewCreate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("interviews.write")),
) -> AdminInterviewResponse:
    student = _candidate_or_404(db, payload.user_id, active=True)
    try:
        interview = start_interview(db, student.id, language=payload.language)
    except (AIUnavailableError, AIOutputError, AIProviderError) as exception:
        raise HTTPException(status_code=503, detail=str(exception)) from exception
    return _interview_response(db, interview)


@router.patch("/interviews/{interview_id}", response_model=AdminInterviewResponse)
def update_interview(
    interview_id: str,
    payload: AdminInterviewUpdate,
    db: DB,
    _current_user: User = Depends(require_admin_permission("interviews.write")),
) -> AdminInterviewResponse:
    interview = db.get(CareerInterview, interview_id)
    if interview is None:
        raise HTTPException(status_code=404, detail="Interview not found")
    for key, value in payload.model_dump(exclude_unset=True).items():
        setattr(interview, key, value)
    db.commit()
    db.refresh(interview)
    return _interview_response(db, interview)


@router.delete("/interviews/{interview_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_interview(
    interview_id: str,
    db: DB,
    _current_user: User = Depends(require_admin_permission("interviews.delete")),
) -> Response:
    interview = db.get(CareerInterview, interview_id)
    if interview is None:
        raise HTTPException(status_code=404, detail="Interview not found")
    db.execute(delete(CareerInterviewAnswer).where(CareerInterviewAnswer.interview_id == interview_id))
    db.delete(interview)
    db.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/dashboard", response_model=AdminDashboardResponse)
def dashboard(db: DB, current_user: User = Depends(require_admin_permission("dashboard.view"))) -> AdminDashboardResponse:
    """Admin hesaplarını hariç tutarak canlı yönetim özetini döndür."""
    module_counts = {
        "students": _count(db, User),
        "readiness": _count(db, CareerAnalysis),
        "skill-passport": _count(db, Evidence),
        "job-radar": _count(db, JobOpportunity),
        "applications": _count(db, JobApplication),
        "interviews": _count(db, CareerInterview),
    }
    current_cv_count = db.scalar(
        select(func.count())
        .select_from(CvDocument)
        .join(User, User.id == CvDocument.user_id)
        .where(*STUDENT_FILTER, CvDocument.is_current.is_(True))
    ) or 0
    ready_analysis_count = db.scalar(
        select(func.count())
        .select_from(CareerAnalysis)
        .join(User, User.id == CareerAnalysis.user_id)
        .where(*STUDENT_FILTER, CareerAnalysis.status == "ready")
    ) or 0
    active_application_count = db.scalar(
        select(func.count())
        .select_from(JobApplication)
        .join(User, User.id == JobApplication.user_id)
        .where(*STUDENT_FILTER, JobApplication.stage != "rejected")
    ) or 0
    students = db.scalars(
        select(User)
        .where(*STUDENT_FILTER)
        .order_by(User.created_at.desc(), User.id.desc())
        .limit(5)
    ).all()

    permissions = set(effective_admin_permissions(current_user))
    metrics = [
        ("students.view", {"label": "Aktif öğrenci", "value": module_counts["students"], "detail": "Admin hesapları hariç"}),
        ("readiness.view", {"label": "Mevcut CV", "value": current_cv_count, "detail": "Aktif CV kaydı"}),
        ("readiness.view", {"label": "Hazır analiz", "value": ready_analysis_count, "detail": "Analizi tamamlanan CV"}),
        ("applications.view", {"label": "Aktif başvuru", "value": active_application_count, "detail": "Reddedilenler hariç"}),
    ]
    visible_counts = {
        key: value for key, value in module_counts.items() if MODULE_PERMISSIONS[key] in permissions
    }

    return AdminDashboardResponse(
        stats=[metric for permission, metric in metrics if permission in permissions],
        module_counts=visible_counts,
        recent_students=[
            {
                "name": student.full_name,
                "email": student.email,
                "registered_at": _date(student.created_at),
            }
            for student in students
        ] if "students.view" in permissions else [],
    )


@router.get("/modules/{module}", response_model=AdminModuleResponse)
def module(module: ModuleKey, db: DB, current_user: User = Depends(require_admin)) -> AdminModuleResponse:
    """Her desteklenen yönetim modülü için yalnız gerçek kayıtları döndür."""
    loaders = {
        "students": _students,
        "readiness": _readiness,
        "skill-passport": _skill_passport,
        "job-radar": _job_radar,
        "applications": _applications,
        "interviews": _interviews,
    }
    ensure_admin_permission(current_user, MODULE_PERMISSIONS[module])
    return loaders[module](db)


def _count(db: Session, model: type) -> int:
    statement = select(func.count()).select_from(model)
    if model is User:
        return db.scalar(statement.where(*STUDENT_FILTER)) or 0

    return db.scalar(
        statement.join(User, User.id == model.user_id).where(*STUDENT_FILTER)
    ) or 0


def _students(db: Session) -> AdminModuleResponse:
    students = db.scalars(
        select(User)
        .where(*STUDENT_FILTER)
        .order_by(User.created_at.desc(), User.id.desc())
        .limit(MAX_ROWS)
    ).all()
    student_ids = [student.id for student in students]
    cv_user_ids = _ids(db, select(CvDocument.user_id).where(CvDocument.user_id.in_(student_ids), CvDocument.is_current.is_(True))) if student_ids else set()
    analyses = db.scalars(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id.in_(student_ids))
        .order_by(CareerAnalysis.created_at.desc())
    ).all() if student_ids else []
    analysis_statuses: dict[int, str] = {}
    for analysis in analyses:
        analysis_statuses.setdefault(analysis.user_id, analysis.status)

    return _module(
        "Öğrenciler",
        "Aktif, admin olmayan kullanıcı hesapları.",
        [
            _row(
                student.full_name,
                student.email,
                "CV yüklendi" if student.id in cv_user_ids else "CV yok",
                analysis_statuses.get(student.id, "Analiz yok"),
                f"Kayıt: {_date(student.created_at)}",
            )
            for student in students
        ],
        total=_count(db, User),
    )


def _readiness(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(CareerAnalysis, User.full_name)
        .join(User, User.id == CareerAnalysis.user_id)
        .where(*STUDENT_FILTER)
        .order_by(CareerAnalysis.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Readiness Analizi",
        "CV analizlerinden gelen gerçek işlem durumu ve yetenek sayısı.",
        [
            _row(
                analysis.current_role or analysis.file_name or "Rol belirtilmedi",
                user_name,
                f"{len(analysis.skills)} yetenek",
                analysis.status,
                f"Analiz: {_date(analysis.created_at)}",
            )
            for analysis, user_name in rows
        ],
        total=_count(db, CareerAnalysis),
    )


def _skill_passport(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(Evidence, CareerTask.title, User.full_name)
        .join(CareerTask, CareerTask.id == Evidence.task_id)
        .join(User, User.id == Evidence.user_id)
        .where(*STUDENT_FILTER)
        .order_by(Evidence.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Yetenek Pasaportu",
        "Öğrencilerin yüklediği kanıt kayıtları.",
        [
            _row(
                task_title,
                user_name,
                evidence.kind,
                evidence.status,
                _confidence(evidence.confidence),
            )
            for evidence, task_title, user_name in rows
        ],
        total=_count(db, Evidence),
    )


def _job_radar(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(JobOpportunity, User.full_name)
        .join(User, User.id == JobOpportunity.user_id)
        .where(*STUDENT_FILTER)
        .order_by(JobOpportunity.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "İş Radarı",
        "Öğrencilerin analiz ettiği gerçek iş ilanları.",
        [
            _row(
                job.title or "Başlıksız ilan",
                job.company or job.source or "Şirket belirtilmedi",
                f"%{job.match_score}" if job.match_score is not None else "Skor yok",
                job.status,
                f"Öğrenci: {user_name}",
            )
            for job, user_name in rows
        ],
        total=_count(db, JobOpportunity),
    )


def _applications(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(JobApplication, User.full_name)
        .join(User, User.id == JobApplication.user_id)
        .where(*STUDENT_FILTER)
        .order_by(JobApplication.applied_at.desc(), JobApplication.id.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Başvurular",
        "Öğrencilerin kaydettiği gerçek başvuru kayıtları.",
        [
            _row(
                f"{application.company} · {application.role}",
                f"Öğrenci: {user_name}",
                _date(application.applied_at),
                application.stage,
                application.next_action or "Sonraki aksiyon yok",
            )
            for application, user_name in rows
        ],
        total=_count(db, JobApplication),
    )


def _interviews(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(CareerInterview, User.full_name)
        .join(User, User.id == CareerInterview.user_id)
        .where(*STUDENT_FILTER)
        .order_by(CareerInterview.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Mülakatlar",
        "Öğrencilerin başlattığı gerçek mülakat simülasyonları.",
        [
            _row(
                interview.target_role,
                f"Öğrenci: {user_name}",
                f"{len(interview.questions)} soru",
                interview.status,
                f"Başlatıldı: {_date(interview.created_at)}",
            )
            for interview, user_name in rows
        ],
        total=_count(db, CareerInterview),
    )


def _module(
    title: str,
    subtitle: str,
    rows: Iterable[AdminTableRow],
    total: int,
) -> AdminModuleResponse:
    items = list(rows)
    return AdminModuleResponse(title=title, subtitle=subtitle, total=total, rows=items)


def _row(name: str, meta: str, score: str, status: str, next_action: str) -> AdminTableRow:
    return AdminTableRow(name=name, meta=meta, score=score, status=status, next=next_action)


def _ids(db: Session, query) -> set[int]:
    return set(db.scalars(query).all())


def _date(value) -> str | None:
    return value.isoformat() if value is not None else None


def _confidence(value: float | None) -> str:
    return f"AI güveni %{round(value * 100)}" if value is not None else "AI güveni yok"
