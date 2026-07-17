from sqlalchemy import select
from sqlalchemy.exc import OperationalError

from app.celery_app import celery_app
from app.core.database import SessionLocal
from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask, Evidence, JobOpportunity
from app.services.career_engine import analyze_row, plan_target, review_evidence
from app.services.job_opportunity import analyze_job, apply_suggestions

_CV_DATABASE_RETRY_DELAYS = (1, 2, 4)
_CV_DATABASE_ERROR_CODE = "database_unavailable"
_CV_DATABASE_ERROR_MESSAGE = "Veritabanı bağlantısı geçici olarak kesildi. Lütfen CV analizini yeniden dene."


def retry_cv_database_disconnect(task, db, analysis_id: str, error: OperationalError) -> None:
    try:
        db.rollback()
    except OperationalError:
        pass

    retry_count = task.request.retries
    if retry_count < len(_CV_DATABASE_RETRY_DELAYS):
        raise task.retry(exc=error, countdown=_CV_DATABASE_RETRY_DELAYS[retry_count])

    failed_db = SessionLocal()
    try:
        row = failed_db.scalar(select(CareerAnalysis).where(CareerAnalysis.id == analysis_id))
        if row is None:
            return
        row.status = "failed"
        row.error_code = _CV_DATABASE_ERROR_CODE
        row.error_message = _CV_DATABASE_ERROR_MESSAGE
        failed_db.commit()
    except OperationalError:
        failed_db.rollback()
    finally:
        failed_db.close()


@celery_app.task(bind=True, name="career.analyze_cv", max_retries=len(_CV_DATABASE_RETRY_DELAYS))
def analyze_cv_task(self, analysis_id: str) -> str:
    db = SessionLocal()
    try:
        row = db.scalar(select(CareerAnalysis).where(CareerAnalysis.id == analysis_id))
        if row is None:
            return analysis_id
        analyze_row(db, row)
        return analysis_id
    except OperationalError as error:
        retry_cv_database_disconnect(self, db, analysis_id, error)
        return analysis_id
    finally:
        db.close()


@celery_app.task(name="career.plan_target")
def plan_target_task(target_id: str) -> str:
    db = SessionLocal()
    try:
        row = db.scalar(select(CareerTarget).where(CareerTarget.id == target_id))
        if row is None:
            return target_id
        plan_target(db, row)
        return target_id
    finally:
        db.close()


@celery_app.task(name="career.reanalyze")
def reanalyze_task(user_id: int, task_id: str) -> str:
    db = SessionLocal()
    try:
        task = db.scalar(select(CareerTask).where(CareerTask.id == task_id, CareerTask.user_id == user_id))
        analysis = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == user_id).order_by(CareerAnalysis.created_at.desc()))
        if task is not None and analysis is not None:
            accepted = db.scalars(select(Evidence).where(Evidence.user_id == user_id, Evidence.status == "accepted")).all()
            accepted_tasks = {
                row.id: row for row in db.scalars(
                    select(CareerTask).where(CareerTask.user_id == user_id)
                ).all()
            }
            context = [{
                "kind": item.kind,
                "url": item.url,
                "task_title": accepted_tasks.get(item.task_id).title if accepted_tasks.get(item.task_id) else None,
                "skill_impacts": accepted_tasks.get(item.task_id).skill_impacts if accepted_tasks.get(item.task_id) else [],
                "confidence": item.confidence,
                "review_feedback": item.feedback,
            } for item in accepted]
            analyze_row(db, analysis, context)
        return task_id
    finally:
        db.close()


@celery_app.task(name="career.review_evidence")
def review_evidence_task(evidence_id: str) -> str:
    db = SessionLocal()
    try:
        evidence = db.scalar(select(Evidence).where(Evidence.id == evidence_id))
        if evidence is not None:
            result = review_evidence(db, evidence)
            if result.status == "accepted":
                reanalyze_task.delay(result.user_id, result.task_id)
        return evidence_id
    finally:
        db.close()


@celery_app.task(name="career.analyze_job")
def analyze_job_task(job_id: str) -> str:
    db = SessionLocal()
    try:
        row = db.scalar(select(JobOpportunity).where(JobOpportunity.id == job_id))
        if row is not None:
            analyze_job(db, row)
        return job_id
    finally:
        db.close()


@celery_app.task(name="career.apply_job_suggestions")
def apply_job_suggestions_task(job_id: str, suggestion_ids: list[str]) -> str:
    db = SessionLocal()
    try:
        row = db.scalar(select(JobOpportunity).where(JobOpportunity.id == job_id))
        if row is not None:
            apply_suggestions(db, row, suggestion_ids)
        return job_id
    finally:
        db.close()
