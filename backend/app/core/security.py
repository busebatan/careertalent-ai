from fastapi import Depends, HTTPException
from fastapi.security import OAuth2PasswordBearer
from datetime import datetime, timedelta, UTC

from jose import JWTError, jwt
from pwdlib import PasswordHash

from sqlalchemy.orm import Session

from app.core.database import get_db
from app.models.user import User

from app.core.config import settings

ADMIN_PERMISSION_KEYS = (
    "dashboard.view",
    "organizations.view",
    "organizations.write",
    "organizations.delete",
    "career_data.view",
    "career_data.write",
    "career_data.delete",
    "students.view",
    "students.write",
    "students.delete",
    "readiness.view",
    "skill_passport.view",
    "job_radar.view",
    "applications.view",
    "applications.write",
    "applications.delete",
    "interviews.view",
    "interviews.write",
    "interviews.delete",
)

LEGACY_ADMIN_PERMISSION_ALIASES = {
    "organizations.manage": (
        "organizations.view",
        "organizations.write",
        "organizations.delete",
    ),
    "career_data.manage": (
        "career_data.view",
        "career_data.write",
        "career_data.delete",
    ),
}

password_hash = PasswordHash.recommended()
DUMMY_PASSWORD_HASH = password_hash.hash("timing-attack-placeholder")


oauth2_scheme = OAuth2PasswordBearer(
    tokenUrl="/api/v1/auth/login"
)

def hash_password(password: str) -> str:
    return password_hash.hash(password)


def verify_password(
    plain_password: str,
    hashed_password: str,
) -> bool:
    return password_hash.verify(plain_password, hashed_password)

def create_access_token(
    data: dict,
    expires_delta: timedelta | None = None,
) -> str:
    to_encode = data.copy()

    if expires_delta:
        expire = datetime.now(UTC) + expires_delta
    else:
        expire = datetime.now(UTC) + timedelta(
            minutes=settings.ACCESS_TOKEN_EXPIRE_MINUTES
        )

    to_encode.update({"exp": expire})

    return jwt.encode(
        to_encode,
        settings.JWT_SECRET_KEY,
        algorithm=settings.JWT_ALGORITHM,
    )


def decode_access_token(
    token: str,
) -> dict:

    credentials_exception = HTTPException(
        status_code=401,
        detail="Could not validate credentials",
    )

    try:
        payload = jwt.decode(
            token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM],
        )

        if payload.get("sub") is None:
            raise credentials_exception

        return payload

    except JWTError:
        raise credentials_exception
    

def get_current_user(
    token: str = Depends(oauth2_scheme),
    db: Session = Depends(get_db),
) -> User:

    payload = decode_access_token(token)
    email = payload["sub"]

    user = (
        db.query(User)
        .filter(User.email == email)
        .first()
    )

    if user is None:
        raise HTTPException(
            status_code=401,
            detail="User not found",
        )

    if not user.is_active:
        raise HTTPException(
            status_code=403,
            detail="Account is inactive",
        )

    if int(payload.get("ver", 0)) != user.token_version:
        raise HTTPException(status_code=401, detail="Session is no longer valid")

    return user    


def require_admin(
    current_user: User = Depends(get_current_user),
) -> User:

    if not is_admin_user(current_user):
        raise HTTPException(
            status_code=403,
            detail="Admin privileges required",
        )

    return current_user


def require_candidate_portal_user(
    current_user: User = Depends(get_current_user),
) -> User:
    """Keep organization accounts outside candidate-owned API surfaces."""
    if current_user.role == "company":
        raise HTTPException(status_code=403, detail="Candidate account required")

    return current_user


def is_admin_user(user: User) -> bool:
    return user.role in {"admin", "super_admin"} or user.is_admin


def is_super_admin(user: User) -> bool:
    if user.role == "super_admin":
        return True

    # Eski is_admin kayıtları migration uygulanmadan da erişimi kaybetmez.
    return user.is_admin and user.role == "student" and not user.admin_permissions


def normalize_admin_permissions(values: list[str] | None) -> list[str]:
    expanded = set(values or [])
    for legacy, replacements in LEGACY_ADMIN_PERMISSION_ALIASES.items():
        if legacy in expanded:
            expanded.update(replacements)
    return [
        key
        for key in ADMIN_PERMISSION_KEYS
        if key == "dashboard.view" or key in expanded
    ]


def effective_admin_permissions(user: User) -> list[str]:
    return list(ADMIN_PERMISSION_KEYS) if is_super_admin(user) else normalize_admin_permissions(user.admin_permissions)


def ensure_admin_permission(user: User, permission: str) -> User:
    if user.must_change_password:
        raise HTTPException(status_code=403, detail="Password change required")
    if permission not in effective_admin_permissions(user):
        raise HTTPException(status_code=403, detail="Admin permission required")
    return user


def require_admin_permission(permission: str):
    def dependency(current_user: User = Depends(require_admin)) -> User:
        return ensure_admin_permission(current_user, permission)

    return dependency


def require_super_admin(current_user: User = Depends(require_admin)) -> User:
    if not is_super_admin(current_user):
        raise HTTPException(status_code=403, detail="Super admin privileges required")
    return current_user
