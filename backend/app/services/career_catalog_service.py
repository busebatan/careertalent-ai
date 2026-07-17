import re
import unicodedata

from fastapi import HTTPException
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from app.models.career_catalog import CareerDataSource, CareerRoleSkillRequirement, CareerSkill
from app.models.career_role import CareerRole
from app.schemas.career_catalog import CareerDataRoleWrite, CareerDataSourceWrite, CareerRoleSkillRequirementWrite, CareerSkillWrite


def list_roles(db: Session) -> list[dict]:
    roles = db.scalars(select(CareerRole).order_by(CareerRole.title, CareerRole.id)).all()
    counts = dict(db.execute(select(CareerRoleSkillRequirement.career_role_id, func.count()).group_by(CareerRoleSkillRequirement.career_role_id)).all())
    return [_role_payload(role, int(counts.get(role.id, 0))) for role in roles]


def create_role(db: Session, payload: CareerDataRoleWrite) -> dict:
    _assert_role_slug_available(db, payload.slug)
    role = CareerRole(
        slug=payload.slug,
        title=payload.title,
        description=payload.description,
        required_skills=[],
        weeks_template=payload.weeks_template,
    )
    db.add(role)
    db.commit()
    db.refresh(role)
    return _role_payload(role, 0)


def update_role(db: Session, role_id: int, payload: CareerDataRoleWrite) -> dict:
    role = _role_or_404(db, role_id)
    _assert_role_slug_available(db, payload.slug, role.id)
    role.slug = payload.slug
    role.title = payload.title
    role.description = payload.description
    role.weeks_template = payload.weeks_template
    db.commit()
    db.refresh(role)
    return _role_payload(role, _requirement_count(db, role.id))


def delete_role(db: Session, role_id: int) -> None:
    role = _role_or_404(db, role_id)
    db.query(CareerRoleSkillRequirement).filter(CareerRoleSkillRequirement.career_role_id == role.id).delete(synchronize_session=False)
    db.delete(role)
    db.commit()


def list_skills(db: Session) -> list[dict]:
    skills = db.scalars(select(CareerSkill).order_by(CareerSkill.name, CareerSkill.id)).all()
    counts = dict(db.execute(select(CareerRoleSkillRequirement.career_skill_id, func.count()).group_by(CareerRoleSkillRequirement.career_skill_id)).all())
    return [_skill_payload(skill, int(counts.get(skill.id, 0))) for skill in skills]


def create_skill(db: Session, payload: CareerSkillWrite) -> dict:
    _assert_skill_available(db, payload.slug, payload.name)
    skill = CareerSkill(**payload.model_dump())
    db.add(skill)
    db.commit()
    db.refresh(skill)
    return _skill_payload(skill, 0)


def update_skill(db: Session, skill_id: int, payload: CareerSkillWrite) -> dict:
    skill = _skill_or_404(db, skill_id)
    _assert_skill_available(db, payload.slug, payload.name, skill.id)
    for key, value in payload.model_dump().items():
        setattr(skill, key, value)
    db.flush()
    _refresh_roles_for_skill(db, skill.id)
    db.commit()
    db.refresh(skill)
    return _skill_payload(skill, _requirement_count_for_skill(db, skill.id))


def delete_skill(db: Session, skill_id: int) -> None:
    skill = _skill_or_404(db, skill_id)
    if _requirement_count_for_skill(db, skill.id):
        raise HTTPException(status_code=409, detail="Skill is still required by career roles")
    db.delete(skill)
    db.commit()


def list_sources(db: Session) -> list[dict]:
    sources = db.scalars(select(CareerDataSource).order_by(CareerDataSource.name, CareerDataSource.id)).all()
    counts = dict(db.execute(select(CareerRoleSkillRequirement.data_source_id, func.count()).where(CareerRoleSkillRequirement.data_source_id.is_not(None)).group_by(CareerRoleSkillRequirement.data_source_id)).all())
    return [_source_payload(source, int(counts.get(source.id, 0))) for source in sources]


def create_source(db: Session, payload: CareerDataSourceWrite) -> dict:
    _assert_source_available(db, payload.slug, payload.name)
    source = CareerDataSource(**payload.model_dump())
    db.add(source)
    db.commit()
    db.refresh(source)
    return _source_payload(source, 0)


def update_source(db: Session, source_id: int, payload: CareerDataSourceWrite) -> dict:
    source = _source_or_404(db, source_id)
    _assert_source_available(db, payload.slug, payload.name, source.id)
    for key, value in payload.model_dump().items():
        setattr(source, key, value)
    db.commit()
    db.refresh(source)
    return _source_payload(source, _requirement_count_for_source(db, source.id))


def delete_source(db: Session, source_id: int) -> None:
    source = _source_or_404(db, source_id)
    db.query(CareerRoleSkillRequirement).filter(CareerRoleSkillRequirement.data_source_id == source.id).update({CareerRoleSkillRequirement.data_source_id: None}, synchronize_session=False)
    db.delete(source)
    db.commit()


def list_requirements(db: Session) -> list[dict]:
    rows = db.execute(
        select(CareerRoleSkillRequirement, CareerRole, CareerSkill, CareerDataSource)
        .join(CareerRole, CareerRole.id == CareerRoleSkillRequirement.career_role_id)
        .join(CareerSkill, CareerSkill.id == CareerRoleSkillRequirement.career_skill_id)
        .outerjoin(CareerDataSource, CareerDataSource.id == CareerRoleSkillRequirement.data_source_id)
        .order_by(CareerRole.title, CareerRoleSkillRequirement.weight.desc(), CareerSkill.name)
    ).all()
    return [_requirement_payload(requirement, role, skill, source) for requirement, role, skill, source in rows]


def create_requirement(db: Session, payload: CareerRoleSkillRequirementWrite) -> dict:
    _role_or_404(db, payload.career_role_id)
    _skill_or_404(db, payload.career_skill_id)
    if payload.data_source_id is not None:
        _source_or_404(db, payload.data_source_id)
    existing = db.scalar(
        select(CareerRoleSkillRequirement).where(
            CareerRoleSkillRequirement.career_role_id == payload.career_role_id,
            CareerRoleSkillRequirement.career_skill_id == payload.career_skill_id,
        )
    )
    if existing is not None:
        raise HTTPException(status_code=409, detail="This skill is already linked to the career role")
    requirement = CareerRoleSkillRequirement(**payload.model_dump())
    db.add(requirement)
    db.flush()
    _sync_legacy_required_skills(db, payload.career_role_id)
    db.commit()
    return _requirement_by_id(db, requirement.id)


def update_requirement(db: Session, requirement_id: int, payload: CareerRoleSkillRequirementWrite) -> dict:
    requirement = _requirement_or_404(db, requirement_id)
    _role_or_404(db, payload.career_role_id)
    _skill_or_404(db, payload.career_skill_id)
    if payload.data_source_id is not None:
        _source_or_404(db, payload.data_source_id)
    duplicate = db.scalar(
        select(CareerRoleSkillRequirement).where(
            CareerRoleSkillRequirement.career_role_id == payload.career_role_id,
            CareerRoleSkillRequirement.career_skill_id == payload.career_skill_id,
            CareerRoleSkillRequirement.id != requirement.id,
        )
    )
    if duplicate is not None:
        raise HTTPException(status_code=409, detail="This skill is already linked to the career role")
    previous_role_id = requirement.career_role_id
    for key, value in payload.model_dump().items():
        setattr(requirement, key, value)
    db.flush()
    _sync_legacy_required_skills(db, previous_role_id)
    if previous_role_id != requirement.career_role_id:
        _sync_legacy_required_skills(db, requirement.career_role_id)
    db.commit()
    return _requirement_by_id(db, requirement.id)


def delete_requirement(db: Session, requirement_id: int) -> None:
    requirement = _requirement_or_404(db, requirement_id)
    role_id = requirement.career_role_id
    db.delete(requirement)
    db.flush()
    _sync_legacy_required_skills(db, role_id)
    db.commit()


def sync_legacy_role_skills(db: Session, role: CareerRole, names: list[str]) -> None:
    normalized = _unique_names(names)
    links = db.scalars(select(CareerRoleSkillRequirement).where(CareerRoleSkillRequirement.career_role_id == role.id)).all()
    by_skill_id = {link.career_skill_id: link for link in links}
    wanted_skill_ids: set[int] = set()
    for name in normalized:
        skill = _find_or_create_legacy_skill(db, name)
        wanted_skill_ids.add(skill.id)
        if skill.id not in by_skill_id:
            db.add(CareerRoleSkillRequirement(career_role_id=role.id, career_skill_id=skill.id))
    for skill_id, link in by_skill_id.items():
        if skill_id not in wanted_skill_ids:
            db.delete(link)
    db.flush()
    _sync_legacy_required_skills(db, role.id)


def _sync_legacy_required_skills(db: Session, role_id: int) -> None:
    role = _role_or_404(db, role_id)
    names = db.scalars(
        select(CareerSkill.name)
        .join(CareerRoleSkillRequirement, CareerRoleSkillRequirement.career_skill_id == CareerSkill.id)
        .where(CareerRoleSkillRequirement.career_role_id == role_id)
        .order_by(CareerRoleSkillRequirement.weight.desc(), CareerSkill.name)
    ).all()
    role.required_skills = list(names)


def _refresh_roles_for_skill(db: Session, skill_id: int) -> None:
    role_ids = db.scalars(
        select(CareerRoleSkillRequirement.career_role_id).where(CareerRoleSkillRequirement.career_skill_id == skill_id)
    ).all()
    for role_id in set(role_ids):
        _sync_legacy_required_skills(db, role_id)


def _role_payload(role: CareerRole, requirement_count: int) -> dict:
    return {
        "id": role.id,
        "slug": role.slug,
        "title": role.title,
        "description": role.description,
        "weeks_template": role.weeks_template,
        "required_skills": list(role.required_skills or []),
        "requirement_count": requirement_count,
    }


def _skill_payload(skill: CareerSkill, requirement_count: int) -> dict:
    return {
        "id": skill.id,
        "slug": skill.slug,
        "name": skill.name,
        "skill_type": skill.skill_type,
        "description": skill.description,
        "is_active": skill.is_active,
        "requirement_count": requirement_count,
    }


def _source_payload(source: CareerDataSource, requirement_count: int) -> dict:
    return {
        "id": source.id,
        "slug": source.slug,
        "name": source.name,
        "source_type": source.source_type,
        "url": source.url,
        "reference_uri": source.reference_uri,
        "version": source.version,
        "checksum_sha256": source.checksum_sha256,
        "license": source.license,
        "description": source.description,
        "status": source.status,
        "last_verified_at": source.last_verified_at,
        "requirement_count": requirement_count,
    }


def _requirement_by_id(db: Session, requirement_id: int) -> dict:
    row = db.execute(
        select(CareerRoleSkillRequirement, CareerRole, CareerSkill, CareerDataSource)
        .join(CareerRole, CareerRole.id == CareerRoleSkillRequirement.career_role_id)
        .join(CareerSkill, CareerSkill.id == CareerRoleSkillRequirement.career_skill_id)
        .outerjoin(CareerDataSource, CareerDataSource.id == CareerRoleSkillRequirement.data_source_id)
        .where(CareerRoleSkillRequirement.id == requirement_id)
    ).one()
    return _requirement_payload(*row)


def _requirement_payload(requirement: CareerRoleSkillRequirement, role: CareerRole, skill: CareerSkill, source: CareerDataSource | None) -> dict:
    return {
        "id": requirement.id,
        "career_role_id": role.id,
        "career_role_title": role.title,
        "career_skill_id": skill.id,
        "career_skill_name": skill.name,
        "data_source_id": source.id if source else None,
        "data_source_name": source.name if source else None,
        "requirement_type": requirement.requirement_type,
        "expected_level": requirement.expected_level,
        "weight": requirement.weight,
        "notes": requirement.notes,
    }


def _find_or_create_legacy_skill(db: Session, name: str) -> CareerSkill:
    skill = db.scalar(select(CareerSkill).where(func.lower(CareerSkill.name) == name.lower()))
    if skill is not None:
        return skill
    base_slug = _slugify(name)
    slug = base_slug
    suffix = 2
    while db.scalar(select(CareerSkill.id).where(CareerSkill.slug == slug)) is not None:
        slug = f"{base_slug}-{suffix}"
        suffix += 1
    skill = CareerSkill(slug=slug, name=name, skill_type="domain", is_active=True)
    db.add(skill)
    db.flush()
    return skill


def _unique_names(names: list[str]) -> list[str]:
    result: list[str] = []
    seen: set[str] = set()
    for name in names:
        value = name.strip()
        key = value.casefold()
        if value and key not in seen:
            seen.add(key)
            result.append(value)
    return result


def _slugify(value: str) -> str:
    value = value.strip().lower().replace("ı", "i").replace("#", " sharp ").replace("+", " plus ")
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    value = re.sub(r"[^a-z0-9]+", "-", value).strip("-")
    return value or "skill"


def _role_or_404(db: Session, role_id: int) -> CareerRole:
    role = db.get(CareerRole, role_id)
    if role is None:
        raise HTTPException(status_code=404, detail="Career role not found")
    return role


def _skill_or_404(db: Session, skill_id: int) -> CareerSkill:
    skill = db.get(CareerSkill, skill_id)
    if skill is None:
        raise HTTPException(status_code=404, detail="Career skill not found")
    return skill


def _source_or_404(db: Session, source_id: int) -> CareerDataSource:
    source = db.get(CareerDataSource, source_id)
    if source is None:
        raise HTTPException(status_code=404, detail="Career data source not found")
    return source


def _requirement_or_404(db: Session, requirement_id: int) -> CareerRoleSkillRequirement:
    requirement = db.get(CareerRoleSkillRequirement, requirement_id)
    if requirement is None:
        raise HTTPException(status_code=404, detail="Career role skill requirement not found")
    return requirement


def _assert_role_slug_available(db: Session, slug: str, current_id: int | None = None) -> None:
    existing = db.scalar(select(CareerRole).where(CareerRole.slug == slug))
    if existing is not None and existing.id != current_id:
        raise HTTPException(status_code=409, detail="Career role slug already exists")


def _assert_skill_available(db: Session, slug: str, name: str, current_id: int | None = None) -> None:
    for existing in (db.scalar(select(CareerSkill).where(CareerSkill.slug == slug)), db.scalar(select(CareerSkill).where(func.lower(CareerSkill.name) == name.lower()))):
        if existing is not None and existing.id != current_id:
            raise HTTPException(status_code=409, detail="Career skill slug or name already exists")


def _assert_source_available(db: Session, slug: str, name: str, current_id: int | None = None) -> None:
    for existing in (db.scalar(select(CareerDataSource).where(CareerDataSource.slug == slug)), db.scalar(select(CareerDataSource).where(func.lower(CareerDataSource.name) == name.lower()))):
        if existing is not None and existing.id != current_id:
            raise HTTPException(status_code=409, detail="Career data source slug or name already exists")


def _requirement_count(db: Session, role_id: int) -> int:
    return int(db.scalar(select(func.count()).select_from(CareerRoleSkillRequirement).where(CareerRoleSkillRequirement.career_role_id == role_id)) or 0)


def _requirement_count_for_skill(db: Session, skill_id: int) -> int:
    return int(db.scalar(select(func.count()).select_from(CareerRoleSkillRequirement).where(CareerRoleSkillRequirement.career_skill_id == skill_id)) or 0)


def _requirement_count_for_source(db: Session, source_id: int) -> int:
    return int(db.scalar(select(func.count()).select_from(CareerRoleSkillRequirement).where(CareerRoleSkillRequirement.data_source_id == source_id)) or 0)
