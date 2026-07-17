from fastapi import APIRouter

from app.api.v1 import admin, auth, career, career_data, career_roles, cv, engagement, panel

api_router = APIRouter()
api_router.include_router(cv.router, prefix="/cv", tags=["CV"])
api_router.include_router(panel.router, prefix="/panel", tags=["Panel"])
api_router.include_router(admin.router, prefix="/admin", tags=["Admin"])
api_router.include_router(career_data.router, prefix="/admin/career-data", tags=["Career Data"])
api_router.include_router(career_roles.router,prefix="/career-roles",tags=["Career Roles"],)
api_router.include_router( auth.router, prefix="/auth",tags=["Authentication"],)
api_router.include_router(career.router)
api_router.include_router(engagement.router)
