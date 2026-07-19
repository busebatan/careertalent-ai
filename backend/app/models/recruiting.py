"""İşveren/ajans tenant çekirdeği ve çoklu kurum üyelikleri."""

from datetime import datetime

from sqlalchemy import JSON, CheckConstraint, DateTime, ForeignKey, Integer, String, UniqueConstraint, func
from sqlalchemy.orm import Mapped, mapped_column

from app.core.company_permissions import normalize_company_permissions
from app.core.database import Base


def _default_company_permissions(context) -> list[str]:
    return normalize_company_permissions(context.get_current_parameters().get("role", "viewer"), None)


class Organization(Base):
    __tablename__ = "organizations"
    __table_args__ = (
        CheckConstraint(
            "organization_type IN ('employer', 'agency')",
            name="ck_organizations_type",
        ),
        CheckConstraint(
            "size_band IN ('smb', 'mid_market', 'enterprise')",
            name="ck_organizations_size_band",
        ),
        CheckConstraint(
            "status IN ('onboarding', 'active', 'suspended', 'closed')",
            name="ck_organizations_status",
        ),
        CheckConstraint(
            "plan_code IN ('pilot', 'starter', 'growth', 'agency', 'enterprise')",
            name="ck_organizations_plan_code",
        ),
        UniqueConstraint("slug", name="uq_organizations_slug"),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    name: Mapped[str] = mapped_column(String(160), nullable=False)
    slug: Mapped[str] = mapped_column(String(100), index=True, nullable=False)
    organization_type: Mapped[str] = mapped_column(String(20), index=True, nullable=False)
    size_band: Mapped[str] = mapped_column(String(20), index=True, nullable=False)
    status: Mapped[str] = mapped_column(String(20), index=True, nullable=False, default="onboarding")
    plan_code: Mapped[str] = mapped_column(String(20), index=True, nullable=False, default="pilot")
    billing_email: Mapped[str] = mapped_column(String(255), nullable=False)
    website: Mapped[str | None] = mapped_column(String(2048), nullable=True)
    settings: Mapped[dict] = mapped_column(JSON, nullable=False, default=dict)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )


class OrganizationMembership(Base):
    __tablename__ = "organization_memberships"
    __table_args__ = (
        CheckConstraint(
            "role IN ('owner', 'admin', 'recruiter', 'hiring_manager', 'viewer')",
            name="ck_organization_memberships_role",
        ),
        CheckConstraint(
            "status IN ('invited', 'active', 'suspended')",
            name="ck_organization_memberships_status",
        ),
        UniqueConstraint(
            "organization_id", "user_id", name="uq_organization_memberships_organization_user"
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    user_id: Mapped[int] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), index=True, nullable=False
    )
    role: Mapped[str] = mapped_column(String(24), index=True, nullable=False)
    permissions: Mapped[list[str]] = mapped_column(JSON, nullable=False, default=_default_company_permissions)
    status: Mapped[str] = mapped_column(String(20), index=True, nullable=False, default="invited")
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )


class OrganizationInvitation(Base):
    __tablename__ = "organization_invitations"
    __table_args__ = (
        CheckConstraint(
            "role IN ('owner', 'admin', 'recruiter', 'hiring_manager', 'viewer')",
            name="ck_organization_invitations_role",
        ),
    )

    id: Mapped[str] = mapped_column(String(36), primary_key=True)
    organization_id: Mapped[str] = mapped_column(
        ForeignKey("organizations.id", ondelete="CASCADE"), index=True, nullable=False
    )
    email: Mapped[str] = mapped_column(String(255), index=True, nullable=False)
    role: Mapped[str] = mapped_column(String(24), nullable=False)
    permissions: Mapped[list[str]] = mapped_column(JSON, nullable=False, default=_default_company_permissions)
    token_hash: Mapped[str] = mapped_column(String(64), unique=True, index=True, nullable=False)
    invited_by_user_id: Mapped[int | None] = mapped_column(
        Integer, ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )
    expires_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    accepted_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
