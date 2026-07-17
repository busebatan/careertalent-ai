"""Panel API response kontrat şemaları."""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field


class PanelStats(BaseModel):
    readiness: int = Field(..., ge=0, le=100)
    career: str
    weekly_tasks_total: int = Field(..., ge=0)
    weekly_tasks_done: int = Field(..., ge=0)


class WeeklyTask(BaseModel):
    id: str
    title: str
    done: bool
    hint: str


class LearningResource(BaseModel):
    id: str
    title: str
    provider: str
    url: str
    price_type: Literal["free", "paid"]
    price_label: str
    price_range: str
    has_certificate: bool
    skills: list[str]


class DashboardResponse(BaseModel):
    stats: PanelStats
    weekly_tasks: list[WeeklyTask]
    learning_resources: list[LearningResource]


class RoadmapResponse(BaseModel):
    stats: PanelStats
    weekly_tasks: list[WeeklyTask]


class TasksResponse(RoadmapResponse):
    pass


class LearningResponse(BaseModel):
    learning_resources: list[LearningResource]


class CareerSwot(BaseModel):
    strengths: list[str]
    weaknesses: list[str]
    opportunities: list[str]
    threats: list[str]


class CareerLadderItem(BaseModel):
    id: str
    tier: Literal["ready", "near", "reachable"]
    tier_label: str
    title: str
    readiness: int = Field(..., ge=0, le=100)
    gap_count: int = Field(..., ge=0)
    gaps_summary: str
    weeks_estimate: str | None = None
    swot: CareerSwot


class CareerTierMetaItem(BaseModel):
    heading: str
    hint: str


class CareerLadderResponse(BaseModel):
    career_ladder: list[CareerLadderItem]
    career_tier_meta: dict[str, CareerTierMetaItem]


class SkillPassportItem(BaseModel):
    skill: str
    level: str
    evidence: str
    type: str
    status: Literal["verified", "review", "missing"]
    impact: str


class SkillPassport(BaseModel):
    score: int = Field(..., ge=0, le=100)
    verified: int = Field(..., ge=0)
    total: int = Field(..., ge=0)
    items: list[SkillPassportItem]
    gaps: list[str]


class SkillPassportResponse(BaseModel):
    passport: SkillPassport


class InterviewQuestion(BaseModel):
    role: str
    type: str
    question: str
    score: int = Field(..., ge=0, le=100)
    feedback: str


class InterviewSimulator(BaseModel):
    questions: list[InterviewQuestion]
    rubric: list[str]


class InterviewResponse(BaseModel):
    interview: InterviewSimulator


class ApplicationItem(BaseModel):
    company: str
    role: str
    date: str
    next: str


class ApplicationColumn(BaseModel):
    id: str
    label: str
    items: list[ApplicationItem]


class ApplicationMetrics(BaseModel):
    active: int = Field(..., ge=0)
    interviews: int = Field(..., ge=0)
    offers: int = Field(..., ge=0)


class ApplicationTracker(BaseModel):
    metrics: ApplicationMetrics
    columns: list[ApplicationColumn]


class ApplicationsResponse(BaseModel):
    applications: ApplicationTracker


class JobRadarAlert(BaseModel):
    role: str
    company: str
    source: str
    match: int = Field(..., ge=0, le=100)
    salary: str
    gaps: list[str]
    action: str


class JobRadar(BaseModel):
    roles: list[str]
    sources: list[str]
    alerts: list[JobRadarAlert]


class JobRadarResponse(BaseModel):
    radar: JobRadar


class MentorPackage(BaseModel):
    name: str
    price: str
    delivery: str


class MentorExpert(BaseModel):
    name: str
    title: str
    company: str
    rating: float = Field(..., ge=0, le=5)
    focus: str
    slots: str


class MentorMarketplace(BaseModel):
    packages: list[MentorPackage]
    experts: list[MentorExpert]


class MentorsResponse(BaseModel):
    mentors: MentorMarketplace


class ChatPrompt(BaseModel):
    q: str
    a: str


class ChatAssistant(BaseModel):
    prompts: list[ChatPrompt]


class ChatResponse(BaseModel):
    assistant: ChatAssistant


class JobMatch(BaseModel):
    id: str
    url: str
    title: str
    company: str
    source: str
    match_score: int = Field(..., ge=0, le=100)
    matched_skills: list[str]
    missing_skills: list[str]
    recommendation: Literal["apply", "prepare", "wait"]
    analyzed_at: str


class JobMatchesResponse(BaseModel):
    seed_jobs: list[JobMatch]
    user_skills: list[str]
    readiness: int = Field(..., ge=0, le=100)


class JobMatchAnalyzeResponse(BaseModel):
    job: JobMatch


class TargetRole(BaseModel):
    source: Literal["ladder", "custom", "job_url"]
    role_id: str
    title: str
    readiness: int = Field(..., ge=0, le=100)
    gap_count: int = Field(..., ge=0)
    gaps_summary: str
    weeks_estimate: str | None = None
    selected_at: str
    job_url: str | None = None
    swot: CareerSwot | None = None
    parsed_from: str | None = None
    required_skills: list[str] = Field(default_factory=list)


class TargetRoleResponse(BaseModel):
    target: TargetRole | None = None


class TargetRoleRequest(BaseModel):
    source: Literal["ladder", "custom", "job_url"]
    role_id: str | None = None
    title: str | None = None
    readiness: int | None = Field(default=None, ge=0, le=100)
    gap_count: int | None = Field(default=None, ge=0)
    gaps_summary: str | None = None
    weeks_estimate: str | None = None
    job_url: str | None = None
    swot: CareerSwot | None = None
    parsed_from: str | None = None
    required_skills: list[str] = Field(default_factory=list)


class JobListingParseRequest(BaseModel):
    url: str


class JobListingParseResponse(BaseModel):
    url: str
    title: str
    company: str
    source: str
    role_id: str
    required_skills: list[str]
    parsed_from: Literal["html", "url"]
