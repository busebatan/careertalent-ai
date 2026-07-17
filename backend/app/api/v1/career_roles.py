from fastapi import APIRouter , Depends 
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.security import require_admin
from app.models.user import User
from app.schemas.career_role import (CareerRoleCreate,CareerRoleResponse,)
from app.services import career_role_service

router = APIRouter()


@router.get("/", response_model=list[CareerRoleResponse])
def get_roles(
    db: Session = Depends(get_db)
):
    return career_role_service.get_roles(db)

@router.post("/", response_model=CareerRoleResponse)
def create_role(
    role: CareerRoleCreate,
    db: Session = Depends(get_db),
    _admin: User = Depends(require_admin),
):
    return career_role_service.create_role(db, role)


@router.get("/{role_id}", response_model=CareerRoleResponse)
def get_role(
    role_id: int,
    db: Session = Depends(get_db),
):
    return career_role_service.get_role(db, role_id)


@router.put("/{role_id}", response_model=CareerRoleResponse)
def update_role(
    role_id: int,
    role: CareerRoleCreate,
    db: Session = Depends(get_db),
    _admin: User = Depends(require_admin),
):
    return career_role_service.update_role(
        db,
        role_id,
        role,
    )


@router.delete("/{role_id}")
def delete_role(
    role_id: int,
    db: Session = Depends(get_db),
    _admin: User = Depends(require_admin),
):
    return career_role_service.delete_role(
        db,
        role_id,
    )
