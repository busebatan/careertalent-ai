"""Persistent permission contract for organization memberships."""

from collections.abc import Iterable


COMPANY_PERMISSION_KEYS = (
    "dashboard.view",
    "positions.view",
    "positions.write",
    "positions.delete",
    "applications.view",
    "applications.write",
    "assessments.view",
    "assessments.write",
    "scorecards.view",
    "scorecards.submit",
    "organization.update",
    "members.view",
    "members.invite",
    "members.manage",
)

LEGACY_COMPANY_ROLE_PERMISSIONS = {
    "owner": COMPANY_PERMISSION_KEYS,
    "admin": COMPANY_PERMISSION_KEYS,
    "recruiter": (
        "dashboard.view", "positions.view", "positions.write", "positions.delete",
        "applications.view", "applications.write", "assessments.view", "assessments.write",
        "scorecards.view", "members.view",
    ),
    "hiring_manager": (
        "dashboard.view", "positions.view", "applications.view", "applications.write",
        "assessments.view", "scorecards.view", "scorecards.submit", "members.view",
    ),
    "viewer": (
        "dashboard.view", "positions.view", "applications.view", "assessments.view",
        "scorecards.view", "members.view",
    ),
}


def normalize_explicit_company_permissions(values: Iterable[str]) -> list[str]:
    requested = set(values)
    unknown = sorted(requested - set(COMPANY_PERMISSION_KEYS))
    if unknown:
        raise ValueError(f"Unknown company permissions: {', '.join(unknown)}")
    requested.add("dashboard.view")
    return [key for key in COMPANY_PERMISSION_KEYS if key in requested]


def normalize_company_permissions(role: str, values: Iterable[str] | None) -> list[str]:
    requested = LEGACY_COMPANY_ROLE_PERMISSIONS.get(role, ("dashboard.view",)) if values is None else values
    normalized = normalize_explicit_company_permissions(requested)
    return list(COMPANY_PERMISSION_KEYS) if role == "owner" else normalized


def effective_company_permissions(membership) -> list[str]:
    return normalize_company_permissions(membership.role, membership.permissions)
