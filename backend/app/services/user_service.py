from fastapi import HTTPException
from sqlalchemy import func
from sqlalchemy.orm import Session

from app.core.security import DUMMY_PASSWORD_HASH, create_access_token, hash_password, verify_password
from app.models.user import User
from app.schemas.user import UserCreate


def create_user(
    db: Session,
    user: UserCreate,
) -> User:
    existing_user = (
        db.query(User)
        .filter(func.lower(User.email) == user.email.lower())
        .first()
    )

    if existing_user:
        raise HTTPException(
            status_code=409,
            detail="Email already registered",
        )

    new_user = User(
        full_name=user.full_name,
        email=user.email.lower(),
        hashed_password=hash_password(user.password),
        is_active=True,
        is_admin=False,
        role="student",
        admin_permissions=[],
        must_change_password=False,
        token_version=0,
        preferred_locale="tr",
    )

    db.add(new_user)
    db.commit()
    db.refresh(new_user)

    return new_user

def login_user(
    db: Session,
    email: str,
    password: str,
) -> str:

    normalized_email = email.strip().lower()
    user = (
        db.query(User)
        .filter(func.lower(User.email) == normalized_email)
        .first()
    )

    if user is None:
        verify_password(password, DUMMY_PASSWORD_HASH)
        raise HTTPException(
            status_code=401,
            detail="Invalid email or password",
        )

    if not verify_password(
        password,
        user.hashed_password,
    ):
        raise HTTPException(
            status_code=401,
            detail="Invalid email or password",
        )

    if not user.is_active:
        raise HTTPException(
            status_code=403,
            detail="Account is inactive",
        )

    access_token = create_access_token(
        {
            "sub": user.email,
            "ver": user.token_version,
        }
    )

    return access_token
