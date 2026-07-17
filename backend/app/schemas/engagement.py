from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator


class SocialLink(BaseModel):
    platform: str = Field(min_length=1, max_length=80)
    url: str = Field(min_length=4, max_length=2048)


class ProfileUpdate(BaseModel):
    full_name: str = Field(min_length=2, max_length=100)
    phone: str | None = Field(default=None, max_length=40)
    location: str | None = Field(default=None, max_length=160)
    headline: str | None = Field(default=None, max_length=240)
    linkedin: str | None = Field(default=None, max_length=2048)
    social_links: list[SocialLink] = Field(default_factory=list, max_length=12)

    @field_validator("full_name")
    @classmethod
    def normalize_name(cls, value: str) -> str:
        return " ".join(value.split())


class ChatRequest(BaseModel):
    message: str = Field(min_length=2, max_length=4000)


class ChatReplyAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    reply: str = Field(min_length=2, max_length=5000)
    suggested_actions: list[str] = Field(default_factory=list, max_length=6)


class InterviewQuestionAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    id: str = Field(min_length=1, max_length=80)
    question: str = Field(min_length=5, max_length=1000)
    competency: str = Field(min_length=2, max_length=160)
    guidance: str = Field(default="", max_length=800)


class InterviewQuestionsAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    questions: list[InterviewQuestionAI] = Field(min_length=3, max_length=8)


class InterviewAnswerRequest(BaseModel):
    question_id: str = Field(min_length=1, max_length=80)
    answer: str = Field(min_length=20, max_length=8000)


class InterviewEvaluationAI(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    score: int = Field(ge=0, le=100)
    feedback: str = Field(min_length=2, max_length=3000)
    strengths: list[str] = Field(default_factory=list, max_length=8)
    improvements: list[str] = Field(default_factory=list, max_length=8)


class PersonalTaskCreate(BaseModel):
    title: str = Field(min_length=2, max_length=240)
    target_id: str | None = Field(default=None, max_length=36)


class PersonalTaskUpdate(BaseModel):
    title: str | None = Field(default=None, min_length=2, max_length=240)
    note: str | None = Field(default=None, max_length=4000)
    completed: bool | None = None


class TaskNoteUpdate(BaseModel):
    note: str | None = Field(default=None, max_length=4000)


class CareerTaskStatusUpdate(BaseModel):
    status: Literal["pending", "completed"]


class SkillEvidenceLinkRequest(BaseModel):
    skill: str = Field(min_length=1, max_length=120)
    target_id: str = Field(min_length=1, max_length=36)
    url: str = Field(min_length=4, max_length=2048)


class ApplicationCreate(BaseModel):
    company: str = Field(min_length=2, max_length=160)
    role: str = Field(min_length=2, max_length=200)
    next_action: str | None = Field(default=None, max_length=300)


class ApplicationUpdate(BaseModel):
    stage: Literal["applied", "interview", "offer", "rejected"] | None = None
    next_action: str | None = Field(default=None, max_length=300)
    note: str | None = Field(default=None, max_length=4000)

