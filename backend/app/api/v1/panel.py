"""Authenticated compatibility endpoints for the student panel."""

from fastapi import APIRouter, Depends, HTTPException

from app.core.security import get_current_user
from app.schemas.panel import (
    ApplicationsResponse,
    CareerLadderResponse,
    ChatResponse,
    DashboardResponse,
    InterviewResponse,
    JobListingParseRequest,
    JobListingParseResponse,
    JobMatchesResponse,
    JobRadarResponse,
    LearningResponse,
    RoadmapResponse,
    SkillPassportResponse,
    TasksResponse,
)
from app.services.job_listing_parser import parse_job_listing

router = APIRouter(dependencies=[Depends(get_current_user)])


def _empty_stats() -> dict:
    return {
        "readiness": 0,
        "career": "",
        "weekly_tasks_total": 0,
        "weekly_tasks_done": 0,
    }


@router.get("/dashboard", response_model=DashboardResponse)
def dashboard() -> dict:
    return {
        "stats": _empty_stats(),
        "weekly_tasks": [],
        "learning_resources": [],
    }


@router.get("/roadmap", response_model=RoadmapResponse)
def roadmap() -> dict:
    return {"stats": _empty_stats(), "weekly_tasks": []}


@router.get("/tasks", response_model=TasksResponse)
def tasks() -> dict:
    return {"stats": _empty_stats(), "weekly_tasks": []}


@router.get("/learning", response_model=LearningResponse)
def learning() -> dict:
    return {"learning_resources": []}


@router.get("/career-ladder", response_model=CareerLadderResponse)
def career_ladder() -> dict:
    return {"career_ladder": [], "career_tier_meta": {}}


@router.get("/skill-passport", response_model=SkillPassportResponse)
def skill_passport() -> dict:
    return {
        "passport": {
            "score": 0,
            "verified": 0,
            "total": 0,
            "items": [],
            "gaps": [],
        }
    }


@router.get("/interview", response_model=InterviewResponse)
def interview() -> dict:
    return {"interview": {"questions": [], "rubric": []}}


@router.get("/applications", response_model=ApplicationsResponse)
def applications() -> dict:
    return {
        "applications": {
            "metrics": {"active": 0, "interviews": 0, "offers": 0},
            "columns": [],
        }
    }


@router.get("/job-radar", response_model=JobRadarResponse)
def job_radar() -> dict:
    return {"radar": {"roles": [], "sources": [], "alerts": []}}


@router.get("/chat", response_model=ChatResponse)
def chat() -> dict:
    return {"assistant": {"prompts": []}}


@router.get("/job-matches", response_model=JobMatchesResponse)
def job_matches() -> dict:
    return {"seed_jobs": [], "user_skills": [], "readiness": 0}


@router.post("/job-listings/parse", response_model=JobListingParseResponse)
def parse_job_listing_endpoint(body: JobListingParseRequest) -> dict:
    try:
        return parse_job_listing(body.url)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
