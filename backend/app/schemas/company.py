from datetime import datetime
from typing import Literal

from pydantic import BaseModel, EmailStr, Field, field_validator

from app.core.company_permissions import normalize_explicit_company_permissions


CompanyRole = Literal["owner", "admin", "recruiter", "hiring_manager", "viewer"]
PositionStatus = Literal["draft", "open", "paused", "closed", "archived"]
EmploymentType = Literal["full_time", "part_time", "contract", "internship"]
WorkplaceType = Literal["onsite", "hybrid", "remote"]


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
    employment_type: EmploymentType | None = None
    workplace_type: WorkplaceType | None = None
    description: str | None = Field(default=None, max_length=10000)
    application_deadline: datetime | None = None
    status: PositionStatus = "draft"


class CompanyPositionUpdate(BaseModel):
    title: str | None = Field(default=None, min_length=2, max_length=160)
    department: str | None = Field(default=None, max_length=120)
    employment_type: EmploymentType | None = None
    workplace_type: WorkplaceType | None = None
    description: str | None = Field(default=None, max_length=10000)
    application_deadline: datetime | None = None
    status: PositionStatus | None = None


class CompanyPositionResponse(BaseModel):
    id: str
    title: str
    department: str | None
    employment_type: EmploymentType | None
    workplace_type: WorkplaceType | None
    description: str | None
    status: PositionStatus
    application_deadline: datetime | None
    opened_at: datetime | None
    closed_at: datetime | None
    application_count: int = 0
    created_at: datetime
    updated_at: datetime


class CompanyPositionsResponse(BaseModel):
    items: list[CompanyPositionResponse]


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


class CompanyApplicationsResponse(BaseModel):
    items: list[CompanyApplicationResponse]


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
