"""Kariyer motoru istek/yanıt ve model JSON sözleşmeleri."""

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator, model_validator


class CVQueueResponse(BaseModel):
    analysis_id: str
    status: Literal["queued", "running", "ready", "failed"]


class CareerSkill(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    name: str = Field(min_length=1, max_length=120)
    score: int = Field(ge=0, le=100)


class SkillRadarItem(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    label: str = Field(min_length=1, max_length=120)
    score: int = Field(ge=0, le=100)
    target: int = Field(ge=0, le=100)


class CareerSwot(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    strengths: list[str] = Field(max_length=12)
    weaknesses: list[str] = Field(max_length=12)
    opportunities: list[str] = Field(max_length=12)
    threats: list[str] = Field(max_length=12)


class CareerRoleAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    tier: Literal["A", "B", "C"]
    title: str = Field(min_length=2, max_length=160)
    readiness: int = Field(ge=0, le=100)
    swot: CareerSwot


class CareerAnalysisAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    current_role: str | None = Field(default=None, max_length=160)
    profile: dict[str, str] = Field(default_factory=dict, max_length=20)
    skills: list[CareerSkill] = Field(max_length=30)
    radar: list[SkillRadarItem] = Field(max_length=30)
    roles: list[CareerRoleAI] = Field(min_length=3, max_length=15)

    @model_validator(mode="after")
    def require_tiers(self) -> "CareerAnalysisAI":
        tiers = [role.tier for role in self.roles]
        if not {"A", "B", "C"}.issubset(set(tiers)):
            raise ValueError("A/B/C kariyer katmanları zorunlu")
        return self


class CareerRoleLocalizationAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    title: str = Field(min_length=2, max_length=160)
    strengths: list[str] = Field(max_length=12)
    weaknesses: list[str] = Field(max_length=12)
    opportunities: list[str] = Field(max_length=12)
    threats: list[str] = Field(max_length=12)


class CareerAnalysisLocalizationAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    current_role: str | None = Field(default=None, max_length=160)
    profile: dict[str, str] = Field(default_factory=dict, max_length=20)
    skill_names: list[str] = Field(max_length=30)
    radar_labels: list[str] = Field(max_length=30)
    roles: list[CareerRoleLocalizationAI] = Field(max_length=15)


class CareerAnalysisLocalizationsAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    tr: CareerAnalysisLocalizationAI
    en: CareerAnalysisLocalizationAI


class TrainingSearchQuery(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    query: str = Field(min_length=4, max_length=240)
    skill: str = Field(min_length=1, max_length=120)
    reason: str = Field(min_length=2, max_length=500)


class CareerPlanTaskAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    title: str = Field(min_length=2, max_length=240)
    hint: str = Field(default="", max_length=1200)
    evidence_required: bool = True
    evidence_types: list[Literal["link", "file"]] = Field(min_length=1, max_length=4)
    skill_impacts: list[str] = Field(min_length=1, max_length=12)
    training_queries: list[TrainingSearchQuery] = Field(max_length=4)


class CareerPlanAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    target_title: str = Field(min_length=2, max_length=160)
    tasks: list[CareerPlanTaskAI] = Field(min_length=1, max_length=12)


class CareerTaskLocalizationAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    id: str = Field(min_length=1, max_length=80)
    title: str = Field(min_length=2, max_length=240)
    hint: str = Field(default="", max_length=1200)
    skill_impacts: list[str] = Field(max_length=12)
    feedback: str | None = Field(default=None, max_length=1200)


class CareerPlanLocalizationAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    target_title: str = Field(min_length=2, max_length=160)
    tasks: list[CareerTaskLocalizationAI] = Field(max_length=12)


class CareerPlanLocalizationsAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    tr: CareerPlanLocalizationAI
    en: CareerPlanLocalizationAI


class CareerAnalysisResponse(BaseModel):
    id: str
    status: Literal["queued", "running", "ready", "failed"]
    source: str
    file_name: str | None = None
    cv_document_id: str | None = None
    current_role: str | None = None
    profile: dict
    skills: list
    radar: list
    career_ladder: list
    error_code: str | None = None
    error_message: str | None = None
    created_at: str
    locale: Literal["tr", "en"] = "tr"


class CareerTargetRequest(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    title: str = Field(min_length=2, max_length=160)
    source: Literal["custom", "ladder", "job_url"] = "custom"
    job_url: str | None = Field(default=None, max_length=2048)


class CareerTargetResponse(BaseModel):
    id: str
    title: str
    source: str
    status: str
    plan: dict
    created_at: str
    locale: Literal["tr", "en"] = "tr"


class CareerTaskResponse(BaseModel):
    id: str
    target_id: str
    title: str
    hint: str
    note: str = ""
    status: str
    evidence_required: bool
    evidence_types: list
    skill_impacts: list
    training_suggestions: list
    feedback: str | None = None
    has_evidence: bool = False
    evidence_verified: bool = False
    evidence_pending: bool = False
    locale: Literal["tr", "en"] = "tr"


class EvidenceCreateRequest(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    kind: Literal["link", "file"]
    url: str | None = Field(default=None, max_length=2048)

    @model_validator(mode="after")
    def require_reference(self) -> "EvidenceCreateRequest":
        if self.kind == "link" and not self.url:
            raise ValueError("Link kanıtı için URL gerekli")
        if self.kind == "file":
            raise ValueError("Dosya kanıtı multipart upload ile gönderilmeli")
        return self


class EvidenceReviewAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    decision: Literal["accept", "revise"]
    confidence: float = Field(ge=0, le=1)
    feedback: str = Field(default="", max_length=1200)


class EvidenceResponse(BaseModel):
    id: str
    task_id: str
    status: str
    confidence: float | None = None
    feedback: str | None = None


class CareerResetRequest(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    scope: Literal["analysis", "plan", "all"]


class JobCvSuggestionAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    action: Literal["rewrite", "add", "develop"]
    section: Literal["summary", "skills", "experience", "projects", "education"]
    title: str = Field(min_length=2, max_length=200)
    reason: str = Field(min_length=2, max_length=1000)
    suggested_text: str = Field(default="", max_length=2000)
    safe_to_apply: bool
    related_skills: list[str] = Field(default_factory=list, max_length=12)


class JobOpportunityAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    title: str = Field(min_length=2, max_length=200)
    company: str = Field(default="", max_length=160)
    source: str = Field(default="", max_length=120)
    required_skills: list[str] = Field(min_length=1, max_length=40)
    matched_skills: list[str] = Field(max_length=40)
    missing_skills: list[str] = Field(max_length=40)
    match_score: int = Field(ge=0, le=100)
    cv_suggestions: list[JobCvSuggestionAI] = Field(min_length=1, max_length=20)


class CvRewriteAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    revised_cv_text: str = Field(min_length=40, max_length=30000)


class JobAnalyzeRequest(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    source_url: str | None = Field(default=None, max_length=2048)
    job_text: str | None = Field(default=None, max_length=30000)

    @model_validator(mode="after")
    def require_source(self) -> "JobAnalyzeRequest":
        if not (self.source_url and self.source_url.strip()) and not (self.job_text and len(self.job_text.strip()) >= 40):
            raise ValueError("İlan URL'si veya en az 40 karakter ilan metni gerekli")
        return self


class JobApplyRequest(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    suggestion_ids: list[str] = Field(min_length=1, max_length=20)
