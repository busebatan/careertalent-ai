from fastapi import APIRouter, Depends, HTTPException
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.security import effective_admin_permissions, get_current_user, is_admin_user
from app.models.user import User
from app.schemas.user import TokenResponse, UserCreate, UserLocaleUpdate, UserResponse
from app.services import user_service
from app.services.career_engine import CareerLocalizationError, ensure_career_localizations

router = APIRouter()


def _user_response(user: User) -> dict:
    response = UserResponse.model_validate(user).model_dump(mode="json")
    if is_admin_user(user):
        response["admin_permissions"] = effective_admin_permissions(user)
    return response


@router.post("/register", response_model=UserResponse, status_code=201)
def register(
    user: UserCreate,
    db: Session = Depends(get_db),
):
    return user_service.create_user(
        db,
        user,
    )


@router.post(
    "/login",
    response_model=TokenResponse,
)
def login(
    form_data: OAuth2PasswordRequestForm = Depends(),
    db: Session = Depends(get_db),
):
    access_token = user_service.login_user(
        db,
        form_data.username,
        form_data.password,
    )

    return {
        "access_token": access_token,
        "token_type": "bearer",
    }


@router.get("/me", response_model=UserResponse)
def me(current_user: User = Depends(get_current_user)):
    return _user_response(current_user)


@router.patch("/me/locale", response_model=UserResponse)
def update_locale(
    body: UserLocaleUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    try:
        ensure_career_localizations(db, current_user.id)
    except CareerLocalizationError as exc:
        raise HTTPException(
            status_code=503,
            detail={
                "code": "career_localization_failed",
                "message": "Kariyer içerikleri seçilen panel dilinde hazırlanamadı. Lütfen tekrar deneyin.",
            },
        ) from exc
    current_user.preferred_locale = body.preferred_locale
    db.commit()
    db.refresh(current_user)
    return _user_response(current_user)
