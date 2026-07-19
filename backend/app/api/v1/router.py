from fastapi import APIRouter, Depends

from app.api.v1 import admin, auth, career, career_data, career_roles, company, cv, engagement, panel
from app.core.security import require_candidate_portal_user

api_router = APIRouter()
candidate_dependencies = [Depends(require_candidate_portal_user)]

api_router.include_router(cv.router, prefix="/cv", tags=["CV"], dependencies=candidate_dependencies)
api_router.include_router(panel.router, prefix="/panel", tags=["Panel"], dependencies=candidate_dependencies)
api_router.include_router(admin.router, prefix="/admin", tags=["Admin"])
api_router.include_router(company.router, prefix="/company", tags=["Company"])
api_router.include_router(career_data.router, prefix="/admin/career-data", tags=["Career Data"])
api_router.include_router(career_roles.router,prefix="/career-roles",tags=["Career Roles"],)
api_router.include_router( auth.router, prefix="/auth",tags=["Authentication"],)
api_router.include_router(career.router, dependencies=candidate_dependencies)
api_router.include_router(engagement.router, dependencies=candidate_dependencies)
