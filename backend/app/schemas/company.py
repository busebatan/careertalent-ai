from datetime import datetime
from typing import Literal

from pydantic import BaseModel, EmailStr, Field


CompanyRole = Literal["owner", "admin", "recruiter", "hiring_manager", "viewer"]


class CompanyInviteCreate(BaseModel):
    email: EmailStr
    role: CompanyRole = "owner"


class CompanyInviteResponse(BaseModel):
    token: str
    email: EmailStr
    role: CompanyRole
    organization_id: str
    organization_name: str
    expires_at: datetime


class CompanyInviteAccept(BaseModel):
    full_name: str = Field(min_length=2, max_length=100)
    password: str = Field(min_length=8, max_length=128)


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


class CompanyDashboardResponse(BaseModel):
    organization: CompanyMembershipSummary
    members_total: int
    members_active: int
    invitations_pending: int


class CompanyMemberResponse(BaseModel):
    membership_id: str
    user_id: int
    full_name: str
    email: EmailStr
    role: CompanyRole
    status: str
    created_at: datetime


class CompanyPendingInviteResponse(BaseModel):
    id: str
    email: EmailStr
    role: CompanyRole
    expires_at: datetime


class CompanyMembersResponse(BaseModel):
    members: list[CompanyMemberResponse]
    pending_invitations: list[CompanyPendingInviteResponse]


class CompanyMemberUpdate(BaseModel):
    role: CompanyRole | None = None
    status: Literal["active", "suspended"] | None = None


class CompanyOrganizationUpdate(BaseModel):
    name: str = Field(min_length=2, max_length=160)
    billing_email: EmailStr
    website: str | None = Field(default=None, max_length=2048)
