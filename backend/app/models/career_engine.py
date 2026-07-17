"""Kullanıcı kariyer analizi, hedef, görev ve kanıt kayıtları."""

from datetime import datetime

from sqlalchemy import Boolean, DateTime, ForeignKey, Integer, JSON, String, Text, func
from sqlalchemy.orm import Mapped, mapped_column

from app.core.database import Base


class CareerAnalysis(Base):
    __tablename__ = "career_analyses"

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True, nullable=False)
    cv_document_id: Mapped[str | None] = mapped_column(
        ForeignKey("cv_documents.id", ondelete="SET NULL"), index=True, nullable=True
    )
    status: Mapped[str] = mapped_column(String(20), index=True, nullable=False, default="queued")
    source: Mapped[str] = mapped_column(String(20), nullable=False)
    file_name: Mapped[str | None] = mapped_column(String(255), nullable=True)
    cv_text: Mapped[str | None] = mapped_column(Text, nullable=True)
    current_role: Mapped[str | None] = mapped_column(String(160), nullable=True)
    profile: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    skills: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    radar: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    career_ladder: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    localizations: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    error_code: Mapped[str | None] = mapped_column(String(40), nullable=True)
    error_message: Mapped[str | None] = mapped_column(String(500), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class CareerTarget(Base):
    __tablename__ = "career_targets"

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True, nullable=False)
    title: Mapped[str] = mapped_column(String(160), nullable=False)
    source: Mapped[str] = mapped_column(String(30), nullable=False, default="custom")
    job_url: Mapped[str | None] = mapped_column(String(2048), nullable=True)
    status: Mapped[str] = mapped_column(String(20), index=True, nullable=False, default="queued")
    plan: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    localizations: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    closed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class CareerTask(Base):
    __tablename__ = "career_tasks"

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True, nullable=False)
    target_id: Mapped[str] = mapped_column(ForeignKey("career_targets.id"), index=True, nullable=False)
    title: Mapped[str] = mapped_column(String(240), nullable=False)
    hint: Mapped[str] = mapped_column(Text, nullable=False, default="")
    note: Mapped[str | None] = mapped_column(Text, nullable=True)
    status: Mapped[str] = mapped_column(String(30), index=True, nullable=False, default="pending")
    evidence_required: Mapped[bool] = mapped_column(nullable=False, default=True)
    evidence_types: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    skill_impacts: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    training_suggestions: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    localizations: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    feedback: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class Evidence(Base):
    __tablename__ = "career_evidence"

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True, nullable=False)
    task_id: Mapped[str] = mapped_column(ForeignKey("career_tasks.id"), index=True, nullable=False)
    kind: Mapped[str] = mapped_column(String(20), nullable=False)
    url: Mapped[str | None] = mapped_column(String(2048), nullable=True)
    file_path: Mapped[str | None] = mapped_column(String(1024), nullable=True)
    status: Mapped[str] = mapped_column(String(30), index=True, nullable=False, default="pending")
    confidence: Mapped[float | None] = mapped_column(nullable=True)
    feedback: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    reviewed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)


class JobOpportunity(Base):
    __tablename__ = "job_opportunities"

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True, nullable=False)
    status: Mapped[str] = mapped_column(String(24), index=True, nullable=False, default="queued")
    source_url: Mapped[str | None] = mapped_column(String(2048), nullable=True)
    job_text: Mapped[str | None] = mapped_column(Text, nullable=True)
    title: Mapped[str | None] = mapped_column(String(200), nullable=True)
    company: Mapped[str | None] = mapped_column(String(160), nullable=True)
    source: Mapped[str | None] = mapped_column(String(120), nullable=True)
    required_skills: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    matched_skills: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    missing_skills: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    match_score: Mapped[int | None] = mapped_column(Integer, nullable=True)
    cv_suggestions: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    saved: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)
    apply_status: Mapped[str | None] = mapped_column(String(24), nullable=True)
    applied_suggestion_ids: Mapped[list] = mapped_column(JSON, nullable=False, default=list)
    result_analysis_id: Mapped[str | None] = mapped_column(String(36), nullable=True)
    error_code: Mapped[str | None] = mapped_column(String(40), nullable=True)
    error_message: Mapped[str | None] = mapped_column(String(500), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
