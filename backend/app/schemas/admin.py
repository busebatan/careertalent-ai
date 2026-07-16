"""Yalnız yönetici yüzeyinin ihtiyaç duyduğu, sunumdan bağımsız gerçek veri sözleşmeleri."""

from pydantic import BaseModel, EmailStr, Field, field_validator


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
