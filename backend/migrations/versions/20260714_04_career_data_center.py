"""Kariyer Veri Merkezi normalize katalog tabloları.

Revision ID: 20260714_04
Revises: 20260713_03
"""
import json
import re
import unicodedata

from alembic import op
import sqlalchemy as sa


revision = "20260714_04"
down_revision = "20260713_03"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.create_table(
        "career_skills",
        sa.Column("id", sa.Integer(), primary_key=True),
        sa.Column("slug", sa.String(length=120), nullable=False),
        sa.Column("name", sa.String(length=160), nullable=False),
        sa.Column("skill_type", sa.String(length=20), nullable=False, server_default="domain"),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column("is_active", sa.Boolean(), nullable=False, server_default=sa.true()),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.CheckConstraint("skill_type IN ('technical', 'domain', 'soft', 'tool')", name="ck_career_skills_type"),
        sa.UniqueConstraint("slug", name="uq_career_skills_slug"),
        sa.UniqueConstraint("name", name="uq_career_skills_name"),
    )
    op.create_index("ix_career_skills_slug", "career_skills", ["slug"])
    op.create_index("ix_career_skills_name", "career_skills", ["name"])

    op.create_table(
        "career_data_sources",
        sa.Column("id", sa.Integer(), primary_key=True),
        sa.Column("slug", sa.String(length=120), nullable=False),
        sa.Column("name", sa.String(length=180), nullable=False),
        sa.Column("source_type", sa.String(length=20), nullable=False, server_default="manual"),
        sa.Column("url", sa.String(length=2048), nullable=True),
        sa.Column("reference_uri", sa.String(length=1024), nullable=True),
        sa.Column("version", sa.String(length=80), nullable=True),
        sa.Column("checksum_sha256", sa.String(length=64), nullable=True),
        sa.Column("license", sa.String(length=255), nullable=True),
        sa.Column("description", sa.Text(), nullable=True),
        sa.Column("status", sa.String(length=20), nullable=False, server_default="active"),
        sa.Column("last_verified_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.CheckConstraint("source_type IN ('official', 'report', 'market', 'manual')", name="ck_career_data_sources_type"),
        sa.CheckConstraint("status IN ('active', 'archived')", name="ck_career_data_sources_status"),
        sa.UniqueConstraint("slug", name="uq_career_data_sources_slug"),
        sa.UniqueConstraint("name", name="uq_career_data_sources_name"),
    )
    op.create_index("ix_career_data_sources_slug", "career_data_sources", ["slug"])
    op.create_index("ix_career_data_sources_name", "career_data_sources", ["name"])

    op.create_table(
        "career_role_skill_requirements",
        sa.Column("id", sa.Integer(), primary_key=True),
        sa.Column("career_role_id", sa.Integer(), nullable=False),
        sa.Column("career_skill_id", sa.Integer(), nullable=False),
        sa.Column("data_source_id", sa.Integer(), nullable=True),
        sa.Column("requirement_type", sa.String(length=20), nullable=False, server_default="required"),
        sa.Column("expected_level", sa.String(length=20), nullable=False, server_default="intermediate"),
        sa.Column("weight", sa.Integer(), nullable=False, server_default="100"),
        sa.Column("notes", sa.Text(), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.func.now()),
        sa.CheckConstraint("requirement_type IN ('required', 'preferred')", name="ck_career_role_skill_requirement_type"),
        sa.CheckConstraint("expected_level IN ('basic', 'intermediate', 'advanced', 'expert')", name="ck_career_role_skill_requirement_level"),
        sa.CheckConstraint("weight >= 1 AND weight <= 100", name="ck_career_role_skill_requirement_weight"),
        sa.ForeignKeyConstraint(["career_role_id"], ["career_roles.id"], name="fk_career_role_skill_requirements_role", ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["career_skill_id"], ["career_skills.id"], name="fk_career_role_skill_requirements_skill", ondelete="RESTRICT"),
        sa.ForeignKeyConstraint(["data_source_id"], ["career_data_sources.id"], name="fk_career_role_skill_requirements_source", ondelete="SET NULL"),
        sa.UniqueConstraint("career_role_id", "career_skill_id", name="uq_career_role_skill_requirement"),
    )
    op.create_index("ix_career_role_skill_requirements_role", "career_role_skill_requirements", ["career_role_id"])
    op.create_index("ix_career_role_skill_requirements_skill", "career_role_skill_requirements", ["career_skill_id"])
    op.create_index("ix_career_role_skill_requirements_source", "career_role_skill_requirements", ["data_source_id"])
    _backfill_legacy_required_skills()
    _register_repo_sources()


def downgrade() -> None:
    raise RuntimeError("Career Data Center migration is forward-only; catalog records must not be dropped.")


def _backfill_legacy_required_skills() -> None:
    bind = op.get_bind()
    roles = sa.table("career_roles", sa.column("id", sa.Integer()), sa.column("required_skills", sa.JSON()))
    skills = sa.table("career_skills", sa.column("id", sa.Integer()), sa.column("slug", sa.String()), sa.column("name", sa.String()), sa.column("skill_type", sa.String()), sa.column("is_active", sa.Boolean()))
    requirements = sa.table("career_role_skill_requirements", sa.column("career_role_id", sa.Integer()), sa.column("career_skill_id", sa.Integer()), sa.column("requirement_type", sa.String()), sa.column("expected_level", sa.String()), sa.column("weight", sa.Integer()))
    skill_ids: dict[str, int] = {}
    used_slugs: set[str] = set()

    for role in bind.execute(sa.select(roles.c.id, roles.c.required_skills)):
        seen: set[str] = set()
        for legacy_requirement in _legacy_requirements(role.required_skills):
            name = legacy_requirement["name"]
            key = name.casefold()
            if key in seen:
                continue
            seen.add(key)
            skill_id = skill_ids.get(key)
            if skill_id is None:
                slug = _unique_slug(_slugify(name), used_slugs)
                bind.execute(skills.insert().values(slug=slug, name=name, skill_type="domain", is_active=True))
                skill_id = bind.execute(sa.select(skills.c.id).where(skills.c.slug == slug)).scalar_one()
                skill_ids[key] = skill_id
            bind.execute(
                requirements.insert().values(
                    career_role_id=role.id,
                    career_skill_id=skill_id,
                    requirement_type=legacy_requirement["requirement_type"],
                    expected_level=legacy_requirement["expected_level"],
                    weight=legacy_requirement["weight"],
                )
            )


def _register_repo_sources() -> None:
    sources = sa.table(
        "career_data_sources",
        sa.column("slug", sa.String()),
        sa.column("name", sa.String()),
        sa.column("source_type", sa.String()),
        sa.column("reference_uri", sa.String()),
        sa.column("version", sa.String()),
        sa.column("checksum_sha256", sa.String()),
    )
    op.get_bind().execute(
        sources.insert(),
        [
            {
                "slug": "bootcamp-role-catalog",
                "name": "Bootcamp rol kataloğu",
                "source_type": "manual",
                "reference_uri": "data/roles/bootcamp_roles.json",
                "version": "2026-07-14",
                "checksum_sha256": "34ca43defd6e227eacc0e7ad54e914670ab122af9cb7edd5e2e790d47a473e8e",
            },
            {
                "slug": "career-dataset-cleaned",
                "name": "Temizlenmiş kariyer veri kümesi",
                "source_type": "market",
                "reference_uri": "data/roles/first_dataset_cleaned.csv",
                "version": "2026-07-14",
                "checksum_sha256": "4a63ac2c1bd5d474ab9d06a962b160a19ad78b28570c956571d642af942a59a7",
            },
        ],
    )


def _legacy_requirements(value) -> list[dict[str, str | int]]:
    if isinstance(value, str):
        try:
            value = json.loads(value)
        except json.JSONDecodeError:
            value = []
    if not isinstance(value, list):
        return []

    requirements: list[dict[str, str | int]] = []
    for item in value:
        if isinstance(item, str) and item.strip():
            requirements.append(_legacy_requirement(item.strip(), None, None))
        elif isinstance(item, dict) and isinstance(item.get("name"), str) and item["name"].strip():
            requirements.append(_legacy_requirement(item["name"].strip(), item.get("level"), item.get("priority")))
    return requirements


def _legacy_requirement(name: str, level, priority) -> dict[str, str | int]:
    expected_level = {"temel": "basic", "orta": "intermediate", "ileri": "advanced"}.get(str(level).lower(), "intermediate")
    requirement_type = {"zorunlu": "required", "tercih": "preferred"}.get(str(priority).lower(), "required")
    return {
        "name": name,
        "expected_level": expected_level,
        "requirement_type": requirement_type,
        "weight": 100 if requirement_type == "required" else 50,
    }


def _slugify(value: str) -> str:
    value = value.strip().lower().replace("ı", "i").replace("#", " sharp ").replace("+", " plus ")
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    return re.sub(r"[^a-z0-9]+", "-", value).strip("-") or "skill"


def _unique_slug(base: str, used_slugs: set[str]) -> str:
    slug = base
    suffix = 2
    while slug in used_slugs:
        slug = f"{base}-{suffix}"
        suffix += 1
    used_slugs.add(slug)
    return slug
