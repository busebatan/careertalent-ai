from sqlalchemy import select
from app.celery_app import celery_app
from app.core.database import SessionLocal
from app.models.company_recruiting import RecruitingApplication, RecruitingPositionAiAnalysis
from app.services.company_positions import analyze_candidate_application, analyze_position

@celery_app.task(name="company.analyze_position")
def analyze_position_task(analysis_id: str) -> str:
    db=SessionLocal()
    try:
        row=db.scalar(select(RecruitingPositionAiAnalysis).where(RecruitingPositionAiAnalysis.id==analysis_id))
        if row: analyze_position(db,row)
        return analysis_id
    finally: db.close()

@celery_app.task(name="company.analyze_candidate_application")
def analyze_candidate_application_task(application_id: str) -> str:
    db=SessionLocal()
    try:
        row=db.scalar(select(RecruitingApplication).where(RecruitingApplication.id==application_id))
        if row: analyze_candidate_application(db,row)
        return application_id
    finally: db.close()
