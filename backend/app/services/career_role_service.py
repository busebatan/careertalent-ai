from sqlalchemy.orm import Session
from fastapi import HTTPException

from app.models.career_role import CareerRole
from app.schemas.career_role import CareerRoleCreate



def get_roles(db: Session,) -> list[CareerRole]:
    return db.query(CareerRole).all()


def get_role(db: Session, role_id: int,)-> CareerRole:
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

    return role


def create_role(
    db: Session,
    role: CareerRoleCreate,) -> CareerRole:
    new_role = CareerRole(
        slug=role.slug,
        title=role.title,
        description=role.description,
        required_skills=role.required_skills,
        weeks_template=role.weeks_template,
    )

    db.add(new_role)
    db.commit()
    db.refresh(new_role)

    return new_role


def update_role(
    db: Session,
    role_id: int,
    role: CareerRoleCreate,
) -> CareerRole:
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

    db_role.slug = role.slug
    db_role.title = role.title
    db_role.description = role.description
    db_role.required_skills = role.required_skills
    db_role.weeks_template = role.weeks_template

    db.commit()
    db.refresh(db_role)

    return db_role


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

    db.delete(db_role)
    db.commit()

    return {
        "message": "Career role deleted successfully"
    }