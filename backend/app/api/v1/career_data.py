from fastapi import APIRouter, Depends, Response, status
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.security import require_admin_permission
from app.models.user import User
from app.schemas.career_catalog import (
    CareerDataRoleResponse,
    CareerDataRoleWrite,
    CareerDataSourceResponse,
    CareerDataSourceWrite,
    CareerRoleSkillRequirementResponse,
    CareerRoleSkillRequirementWrite,
    CareerSkillResponse,
    CareerSkillWrite,
)
from app.services import career_catalog_service

router = APIRouter()
require_career_data_view = require_admin_permission("career_data.view")
require_career_data_write = require_admin_permission("career_data.write")
require_career_data_delete = require_admin_permission("career_data.delete")


@router.get("/roles", response_model=list[CareerDataRoleResponse])
def roles(db: Session = Depends(get_db), _admin: User = Depends(require_career_data_view)) -> list[dict]:
    return career_catalog_service.list_roles(db)


@router.post("/roles", response_model=CareerDataRoleResponse, status_code=status.HTTP_201_CREATED)
def create_role(payload: CareerDataRoleWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.create_role(db, payload)


@router.put("/roles/{role_id}", response_model=CareerDataRoleResponse)
def update_role(role_id: int, payload: CareerDataRoleWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.update_role(db, role_id, payload)


@router.delete("/roles/{role_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_role(role_id: int, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_delete)) -> Response:
    career_catalog_service.delete_role(db, role_id)
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/skills", response_model=list[CareerSkillResponse])
def skills(db: Session = Depends(get_db), _admin: User = Depends(require_career_data_view)) -> list[dict]:
    return career_catalog_service.list_skills(db)


@router.post("/skills", response_model=CareerSkillResponse, status_code=status.HTTP_201_CREATED)
def create_skill(payload: CareerSkillWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.create_skill(db, payload)


@router.put("/skills/{skill_id}", response_model=CareerSkillResponse)
def update_skill(skill_id: int, payload: CareerSkillWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.update_skill(db, skill_id, payload)


@router.delete("/skills/{skill_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_skill(skill_id: int, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_delete)) -> Response:
    career_catalog_service.delete_skill(db, skill_id)
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/sources", response_model=list[CareerDataSourceResponse])
def sources(db: Session = Depends(get_db), _admin: User = Depends(require_career_data_view)) -> list[dict]:
    return career_catalog_service.list_sources(db)


@router.post("/sources", response_model=CareerDataSourceResponse, status_code=status.HTTP_201_CREATED)
def create_source(payload: CareerDataSourceWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.create_source(db, payload)


@router.put("/sources/{source_id}", response_model=CareerDataSourceResponse)
def update_source(source_id: int, payload: CareerDataSourceWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.update_source(db, source_id, payload)


@router.delete("/sources/{source_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_source(source_id: int, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_delete)) -> Response:
    career_catalog_service.delete_source(db, source_id)
    return Response(status_code=status.HTTP_204_NO_CONTENT)


@router.get("/requirements", response_model=list[CareerRoleSkillRequirementResponse])
def requirements(db: Session = Depends(get_db), _admin: User = Depends(require_career_data_view)) -> list[dict]:
    return career_catalog_service.list_requirements(db)


@router.post("/requirements", response_model=CareerRoleSkillRequirementResponse, status_code=status.HTTP_201_CREATED)
def create_requirement(payload: CareerRoleSkillRequirementWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.create_requirement(db, payload)


@router.put("/requirements/{requirement_id}", response_model=CareerRoleSkillRequirementResponse)
def update_requirement(requirement_id: int, payload: CareerRoleSkillRequirementWrite, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_write)) -> dict:
    return career_catalog_service.update_requirement(db, requirement_id, payload)


@router.delete("/requirements/{requirement_id}", status_code=status.HTTP_204_NO_CONTENT)
def delete_requirement(requirement_id: int, db: Session = Depends(get_db), _admin: User = Depends(require_career_data_delete)) -> Response:
    career_catalog_service.delete_requirement(db, requirement_id)
    return Response(status_code=status.HTTP_204_NO_CONTENT)
