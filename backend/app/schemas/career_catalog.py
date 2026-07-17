from datetime import datetime
from typing import Literal

from pydantic import BaseModel, Field, field_validator


SkillType = Literal["technical", "domain", "soft", "tool"]
RequirementType = Literal["required", "preferred"]
ExpectedLevel = Literal["basic", "intermediate", "advanced", "expert"]
SourceType = Literal["official", "report", "market", "manual"]
SourceStatus = Literal["active", "archived"]


class CareerDataRoleWrite(BaseModel):
    slug: str = Field(min_length=2, max_length=100)
    title: str = Field(min_length=2, max_length=255)
    description: str | None = Field(default=None, max_length=10000)
    weeks_template: int = Field(default=12, ge=1, le=104)

    @field_validator("slug", "title", "description", mode="before")
    @classmethod
    def strip_text(cls, value: str | None) -> str | None:
        return value.strip() if isinstance(value, str) else value


class CareerSkillWrite(BaseModel):
    slug: str = Field(min_length=2, max_length=120)
    name: str = Field(min_length=2, max_length=160)
    skill_type: SkillType = "domain"
    description: str | None = Field(default=None, max_length=10000)
    is_active: bool = True

    @field_validator("slug", "name", "description", mode="before")
    @classmethod
    def strip_text(cls, value: str | None) -> str | None:
        return value.strip() if isinstance(value, str) else value


class CareerDataSourceWrite(BaseModel):
    slug: str = Field(min_length=2, max_length=120)
    name: str = Field(min_length=2, max_length=180)
    source_type: SourceType = "manual"
    url: str | None = Field(default=None, max_length=2048)
    reference_uri: str | None = Field(default=None, max_length=1024)
    version: str | None = Field(default=None, max_length=80)
    checksum_sha256: str | None = Field(default=None, min_length=64, max_length=64)
    license: str | None = Field(default=None, max_length=255)
    description: str | None = Field(default=None, max_length=10000)
    status: SourceStatus = "active"
    last_verified_at: datetime | None = None

    @field_validator("slug", "name", "url", "reference_uri", "version", "checksum_sha256", "license", "description", mode="before")
    @classmethod
    def strip_text(cls, value: str | None) -> str | None:
        return value.strip() if isinstance(value, str) else value


class CareerRoleSkillRequirementWrite(BaseModel):
    career_role_id: int = Field(gt=0)
    career_skill_id: int = Field(gt=0)
    data_source_id: int | None = Field(default=None, gt=0)
    requirement_type: RequirementType = "required"
    expected_level: ExpectedLevel = "intermediate"
    weight: int = Field(default=100, ge=1, le=100)
    notes: str | None = Field(default=None, max_length=10000)

    @field_validator("notes", mode="before")
    @classmethod
    def strip_notes(cls, value: str | None) -> str | None:
        return value.strip() if isinstance(value, str) else value


class CareerSkillResponse(BaseModel):
    id: int
    slug: str
    name: str
    skill_type: SkillType
    description: str | None
    is_active: bool
    requirement_count: int


class CareerDataSourceResponse(BaseModel):
    id: int
    slug: str
    name: str
    source_type: SourceType
    url: str | None
    reference_uri: str | None
    version: str | None
    checksum_sha256: str | None
    license: str | None
    description: str | None
    status: SourceStatus
    last_verified_at: datetime | None
    requirement_count: int


class CareerRoleSkillRequirementResponse(BaseModel):
    id: int
    career_role_id: int
    career_role_title: str
    career_skill_id: int
    career_skill_name: str
    data_source_id: int | None
    data_source_name: str | None
    requirement_type: RequirementType
    expected_level: ExpectedLevel
    weight: int
    notes: str | None


class CareerDataRoleResponse(BaseModel):
    id: int
    slug: str
    title: str
    description: str | None
    weeks_template: int
    required_skills: list[str]
    requirement_count: int
