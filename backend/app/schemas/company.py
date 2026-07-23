from datetime import date, datetime
from decimal import Decimal
from typing import Any, Literal

from pydantic import BaseModel, EmailStr, Field, field_validator, model_validator

from app.core.company_permissions import normalize_explicit_company_permissions


CompanyRole = Literal["owner", "admin", "recruiter", "hiring_manager", "viewer"]
PositionStatus = Literal["draft", "published", "paused", "closed", "archived"]
EmploymentType = Literal["full_time", "part_time", "contract", "internship"]
WorkplaceType = Literal["onsite", "hybrid", "remote"]
ApplicationStage = Literal["new", "assessment_pending", "assessment_in_progress", "technical_review", "shortlisted", "interview", "offer", "hired", "rejected", "withdrawn"]


class CompanyInviteCreate(BaseModel):
    email: EmailStr
    role: CompanyRole = "owner"
    permissions: list[str] | None = None

    @field_validator("permissions")
    @classmethod
    def validate_permissions(cls, value: list[str] | None) -> list[str] | None:
        return normalize_explicit_company_permissions(value) if value is not None else None


class CompanyInviteResponse(BaseModel):
    token: str
    email: EmailStr
    role: CompanyRole
    permissions: list[str]
    organization_id: str
    organization_name: str
    expires_at: datetime


class CompanyInviteAccept(BaseModel):
    full_name: str = Field(min_length=2, max_length=100)
    password: str = Field(min_length=8, max_length=128)


class CompanyOrganizationProfile(BaseModel):
    name: str
    slug: str
    website: str | None
    description: str | None
    logo_url: str | None


class CompanyMembershipSummary(BaseModel):
    organization_id: str
    organization_name: str
    organization_slug: str
    organization_type: str
    organization_status: str
    plan_code: str
    billing_email: EmailStr
    website: str | None
    role: CompanyRole
    permissions: list[str]


class CompanyContextResponse(BaseModel):
    memberships: list[CompanyMembershipSummary]


class CompanyAssessmentUsage(BaseModel):
    used: int
    quota: int | None = None


class CompanyDashboardIndicators(BaseModel):
    active_positions: int
    new_applications: int
    assessment_pending: int
    technical_review_pending: int
    shortlisted: int
    assessment_usage: CompanyAssessmentUsage


class CompanyDashboardPeriod(BaseModel):
    key: Literal["7d", "30d", "90d"]
    from_: datetime = Field(alias="from", serialization_alias="from")
    to: datetime

    model_config = {"populate_by_name": True}


class CompanyDashboardTaskPosition(BaseModel):
    id: str
    title: str


class CompanyDashboardTask(BaseModel):
    type: Literal["new_applications", "technical_review", "scorecard_missing", "position_deadline", "retention_due"]
    priority: int
    count: int
    position: CompanyDashboardTaskPosition | None = None
    target: str


class CompanyLargestLossStage(BaseModel):
    stage: str
    count: int


class CompanyDashboardSummary(BaseModel):
    application_to_assessment_rate: float | None
    assessment_to_interview_rate: float | None
    average_shortlist_hours: float | None
    largest_loss_stage: CompanyLargestLossStage | None


class CompanyDashboardResponse(BaseModel):
    organization: CompanyMembershipSummary
    as_of: datetime
    period: CompanyDashboardPeriod
    indicators: CompanyDashboardIndicators
    tasks: list[CompanyDashboardTask]
    summary: CompanyDashboardSummary
    members_total: int
    members_active: int
    invitations_pending: int


class CompanyPositionCreate(BaseModel):
    title: str = Field(min_length=2, max_length=160)
    department: str | None = Field(default=None, max_length=120)
    level: str | None = Field(default=None, max_length=80)
    employment_type: EmploymentType | None = None
    workplace_type: WorkplaceType | None = None
    location: str | None = Field(default=None, max_length=180)
    salary_min: Decimal | None = Field(default=None, ge=0)
    salary_max: Decimal | None = Field(default=None, ge=0)
    salary_currency: str | None = Field(default=None, min_length=3, max_length=3)
    description: str | None = Field(default=None, max_length=10000)
    responsibilities: str | None = Field(default=None, max_length=20000)
    must_have_skills: list[str] = Field(default_factory=list, max_length=100)
    preferred_skills: list[str] = Field(default_factory=list, max_length=100)
    learnable_skills: list[str] = Field(default_factory=list, max_length=100)
    experience_expectation: str | None = Field(default=None, max_length=5000)
    language_work_authorization: str | None = Field(default=None, max_length=5000)
    source_text: str | None = Field(default=None, max_length=40000)
    ats_terms: list[str] = Field(default_factory=list, max_length=100)
    ats_notes: str | None = Field(default=None, max_length=10000)
    evaluation_config: dict[str, Any] = Field(default_factory=dict)
    application_form_id: str | None = Field(default=None, max_length=80)
    assessment_template_id: str | None = Field(default=None, max_length=80)
    recruiter_membership_id: str | None = None
    technical_manager_membership_id: str | None = None
    retention_days: int = Field(default=180, ge=1, le=3650)
    application_deadline: datetime | None = None
    target_start_date: date | None = None
    status: PositionStatus = "draft"

    @field_validator("salary_currency")
    @classmethod
    def normalize_currency(cls, value: str | None) -> str | None:
        return value.upper() if value else None

    @field_validator("must_have_skills", "preferred_skills", "learnable_skills", "ats_terms")
    @classmethod
    def normalize_string_list(cls, value: list[str]) -> list[str]:
        return list(dict.fromkeys(item.strip() for item in value if item.strip()))

    @model_validator(mode="after")
    def validate_salary_range(self):
        if self.salary_min is not None and self.salary_max is not None and self.salary_min > self.salary_max:
            raise ValueError("salary_min cannot exceed salary_max")
        return self


class CompanyPositionUpdate(BaseModel):
    title: str | None = Field(default=None, min_length=2, max_length=160)
    department: str | None = Field(default=None, max_length=120)
    level: str | None = Field(default=None, max_length=80)
    employment_type: EmploymentType | None = None
    workplace_type: WorkplaceType | None = None
    location: str | None = Field(default=None, max_length=180)
    salary_min: Decimal | None = Field(default=None, ge=0)
    salary_max: Decimal | None = Field(default=None, ge=0)
    salary_currency: str | None = Field(default=None, min_length=3, max_length=3)
    description: str | None = Field(default=None, max_length=10000)
    responsibilities: str | None = Field(default=None, max_length=20000)
    must_have_skills: list[str] | None = Field(default=None, max_length=100)
    preferred_skills: list[str] | None = Field(default=None, max_length=100)
    learnable_skills: list[str] | None = Field(default=None, max_length=100)
    experience_expectation: str | None = Field(default=None, max_length=5000)
    language_work_authorization: str | None = Field(default=None, max_length=5000)
    source_text: str | None = Field(default=None, max_length=40000)
    ats_terms: list[str] | None = Field(default=None, max_length=100)
    ats_notes: str | None = Field(default=None, max_length=10000)
    evaluation_config: dict[str, Any] | None = None
    application_form_id: str | None = Field(default=None, max_length=80)
    assessment_template_id: str | None = Field(default=None, max_length=80)
    recruiter_membership_id: str | None = None
    technical_manager_membership_id: str | None = None
    retention_days: int | None = Field(default=None, ge=1, le=3650)
    application_deadline: datetime | None = None
    target_start_date: date | None = None
    status: PositionStatus | None = None

    @field_validator("salary_currency")
    @classmethod
    def normalize_currency(cls, value: str | None) -> str | None:
        return value.upper() if value else None

    @field_validator("must_have_skills", "preferred_skills", "learnable_skills", "ats_terms")
    @classmethod
    def normalize_optional_string_list(cls, value: list[str] | None) -> list[str] | None:
        return list(dict.fromkeys(item.strip() for item in value if item.strip())) if value is not None else None


class CompanyPositionResponse(BaseModel):
    id: str
    slug: str
    public_id: str
    public_path: str
    title: str
    department: str | None
    level: str | None
    employment_type: EmploymentType | None
    workplace_type: WorkplaceType | None
    location: str | None
    salary_min: Decimal | None
    salary_max: Decimal | None
    salary_currency: str | None
    description: str | None
    responsibilities: str | None
    must_have_skills: list[str]
    preferred_skills: list[str]
    learnable_skills: list[str]
    experience_expectation: str | None
    language_work_authorization: str | None
    source_text: str | None
    ats_terms: list[str]
    ats_notes: str | None
    evaluation_config: dict[str, Any]
    application_form_id: str | None
    assessment_template_id: str | None
    recruiter_membership_id: str | None
    recruiter_name: str | None = None
    technical_manager_membership_id: str | None
    technical_manager_name: str | None = None
    retention_days: int
    status: PositionStatus
    application_deadline: datetime | None
    target_start_date: date | None
    opened_at: datetime | None
    closed_at: datetime | None
    application_count: int = 0
    assessment_completed_count: int = 0
    shortlisted_count: int = 0
    created_at: datetime
    updated_at: datetime


class CompanyPositionsResponse(BaseModel):
    items: list[CompanyPositionResponse]
    total: int = 0
    page: int = 1
    page_size: int = 25
    status_counts: dict[PositionStatus, int] = Field(default_factory=dict)


class CompanyAtsConfigUpdate(BaseModel):
    provider: Literal["generic", "greenhouse", "lever", "workable", "sap_successfactors", "teamtailor", "custom"] = "generic"
    system_name: str | None = Field(default=None, max_length=120)
    terms: list[str] = Field(default_factory=list, max_length=200)
    notes: str | None = Field(default=None, max_length=20000)
    candidate_analysis_instructions: str | None = Field(default=None, max_length=20000)

    @field_validator("terms")
    @classmethod
    def normalize_terms(cls, value: list[str]) -> list[str]:
        return list(dict.fromkeys(item.strip() for item in value if item.strip()))


class CompanyAtsConfigResponse(CompanyAtsConfigUpdate):
    organization_id: str
    updated_at: datetime | None = None


class CompanyEffectiveAtsConfig(BaseModel):
    provider: str
    system_name: str | None
    organization_terms: list[str]
    position_terms: list[str]
    effective_terms: list[str]
    organization_notes: str | None
    position_notes: str | None
    candidate_analysis_instructions: str | None


class CompanyPositionCounts(BaseModel):
    applications: int
    assessment_completed: int
    shortlisted: int


class CompanyCriteriaVersionUpdate(BaseModel):
    criteria: dict[str, Any]


class CompanyCriteriaVersionResponse(BaseModel):
    id: str
    version_number: int
    status: Literal["draft", "approved", "superseded"]
    criteria: dict[str, Any]
    ai_suggestions: dict[str, Any]
    approved_by_membership_id: str | None
    approved_at: datetime | None
    created_at: datetime
    updated_at: datetime


class CompanyPositionAiAnalysisResponse(BaseModel):
    id: str
    criteria_version_id: str
    status: Literal["queued", "processing", "completed", "failed"]
    result: dict[str, Any]
    error_code: str | None
    error_message: str | None
    created_at: datetime
    completed_at: datetime | None


ShareChannel = Literal["linkedin", "kariyer_net", "indeed", "company_website", "social_media", "employee_referral", "agency", "email", "other"]


class CompanyShareLinkCreate(BaseModel):
    channel: ShareChannel
    label: str = Field(min_length=2, max_length=160)
    campaign: str | None = Field(default=None, max_length=120)
    expires_at: datetime | None = None
    agency_reference: str | None = Field(default=None, max_length=160)
    employee_reference: str | None = Field(default=None, max_length=160)
    application_limit: int | None = Field(default=None, ge=1, le=1_000_000)
    source_description: str | None = Field(default=None, max_length=5000)
    is_active: bool = True


class CompanyShareLinkUpdate(BaseModel):
    label: str | None = Field(default=None, min_length=2, max_length=160)
    campaign: str | None = Field(default=None, max_length=120)
    expires_at: datetime | None = None
    agency_reference: str | None = Field(default=None, max_length=160)
    employee_reference: str | None = Field(default=None, max_length=160)
    application_limit: int | None = Field(default=None, ge=1, le=1_000_000)
    source_description: str | None = Field(default=None, max_length=5000)
    is_active: bool | None = None


class CompanyShareLinkResponse(BaseModel):
    id: str
    channel: str
    label: str
    short_code: str
    short_path: str
    campaign: str | None
    expires_at: datetime | None
    agency_reference: str | None
    employee_reference: str | None
    application_limit: int | None
    source_description: str | None
    is_active: bool
    click_count: int
    application_count: int
    assessment_completed_count: int
    shortlisted_count: int
    created_at: datetime


class CompanyPositionActivityResponse(BaseModel):
    id: str
    event_type: str
    entity_type: str
    entity_id: str | None
    actor_membership_id: str | None
    actor_user_id: int | None
    actor_name: str | None = None
    details: dict[str, Any]
    occurred_at: datetime


class CompanyPositionMemberResponse(BaseModel):
    membership_id: str
    full_name: str
    role: CompanyRole


class CompanyPositionDetailResponse(BaseModel):
    position: CompanyPositionResponse
    counts: CompanyPositionCounts
    ats_config: CompanyEffectiveAtsConfig | None
    criteria_versions: list[CompanyCriteriaVersionResponse]
    active_criteria_version: CompanyCriteriaVersionResponse | None
    ai_analyses: list[CompanyPositionAiAnalysisResponse]
    share_links: list[CompanyShareLinkResponse]
    applications: list[dict[str, Any]]
    assessments: list[dict[str, Any]]
    comparison: list[dict[str, Any]]
    activities: list[CompanyPositionActivityResponse]
    members: list[CompanyPositionMemberResponse]


class PublicOrganizationResponse(BaseModel):
    name: str
    slug: str
    website: str | None
    logo_url: str | None


QuestionType = Literal["text", "number", "single_choice"]


class CompanyPositionQuestionCreate(BaseModel):
    question_text: str = Field(min_length=2, max_length=1000)
    question_type: QuestionType = "text"
    options: list[str] = Field(default_factory=list, max_length=50)
    is_required: bool = True
    sort_order: int = Field(default=0, ge=0, le=1000)

    @field_validator("options")
    @classmethod
    def normalize_options(cls, value: list[str]) -> list[str]:
        return list(dict.fromkeys(item.strip() for item in value if item.strip()))


class CompanyPositionQuestionUpdate(BaseModel):
    question_text: str | None = Field(default=None, min_length=2, max_length=1000)
    question_type: QuestionType | None = None
    options: list[str] | None = Field(default=None, max_length=50)
    is_required: bool | None = None
    sort_order: int | None = Field(default=None, ge=0, le=1000)

    @field_validator("options")
    @classmethod
    def normalize_options(cls, value: list[str] | None) -> list[str] | None:
        if value is None:
            return None
        return list(dict.fromkeys(item.strip() for item in value if item.strip()))


class CompanyPositionQuestionResponse(BaseModel):
    id: str
    position_id: str
    question_text: str
    question_type: QuestionType
    options: list[str]
    is_required: bool
    sort_order: int
    created_at: datetime
    updated_at: datetime


class PublicPositionResponse(BaseModel):
    id: str
    public_id: str
    public_path: str
    title: str
    department: str | None
    level: str | None
    employment_type: EmploymentType | None
    workplace_type: WorkplaceType | None
    location: str | None
    description: str | None
    responsibilities: str | None
    must_have_skills: list[str]
    preferred_skills: list[str]
    application_deadline: datetime | None
    status: Literal["published", "paused", "closed", "archived"]
    application_open: bool
    estimated_application_minutes: int
    estimated_assessment_minutes: int | None
    questions: list[CompanyPositionQuestionResponse] = Field(default_factory=list)


class PublicPositionPageResponse(BaseModel):
    organization: PublicOrganizationResponse
    position: PublicPositionResponse
    source: dict[str, Any] | None = None


class PublicPositionListResponse(BaseModel):
    items: list[PublicPositionPageResponse]
    total: int
    limit: int
    offset: int
    has_more: bool


class CandidatePositionApplicationCreate(BaseModel):
    cv_document_id: str
    share_link_code: str | None = Field(default=None, max_length=16)
    consent: dict[str, Any]
    selected_projects: list[dict[str, Any]] = Field(default_factory=list, max_length=20)
    application_answers: list[dict[str, Any]] = Field(default_factory=list, max_length=50)

    @model_validator(mode="after")
    def validate_consent(self):
        if self.consent.get("accepted") is not True or not str(self.consent.get("version", "")).strip():
            raise ValueError("Explicit consent and consent version are required")
        return self


class CandidatePositionApplicationResponse(BaseModel):
    id: str
    position_id: str
    current_stage: str
    analysis_status: str
    created: bool
    applied_at: datetime


class CompanyApplicationResponse(BaseModel):
    id: str
    position_id: str
    position_title: str
    candidate_name: str
    candidate_email: str
    current_stage: str
    first_reviewed_at: datetime | None
    applied_at: datetime
    retention_expires_at: datetime | None
    cv_document_id: str | None = None
    application_snapshot: dict[str, Any] = Field(default_factory=dict)
    analysis_result: dict[str, Any] = Field(default_factory=dict)


class CompanyApplicationsResponse(BaseModel):
    items: list[CompanyApplicationResponse]


class CompanyApplicationAction(BaseModel):
    stage: ApplicationStage | None = None
    note: str | None = Field(default=None, max_length=5000)
    decision: str | None = Field(default=None, max_length=160)
    idempotency_key: str = Field(min_length=8, max_length=120)

    @model_validator(mode="after")
    def require_action(self):
        if self.stage is None and not (self.note or "").strip() and not (self.decision or "").strip():
            raise ValueError("At least one application action is required")
        return self


class CompanyApplicationActionResponse(BaseModel):
    id: str
    current_stage: ApplicationStage
    first_reviewed_at: datetime | None
    updated_at: datetime


class CompanyAssessmentResponse(BaseModel):
    id: str
    application_id: str
    position_title: str
    candidate_name: str
    title: str | None
    status: str
    assigned_at: datetime
    completed_at: datetime | None


class CompanyAssessmentsResponse(BaseModel):
    usage: CompanyAssessmentUsage
    items: list[CompanyAssessmentResponse]


class CompanyMemberResponse(BaseModel):
    membership_id: str
    user_id: int
    full_name: str
    email: EmailStr
    role: CompanyRole
    permissions: list[str]
    status: str
    created_at: datetime


class CompanyPendingInviteResponse(BaseModel):
    id: str
    email: EmailStr
    role: CompanyRole
    permissions: list[str]
    expires_at: datetime


class CompanyMembersResponse(BaseModel):
    permission_keys: list[str]
    members: list[CompanyMemberResponse]
    pending_invitations: list[CompanyPendingInviteResponse]


class CompanyMemberUpdate(BaseModel):
    role: CompanyRole | None = None
    status: Literal["active", "suspended"] | None = None
    permissions: list[str] | None = None

    @field_validator("permissions")
    @classmethod
    def validate_permissions(cls, value: list[str] | None) -> list[str] | None:
        return normalize_explicit_company_permissions(value) if value is not None else None


class CompanyOrganizationUpdate(BaseModel):
    name: str = Field(min_length=2, max_length=160)
    billing_email: EmailStr
    website: str | None = Field(default=None, max_length=2048)
