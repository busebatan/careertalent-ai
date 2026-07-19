import hashlib
import re
import secrets
import unicodedata
from datetime import UTC, datetime, timedelta
from uuid import uuid4

from sqlalchemy import func, select
from sqlalchemy.orm import Session

from app.core.company_permissions import normalize_company_permissions
from app.models.recruiting import Organization, OrganizationInvitation, OrganizationMembership
from app.models.user import User


RESERVED_ORGANIZATION_SLUGS = frozenset(
    {
        "admin",
        "api",
        "blog",
        "bootcamp",
        "company",
        "cikis",
        "faq",
        "galeri",
        "giris",
        "hakkimizda",
        "health",
        "iletisim",
        "kayit",
        "locale",
        "meslekler",
        "nasil-calisir",
        "ozellikler",
        "panel",
        "fiyatlandirma",
        "storage",
        "up",
        "vendor",
    }
)
_TURKISH_ASCII = str.maketrans(
    {"ç": "c", "ğ": "g", "ı": "i", "İ": "I", "ö": "o", "ş": "s", "ü": "u"}
)


class CompanyInvitationConflict(ValueError):
    pass


def invitation_hash(token: str) -> str:
    return hashlib.sha256(token.encode("utf-8")).hexdigest()


def organization_slug(value: str) -> str:
    translated = value.translate(_TURKISH_ASCII)
    ascii_value = unicodedata.normalize("NFKD", translated).encode("ascii", "ignore").decode()
    slug = re.sub(r"[^a-z0-9]+", "-", ascii_value.lower()).strip("-")
    return (slug or "kurum")[:100].rstrip("-")


def is_reserved_organization_slug(slug: str) -> bool:
    return slug in RESERVED_ORGANIZATION_SLUGS or slug.startswith("livewire-")


def available_organization_slug(db: Session, name: str) -> str:
    base = organization_slug(name)
    if is_reserved_organization_slug(base):
        base = f"kurum-{base}"[:100].rstrip("-")
    candidate = base
    suffix = 2
    while is_reserved_organization_slug(candidate) or db.scalar(
        select(Organization.id).where(Organization.slug == candidate)
    ):
        ending = f"-{suffix}"
        candidate = f"{base[: 100 - len(ending)].rstrip('-')}{ending}"
        suffix += 1
    return candidate


def create_company_invitation(
    db: Session,
    organization: Organization,
    email: str,
    role: str,
    invited_by: User,
    permissions: list[str] | None = None,
) -> tuple[OrganizationInvitation, str]:
    normalized_email = email.strip().lower()
    now = datetime.now(UTC)
    # Serialize invitations per organization so only the latest link remains valid.
    db.execute(
        select(Organization.id)
        .where(Organization.id == organization.id)
        .with_for_update()
    ).scalar_one()

    existing_user = db.scalar(select(User).where(func.lower(User.email) == normalized_email))
    if existing_user is not None:
        if existing_user.role != "company" or existing_user.is_admin:
            raise CompanyInvitationConflict("Email belongs to a candidate or admin account")
        existing_membership = db.scalar(
            select(OrganizationMembership).where(
                OrganizationMembership.organization_id == organization.id,
                OrganizationMembership.user_id == existing_user.id,
            )
        )
        if existing_membership is not None:
            raise CompanyInvitationConflict("User is already a member of this organization")
    pending = db.scalars(
        select(OrganizationInvitation).where(
            OrganizationInvitation.organization_id == organization.id,
            OrganizationInvitation.email == normalized_email,
            OrganizationInvitation.accepted_at.is_(None),
        )
    ).all()
    for invitation in pending:
        invitation.accepted_at = now

    token = secrets.token_urlsafe(32)
    invitation = OrganizationInvitation(
        id=str(uuid4()),
        organization_id=organization.id,
        email=normalized_email,
        role=role,
        permissions=normalize_company_permissions(role, permissions),
        token_hash=invitation_hash(token),
        invited_by_user_id=invited_by.id,
        expires_at=now + timedelta(days=7),
    )
    db.add(invitation)
    db.commit()
    db.refresh(invitation)
    return invitation, token
