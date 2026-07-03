from fastapi import APIRouter

from app.api.v1 import careers

api_router = APIRouter()
api_router.include_router(careers.router, prefix="/careers", tags=["Meslekler"])
