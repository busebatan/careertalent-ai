"""Gerçek kayıtları yöneticilere sunan salt-okunur yönetim API'si."""

from collections.abc import Iterable
from typing import Annotated, Literal

from fastapi import APIRouter, Depends
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.core.security import require_admin
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence, JobOpportunity
from app.models.engagement import CareerInterview, CvDocument, JobApplication
from app.models.user import User
from app.schemas.admin import AdminDashboardResponse, AdminModuleResponse, AdminTableRow

router = APIRouter(dependencies=[Depends(require_admin)])
DB = Annotated[Session, Depends(get_db)]
ModuleKey = Literal["students", "readiness", "skill-passport", "job-radar", "applications", "interviews"]
STUDENT_FILTER = (User.is_active.is_(True), User.is_admin.is_(False))
MAX_ROWS = 50


@router.get("/dashboard", response_model=AdminDashboardResponse)
def dashboard(db: DB) -> AdminDashboardResponse:
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

    return AdminDashboardResponse(
        stats=[
            {"label": "Aktif öğrenci", "value": module_counts["students"], "detail": "Admin hesapları hariç"},
            {"label": "Mevcut CV", "value": current_cv_count, "detail": "Aktif CV kaydı"},
            {"label": "Hazır analiz", "value": ready_analysis_count, "detail": "Analizi tamamlanan CV"},
            {"label": "Aktif başvuru", "value": active_application_count, "detail": "Reddedilenler hariç"},
        ],
        module_counts=module_counts,
        recent_students=[
            {
                "name": student.full_name,
                "email": student.email,
                "registered_at": _date(student.created_at),
            }
            for student in students
        ],
    )


@router.get("/modules/{module}", response_model=AdminModuleResponse)
def module(module: ModuleKey, db: DB) -> AdminModuleResponse:
    """Her desteklenen yönetim modülü için yalnız gerçek kayıtları döndür."""
    loaders = {
        "students": _students,
        "readiness": _readiness,
        "skill-passport": _skill_passport,
        "job-radar": _job_radar,
        "applications": _applications,
        "interviews": _interviews,
    }
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
    )


def _module(title: str, subtitle: str, rows: Iterable[AdminTableRow]) -> AdminModuleResponse:
    items = list(rows)
    return AdminModuleResponse(title=title, subtitle=subtitle, total=len(items), rows=items)


def _row(name: str, meta: str, score: str, status: str, next_action: str) -> AdminTableRow:
    return AdminTableRow(name=name, meta=meta, score=score, status=status, next=next_action)


def _ids(db: Session, query) -> set[int]:
    return set(db.scalars(query).all())


def _date(value) -> str | None:
    return value.isoformat() if value is not None else None


def _confidence(value: float | None) -> str:
    return f"AI güveni %{round(value * 100)}" if value is not None else "AI güveni yok"
