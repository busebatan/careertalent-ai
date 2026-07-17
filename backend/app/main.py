"""CareerTalent AI — FastAPI uygulama giriş noktası."""

from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy import text

from app.core.config import settings
from app.core.database import engine, init_db
from app.services.llm import ai_configured, get_active_model_name


@asynccontextmanager
async def lifespan(app: FastAPI):
    if settings.AUTO_CREATE_TABLES:
        init_db()
    yield


app = FastAPI(
    title=settings.APP_NAME,
    description="YZTA Bootcamp kariyer yol arkadaşı API",
    version="0.1.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:8080",
        "http://127.0.0.1:8080",
        "http://localhost:8000",
        "https://careertalent.ygtlabs.ai",
        "http://careertalent.ygtlabs.ai",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

from app.api.v1.router import api_router  # noqa: E402

app.include_router(api_router, prefix="/api/v1")


@app.get("/health")
def health_check():
    """Sunucu ayakta mı kontrolü."""
    return {"status": "ok", "app": settings.APP_NAME}


@app.get("/health/ready")
def readiness_check():
    """Veritabanı ve AI yapılandırması hazır mı."""
    db_ok = False
    try:
        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        db_ok = True
    except Exception:
        db_ok = False

    return {
        "status": "ok" if db_ok and ai_configured() else "degraded",
        "database": "postgresql" if "postgresql" in settings.DATABASE_URL else "other",
        "database_ok": db_ok,
        "ai_provider": settings.AI_PROVIDER,
        "ai_model": get_active_model_name(),
        "ai_configured": ai_configured(),
    }
