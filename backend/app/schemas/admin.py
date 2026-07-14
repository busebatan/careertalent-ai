"""Yalnız yönetici yüzeyinin ihtiyaç duyduğu, sunumdan bağımsız gerçek veri sözleşmeleri."""

from pydantic import BaseModel, Field


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
