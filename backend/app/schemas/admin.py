"""Yalnız yönetici yüzeyinin ihtiyaç duyduğu, sunumdan bağımsız gerçek veri sözleşmeleri."""

import re
from typing import Literal

from pydantic import AnyHttpUrl, BaseModel, EmailStr, Field, field_validator, model_validator


OrganizationType = Literal["employer", "agency"]
OrganizationSizeBand = Literal["smb", "mid_market", "enterprise"]
OrganizationStatus = Literal["onboarding", "active", "suspended", "closed"]
OrganizationPlan = Literal["pilot", "starter", "growth", "agency", "enterprise"]


def _normalized_name(value: str) -> str:
    return " ".join(value.split())


def _normalized_slug(value: str) -> str:
    normalized = value.strip().lower()
    if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", normalized):
        raise ValueError("Slug must contain lowercase ASCII letters, numbers, and hyphens only")
    return normalized


def _blank_to_none(value):
    return None if isinstance(value, str) and not value.strip() else value


class AdminMetric(BaseModel):
    label: str
    value: int = Field(ge=0)
    detail: str


class AdminRecentStudent(BaseModel):
    name: str
    email: str
    registered_at: str | None


class AdminDashboardResponse(BaseModel):
    stats: list[AdminMetric]
    module_counts: dict[str, int]
    recent_students: list[AdminRecentStudent]


class AdminTableRow(BaseModel):
    name: str
    meta: str
    score: str
    status: str
    next: str


class AdminModuleResponse(BaseModel):
    title: str
    subtitle: str
    total: int = Field(ge=0)
    rows: list[AdminTableRow]


class AdminProfileResponse(BaseModel):
    id: int
    full_name: str
    email: EmailStr
    role: str
    is_active: bool
    admin_permissions: list[str]
    must_change_password: bool


class AdminProfileUpdate(BaseModel):
    full_name: str = Field(min_length=2, max_length=100)
    email: EmailStr
    current_password: str = Field(min_length=8, max_length=128)
    new_password: str | None = Field(default=None, min_length=8, max_length=128)

    @field_validator("full_name")
    @classmethod
    def normalize_name(cls, value: str) -> str:
        return " ".join(value.split())


class AdminAccountCreate(BaseModel):
    full_name: str = Field(min_length=2, max_length=100)
    email: EmailStr
    temporary_password: str = Field(min_length=8, max_length=128)
    permissions: list[str] = Field(default_factory=list)

    @field_validator("full_name")
    @classmethod
    def normalize_create_name(cls, value: str) -> str:
        return " ".join(value.split())


class AdminAccountUpdate(BaseModel):
    full_name: str = Field(min_length=2, max_length=100)
    email: EmailStr
    is_active: bool
    permissions: list[str] = Field(default_factory=list)
    temporary_password: str | None = Field(default=None, min_length=8, max_length=128)


class AdminAccountResponse(AdminProfileResponse):
    created_at: str | None = None


class AdminAccountsResponse(BaseModel):
    permission_keys: list[str]
    accounts: list[AdminAccountResponse]


class AdminOrganizationCreate(BaseModel):
    name: str = Field(min_length=2, max_length=160)
    slug: str | None = Field(default=None, min_length=2, max_length=100)
    organization_type: OrganizationType
    size_band: OrganizationSizeBand
    status: OrganizationStatus = "onboarding"
    plan_code: OrganizationPlan = "pilot"
    billing_email: EmailStr
    website: AnyHttpUrl | None = None
    description: str | None = Field(default=None, max_length=1000)
    logo_url: AnyHttpUrl | None = None

    @field_validator("name")
    @classmethod
    def normalize_name(cls, value: str) -> str:
        return _normalized_name(value)

    @field_validator("slug")
    @classmethod
    def normalize_slug(cls, value: str | None) -> str | None:
        return _normalized_slug(value) if value is not None else None

    @field_validator("website", "description", "logo_url", mode="before")
    @classmethod
    def normalize_optional_website(cls, value):
        return _blank_to_none(value)

    @field_validator("logo_url")
    @classmethod
    def require_https_logo(cls, value: AnyHttpUrl | None) -> AnyHttpUrl | None:
        if value is not None and value.scheme != "https":
            raise ValueError("Logo URL must use HTTPS")
        return value


class AdminOrganizationUpdate(BaseModel):
    name: str | None = Field(default=None, min_length=2, max_length=160)
    slug: str | None = Field(default=None, min_length=2, max_length=100)
    organization_type: OrganizationType | None = None
    size_band: OrganizationSizeBand | None = None
    status: OrganizationStatus | None = None
    plan_code: OrganizationPlan | None = None
    billing_email: EmailStr | None = None
    website: AnyHttpUrl | None = None
    description: str | None = Field(default=None, max_length=1000)
    logo_url: AnyHttpUrl | None = None

    @field_validator("name")
    @classmethod
    def normalize_name(cls, value: str | None) -> str | None:
        return _normalized_name(value) if value is not None else None

    @field_validator("slug")
    @classmethod
    def normalize_slug(cls, value: str | None) -> str | None:
        return _normalized_slug(value) if value is not None else None

    @field_validator("website", "description", "logo_url", mode="before")
    @classmethod
    def normalize_optional_website(cls, value):
        return _blank_to_none(value)

    @field_validator("logo_url")
    @classmethod
    def require_https_logo(cls, value: AnyHttpUrl | None) -> AnyHttpUrl | None:
        if value is not None and value.scheme != "https":
            raise ValueError("Logo URL must use HTTPS")
        return value

    @model_validator(mode="after")
    def reject_null_for_required_fields(self):
        nullable = {"website", "description", "logo_url"}
        for field in self.model_fields_set - nullable:
            if getattr(self, field) is None:
                raise ValueError(f"{field} cannot be null")
        return self


class AdminOrganizationResponse(BaseModel):
    id: str
    name: str
    slug: str
    organization_type: OrganizationType
    size_band: OrganizationSizeBand
    status: OrganizationStatus
    plan_code: OrganizationPlan
    billing_email: EmailStr
    website: str | None
    description: str | None
    logo_url: str | None
    members_count: int = Field(ge=0)
    created_at: str
    updated_at: str


class AdminOrganizationsResponse(BaseModel):
    total: int = Field(ge=0)
    organizations: list[AdminOrganizationResponse]
