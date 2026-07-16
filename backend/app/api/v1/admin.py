"""Gerçek kayıtları yöneticilere sunan salt-okunur yönetim API'si."""

from collections.abc import Iterable
from typing import Annotated, Literal

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.security import (
    ADMIN_PERMISSION_KEYS,
    effective_admin_permissions,
    ensure_admin_permission,
    hash_password,
    is_super_admin,
    require_admin,
    require_admin_permission,
    require_super_admin,
    verify_password,
)
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence, JobOpportunity
from app.models.engagement import CareerInterview, CvDocument, JobApplication
from app.models.user import User
from app.schemas.admin import (
    AdminAccountCreate,
    AdminAccountResponse,
    AdminAccountsResponse,
    AdminAccountUpdate,
    AdminDashboardResponse,
    AdminModuleResponse,
    AdminProfileResponse,
    AdminProfileUpdate,
    AdminTableRow,
)

router = APIRouter()
DB = Annotated[Session, Depends(get_db)]
ModuleKey = Literal["students", "readiness", "skill-passport", "job-radar", "applications", "interviews"]
STUDENT_FILTER = (User.is_active.is_(True), User.is_admin.is_(False))
MAX_ROWS = 50
MODULE_PERMISSIONS = {
    "students": "students.view",
    "readiness": "readiness.view",
    "skill-passport": "skill_passport.view",
    "job-radar": "job_radar.view",
    "applications": "applications.view",
    "interviews": "interviews.view",
}


def _permissions(values: list[str]) -> list[str]:
    unknown = sorted(set(values) - set(ADMIN_PERMISSION_KEYS))
    if unknown:
        raise HTTPException(status_code=422, detail=f"Unknown admin permissions: {', '.join(unknown)}")
    return [key for key in ADMIN_PERMISSION_KEYS if key == "dashboard.view" or key in values]


def _admin_account(user: User) -> AdminAccountResponse:
    return AdminAccountResponse(
        id=user.id,
        full_name=user.full_name,
        email=user.email,
        role="super_admin" if is_super_admin(user) else user.role,
        is_active=user.is_active,
        admin_permissions=effective_admin_permissions(user),
        must_change_password=user.must_change_password,
        created_at=user.created_at.isoformat() if user.created_at else None,
    )


@router.get("/profile", response_model=AdminProfileResponse)
def profile(current_user: User = Depends(require_admin)) -> AdminProfileResponse:
    return _admin_account(current_user)


@router.patch("/profile", response_model=AdminProfileResponse)
def update_profile(payload: AdminProfileUpdate, db: DB, current_user: User = Depends(require_admin)) -> AdminProfileResponse:
    if not verify_password(payload.current_password, current_user.hashed_password):
        raise HTTPException(status_code=422, detail="Current password is incorrect")
    email = str(payload.email).strip().lower()
    duplicate = db.scalar(select(User).where(func.lower(User.email) == email, User.id != current_user.id))
    if duplicate:
        raise HTTPException(status_code=409, detail="Email already registered")
    current_user.full_name = payload.full_name
    current_user.email = email
    if payload.new_password:
        current_user.hashed_password = hash_password(payload.new_password)
        current_user.must_change_password = False
        current_user.token_version += 1
    elif current_user.must_change_password:
        raise HTTPException(status_code=422, detail="New password is required")
    db.commit()
    db.refresh(current_user)
    return _admin_account(current_user)


@router.get("/accounts", response_model=AdminAccountsResponse)
def accounts(db: DB, _super_admin: User = Depends(require_super_admin)) -> AdminAccountsResponse:
    rows = db.scalars(
        select(User).where(User.is_admin.is_(True)).order_by(User.created_at.desc(), User.id.desc())
    ).all()
    return AdminAccountsResponse(permission_keys=list(ADMIN_PERMISSION_KEYS), accounts=[_admin_account(row) for row in rows])


@router.post("/accounts", response_model=AdminAccountResponse, status_code=status.HTTP_201_CREATED)
def create_account(payload: AdminAccountCreate, db: DB, _super_admin: User = Depends(require_super_admin)) -> AdminAccountResponse:
    email = str(payload.email).strip().lower()
    if db.scalar(select(User).where(func.lower(User.email) == email)):
        raise HTTPException(status_code=409, detail="Email already registered")
    user = User(
        full_name=payload.full_name,
        email=email,
        hashed_password=hash_password(payload.temporary_password),
        is_active=True,
        is_admin=True,
        role="admin",
        admin_permissions=_permissions(payload.permissions),
        must_change_password=True,
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return _admin_account(user)


@router.patch("/accounts/{user_id}", response_model=AdminAccountResponse)
def update_account(user_id: int, payload: AdminAccountUpdate, db: DB, _super_admin: User = Depends(require_super_admin)) -> AdminAccountResponse:
    user = db.scalar(select(User).where(User.id == user_id, User.is_admin.is_(True)))
    if user is None:
        raise HTTPException(status_code=404, detail="Admin account not found")
    if is_super_admin(user):
        raise HTTPException(status_code=422, detail="Super admin account must be updated from its profile")
    email = str(payload.email).strip().lower()
    if db.scalar(select(User).where(func.lower(User.email) == email, User.id != user.id)):
        raise HTTPException(status_code=409, detail="Email already registered")
    user.full_name = " ".join(payload.full_name.split())
    user.email = email
    user.is_active = payload.is_active
    user.admin_permissions = _permissions(payload.permissions)
    if payload.temporary_password:
        user.hashed_password = hash_password(payload.temporary_password)
        user.must_change_password = True
        user.token_version += 1
    db.commit()
    db.refresh(user)
    return _admin_account(user)


@router.get("/dashboard", response_model=AdminDashboardResponse)
def dashboard(db: DB, current_user: User = Depends(require_admin_permission("dashboard.view"))) -> AdminDashboardResponse:
    """Admin hesaplarını hariç tutarak canlı yönetim özetini döndür."""
    module_counts = {
        "students": _count(db, User),
        "readiness": _count(db, CareerAnalysis),
        "skill-passport": _count(db, Evidence),
        "job-radar": _count(db, JobOpportunity),
        "applications": _count(db, JobApplication),
        "interviews": _count(db, CareerInterview),
    }
    current_cv_count = db.scalar(
        select(func.count())
        .select_from(CvDocument)
        .join(User, User.id == CvDocument.user_id)
        .where(*STUDENT_FILTER, CvDocument.is_current.is_(True))
    ) or 0
    ready_analysis_count = db.scalar(
        select(func.count())
        .select_from(CareerAnalysis)
        .join(User, User.id == CareerAnalysis.user_id)
        .where(*STUDENT_FILTER, CareerAnalysis.status == "ready")
    ) or 0
    active_application_count = db.scalar(
        select(func.count())
        .select_from(JobApplication)
        .join(User, User.id == JobApplication.user_id)
        .where(*STUDENT_FILTER, JobApplication.stage != "rejected")
    ) or 0
    students = db.scalars(
        select(User)
        .where(*STUDENT_FILTER)
        .order_by(User.created_at.desc(), User.id.desc())
        .limit(5)
    ).all()

    permissions = set(effective_admin_permissions(current_user))
    metrics = [
        ("students.view", {"label": "Aktif öğrenci", "value": module_counts["students"], "detail": "Admin hesapları hariç"}),
        ("readiness.view", {"label": "Mevcut CV", "value": current_cv_count, "detail": "Aktif CV kaydı"}),
        ("readiness.view", {"label": "Hazır analiz", "value": ready_analysis_count, "detail": "Analizi tamamlanan CV"}),
        ("applications.view", {"label": "Aktif başvuru", "value": active_application_count, "detail": "Reddedilenler hariç"}),
    ]
    visible_counts = {
        key: value for key, value in module_counts.items() if MODULE_PERMISSIONS[key] in permissions
    }

    return AdminDashboardResponse(
        stats=[metric for permission, metric in metrics if permission in permissions],
        module_counts=visible_counts,
        recent_students=[
            {
                "name": student.full_name,
                "email": student.email,
                "registered_at": _date(student.created_at),
            }
            for student in students
        ] if "students.view" in permissions else [],
    )


@router.get("/modules/{module}", response_model=AdminModuleResponse)
def module(module: ModuleKey, db: DB, current_user: User = Depends(require_admin)) -> AdminModuleResponse:
    """Her desteklenen yönetim modülü için yalnız gerçek kayıtları döndür."""
    loaders = {
        "students": _students,
        "readiness": _readiness,
        "skill-passport": _skill_passport,
        "job-radar": _job_radar,
        "applications": _applications,
        "interviews": _interviews,
    }
    ensure_admin_permission(current_user, MODULE_PERMISSIONS[module])
    return loaders[module](db)


def _count(db: Session, model: type) -> int:
    statement = select(func.count()).select_from(model)
    if model is User:
        return db.scalar(statement.where(*STUDENT_FILTER)) or 0

    return db.scalar(
        statement.join(User, User.id == model.user_id).where(*STUDENT_FILTER)
    ) or 0


def _students(db: Session) -> AdminModuleResponse:
    students = db.scalars(
        select(User)
        .where(*STUDENT_FILTER)
        .order_by(User.created_at.desc(), User.id.desc())
        .limit(MAX_ROWS)
    ).all()
    student_ids = [student.id for student in students]
    cv_user_ids = _ids(db, select(CvDocument.user_id).where(CvDocument.user_id.in_(student_ids), CvDocument.is_current.is_(True))) if student_ids else set()
    analyses = db.scalars(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id.in_(student_ids))
        .order_by(CareerAnalysis.created_at.desc())
    ).all() if student_ids else []
    analysis_statuses: dict[int, str] = {}
    for analysis in analyses:
        analysis_statuses.setdefault(analysis.user_id, analysis.status)

    return _module(
        "Öğrenciler",
        "Aktif, admin olmayan kullanıcı hesapları.",
        [
            _row(
                student.full_name,
                student.email,
                "CV yüklendi" if student.id in cv_user_ids else "CV yok",
                analysis_statuses.get(student.id, "Analiz yok"),
                f"Kayıt: {_date(student.created_at)}",
            )
            for student in students
        ],
        total=_count(db, User),
    )


def _readiness(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(CareerAnalysis, User.full_name)
        .join(User, User.id == CareerAnalysis.user_id)
        .where(*STUDENT_FILTER)
        .order_by(CareerAnalysis.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Readiness Analizi",
        "CV analizlerinden gelen gerçek işlem durumu ve yetenek sayısı.",
        [
            _row(
                analysis.current_role or analysis.file_name or "Rol belirtilmedi",
                user_name,
                f"{len(analysis.skills)} yetenek",
                analysis.status,
                f"Analiz: {_date(analysis.created_at)}",
            )
            for analysis, user_name in rows
        ],
        total=_count(db, CareerAnalysis),
    )


def _skill_passport(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(Evidence, CareerTask.title, User.full_name)
        .join(CareerTask, CareerTask.id == Evidence.task_id)
        .join(User, User.id == Evidence.user_id)
        .where(*STUDENT_FILTER)
        .order_by(Evidence.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Yetenek Pasaportu",
        "Öğrencilerin yüklediği kanıt kayıtları.",
        [
            _row(
                task_title,
                user_name,
                evidence.kind,
                evidence.status,
                _confidence(evidence.confidence),
            )
            for evidence, task_title, user_name in rows
        ],
        total=_count(db, Evidence),
    )


def _job_radar(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(JobOpportunity, User.full_name)
        .join(User, User.id == JobOpportunity.user_id)
        .where(*STUDENT_FILTER)
        .order_by(JobOpportunity.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "İş Radarı",
        "Öğrencilerin analiz ettiği gerçek iş ilanları.",
        [
            _row(
                job.title or "Başlıksız ilan",
                job.company or job.source or "Şirket belirtilmedi",
                f"%{job.match_score}" if job.match_score is not None else "Skor yok",
                job.status,
                f"Öğrenci: {user_name}",
            )
            for job, user_name in rows
        ],
        total=_count(db, JobOpportunity),
    )


def _applications(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(JobApplication, User.full_name)
        .join(User, User.id == JobApplication.user_id)
        .where(*STUDENT_FILTER)
        .order_by(JobApplication.applied_at.desc(), JobApplication.id.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Başvurular",
        "Öğrencilerin kaydettiği gerçek başvuru kayıtları.",
        [
            _row(
                f"{application.company} · {application.role}",
                f"Öğrenci: {user_name}",
                _date(application.applied_at),
                application.stage,
                application.next_action or "Sonraki aksiyon yok",
            )
            for application, user_name in rows
        ],
        total=_count(db, JobApplication),
    )


def _interviews(db: Session) -> AdminModuleResponse:
    rows = db.execute(
        select(CareerInterview, User.full_name)
        .join(User, User.id == CareerInterview.user_id)
        .where(*STUDENT_FILTER)
        .order_by(CareerInterview.created_at.desc())
        .limit(MAX_ROWS)
    ).all()
    return _module(
        "Mülakatlar",
        "Öğrencilerin başlattığı gerçek mülakat simülasyonları.",
        [
            _row(
                interview.target_role,
                f"Öğrenci: {user_name}",
                f"{len(interview.questions)} soru",
                interview.status,
                f"Başlatıldı: {_date(interview.created_at)}",
            )
            for interview, user_name in rows
        ],
        total=_count(db, CareerInterview),
    )


def _module(
    title: str,
    subtitle: str,
    rows: Iterable[AdminTableRow],
    total: int,
) -> AdminModuleResponse:
    items = list(rows)
    return AdminModuleResponse(title=title, subtitle=subtitle, total=total, rows=items)


def _row(name: str, meta: str, score: str, status: str, next_action: str) -> AdminTableRow:
    return AdminTableRow(name=name, meta=meta, score=score, status=status, next=next_action)


def _ids(db: Session, query) -> set[int]:
    return set(db.scalars(query).all())


def _date(value) -> str | None:
    return value.isoformat() if value is not None else None


def _confidence(value: float | None) -> str:
    return f"AI güveni %{round(value * 100)}" if value is not None else "AI güveni yok"
