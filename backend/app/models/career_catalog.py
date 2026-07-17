from datetime import datetime

from sqlalchemy import Boolean, CheckConstraint, DateTime, ForeignKey, Integer, String, Text, UniqueConstraint, func
from sqlalchemy.orm import Mapped, mapped_column

from app.core.database import Base


class CareerSkill(Base):
    __tablename__ = "career_skills"
    __table_args__ = (
        CheckConstraint("skill_type IN ('technical', 'domain', 'soft', 'tool')", name="ck_career_skills_type"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    slug: Mapped[str] = mapped_column(String(120), unique=True, index=True)
    name: Mapped[str] = mapped_column(String(160), unique=True, index=True)
    skill_type: Mapped[str] = mapped_column(String(20), nullable=False, default="domain")
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class CareerDataSource(Base):
    __tablename__ = "career_data_sources"
    __table_args__ = (
        CheckConstraint("source_type IN ('official', 'report', 'market', 'manual')", name="ck_career_data_sources_type"),
        CheckConstraint("status IN ('active', 'archived')", name="ck_career_data_sources_status"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    slug: Mapped[str] = mapped_column(String(120), unique=True, index=True)
    name: Mapped[str] = mapped_column(String(180), unique=True, index=True)
    source_type: Mapped[str] = mapped_column(String(20), nullable=False, default="manual")
    url: Mapped[str | None] = mapped_column(String(2048), nullable=True)
    reference_uri: Mapped[str | None] = mapped_column(String(1024), nullable=True)
    version: Mapped[str | None] = mapped_column(String(80), nullable=True)
    checksum_sha256: Mapped[str | None] = mapped_column(String(64), nullable=True)
    license: Mapped[str | None] = mapped_column(String(255), nullable=True)
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    status: Mapped[str] = mapped_column(String(20), nullable=False, default="active")
    last_verified_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)


class CareerRoleSkillRequirement(Base):
    __tablename__ = "career_role_skill_requirements"
    __table_args__ = (
        UniqueConstraint("career_role_id", "career_skill_id", name="uq_career_role_skill_requirement"),
        CheckConstraint("requirement_type IN ('required', 'preferred')", name="ck_career_role_skill_requirement_type"),
        CheckConstraint("expected_level IN ('basic', 'intermediate', 'advanced', 'expert')", name="ck_career_role_skill_requirement_level"),
        CheckConstraint("weight >= 1 AND weight <= 100", name="ck_career_role_skill_requirement_weight"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    career_role_id: Mapped[int] = mapped_column(ForeignKey("career_roles.id", ondelete="CASCADE"), index=True, nullable=False)
    career_skill_id: Mapped[int] = mapped_column(ForeignKey("career_skills.id", ondelete="RESTRICT"), index=True, nullable=False)
    data_source_id: Mapped[int | None] = mapped_column(ForeignKey("career_data_sources.id", ondelete="SET NULL"), index=True, nullable=True)
    requirement_type: Mapped[str] = mapped_column(String(20), nullable=False, default="required")
    expected_level: Mapped[str] = mapped_column(String(20), nullable=False, default="intermediate")
    weight: Mapped[int] = mapped_column(Integer, nullable=False, default=100)
    notes: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)
