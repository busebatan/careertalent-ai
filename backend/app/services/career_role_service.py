from sqlalchemy import select
from sqlalchemy.orm import Session
from fastapi import HTTPException

from app.models.career_role import CareerRole
from app.models.career_catalog import CareerRoleSkillRequirement, CareerSkill
from app.schemas.career_role import CareerRoleCreate
from app.services.career_catalog_service import sync_legacy_role_skills



def get_roles(db: Session,) -> list[dict]:
    return [_legacy_payload(db, role) for role in db.query(CareerRole).order_by(CareerRole.id).all()]


def get_role(db: Session, role_id: int,) -> dict:
    role = (
        db.query(CareerRole)
        .filter(CareerRole.id == role_id)
        .first()
    )

    if role is None:
        raise HTTPException(
            status_code=404,
            detail="Career role not found",
        )

    return _legacy_payload(db, role)


def create_role(
    db: Session,
    role: CareerRoleCreate,) -> dict:
    if db.query(CareerRole).filter(CareerRole.slug == role.slug).first() is not None:
        raise HTTPException(status_code=409, detail="Career role slug already exists")
    new_role = CareerRole(
        slug=role.slug,
        title=role.title,
        description=role.description,
        required_skills=role.required_skills,
        weeks_template=role.weeks_template,
    )

    db.add(new_role)
    db.flush()
    sync_legacy_role_skills(db, new_role, role.required_skills)
    db.commit()
    db.refresh(new_role)

    return _legacy_payload(db, new_role)


def update_role(
    db: Session,
    role_id: int,
    role: CareerRoleCreate,
) -> dict:
    db_role = (
        db.query(CareerRole)
        .filter(CareerRole.id == role_id)
        .first()
    )

    if db_role is None:
        raise HTTPException(
            status_code=404,
            detail="Career role not found",
        )

    existing = db.query(CareerRole).filter(CareerRole.slug == role.slug, CareerRole.id != db_role.id).first()
    if existing is not None:
        raise HTTPException(status_code=409, detail="Career role slug already exists")

    db_role.slug = role.slug
    db_role.title = role.title
    db_role.description = role.description
    db_role.required_skills = role.required_skills
    db_role.weeks_template = role.weeks_template

    sync_legacy_role_skills(db, db_role, role.required_skills)

    db.commit()
    db.refresh(db_role)

    return _legacy_payload(db, db_role)


def delete_role(
    db: Session,
    role_id: int,
) -> dict[str, str]:
    db_role = (
        db.query(CareerRole)
        .filter(CareerRole.id == role_id)
        .first()
    )

    if db_role is None:
        raise HTTPException(
            status_code=404,
            detail="Career role not found",
        )

    db.query(CareerRoleSkillRequirement).filter(CareerRoleSkillRequirement.career_role_id == db_role.id).delete(synchronize_session=False)
    db.delete(db_role)
    db.commit()

    return {
        "message": "Career role deleted successfully"
    }


def _legacy_payload(db: Session, role: CareerRole) -> dict:
    normalized_names = db.scalars(
        select(CareerSkill.name)
        .join(CareerRoleSkillRequirement, CareerRoleSkillRequirement.career_skill_id == CareerSkill.id)
        .where(CareerRoleSkillRequirement.career_role_id == role.id)
        .order_by(CareerRoleSkillRequirement.weight.desc(), CareerSkill.name)
    ).all()
    return {
        "id": role.id,
        "slug": role.slug,
        "title": role.title,
        "description": role.description,
        "required_skills": list(normalized_names) if normalized_names else _skill_names(role.required_skills),
        "weeks_template": role.weeks_template,
    }


def _skill_names(value: object) -> list[str]:
    if not isinstance(value, list):
        return []
    names: list[str] = []
    for item in value:
        if isinstance(item, str) and item.strip():
            names.append(item.strip())
        elif isinstance(item, dict) and isinstance(item.get("name"), str) and item["name"].strip():
            names.append(item["name"].strip())
    return names
