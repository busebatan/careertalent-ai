"""CareerTalent AI — FastAPI uygulama giriş noktası."""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.core.config import settings

app = FastAPI(
    title=settings.APP_NAME,
    description="YZTA Bootcamp kariyer yol arkadaşı API",
    version="0.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # MVP: Streamlit için; prod'da kısıtla
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health")
def health_check():
    """Sunucu ayakta mı kontrolü."""
    return {"status": "ok", "app": settings.APP_NAME}


# Sprint 1'de açılacak router'lar:
# from app.api.v1 import auth, cv, careers, roadmaps, chat, admin
# app.include_router(auth.router, prefix="/api/v1/auth", tags=["Kimlik"])
# app.include_router(cv.router, prefix="/api/v1/cv", tags=["CV"])
# ...
