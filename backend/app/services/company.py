import hashlib
import secrets
from datetime import UTC, datetime, timedelta
from uuid import uuid4

from sqlalchemy import func, select
from sqlalchemy.orm import Session

from app.models.recruiting import Organization, OrganizationInvitation, OrganizationMembership
from app.models.user import User


class CompanyInvitationConflict(ValueError):
    pass


def invitation_hash(token: str) -> str:
    return hashlib.sha256(token.encode("utf-8")).hexdigest()


def create_company_invitation(
    db: Session,
    organization: Organization,
    email: str,
    role: str,
    invited_by: User,
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
        token_hash=invitation_hash(token),
        invited_by_user_id=invited_by.id,
        expires_at=now + timedelta(days=7),
    )
    db.add(invitation)
    db.commit()
    db.refresh(invitation)
    return invitation, token
