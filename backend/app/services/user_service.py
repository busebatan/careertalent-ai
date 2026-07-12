from fastapi import HTTPException
from sqlalchemy.orm import Session

from app.core.security import (hash_password,verify_password,create_access_token,)
from app.models.user import User
from app.schemas.user import UserCreate
from app.core.security import (create_access_token, verify_password,)


def create_user(
    db: Session,
    user: UserCreate,
) -> User:
    existing_user = (
        db.query(User)
        .filter(User.email == user.email)
        .first()
    )

    if existing_user:
        raise HTTPException(
            status_code=400,
            detail="Email already registered",
        )

    new_user = User(
        full_name=user.full_name,
        email=user.email,
        hashed_password=hash_password(user.password),
        is_active=True,
        is_admin=False,
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

    user = (
        db.query(User)
        .filter(User.email == email)
        .first()
    )

    if user is None:
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

    access_token = create_access_token(
        {
            "sub": user.email,
        }
    )

    return access_token