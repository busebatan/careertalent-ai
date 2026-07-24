"""CV analiz istek şemaları."""

from __future__ import annotations

from datetime import datetime
from typing import Literal
from pydantic import BaseModel, Field


class AnalyzeTextRequest(BaseModel):
    cv_text: str = Field(..., min_length=40)
    file_name: str | None = None


class GeneratedCvQueueResponse(BaseModel):
    analysis_id: str
    status: Literal["queued", "running", "ready", "failed"]
    file_name: str
    cv_document_id: str


class CandidateCvVersionCreate(BaseModel):
    version_name: str = Field(..., min_length=1, max_length=160)
    language: str = Field(..., min_length=2, max_length=8)  # 'tr' or 'en'
    is_main: bool = False
    payload: dict


class CandidateCvVersionUpdate(BaseModel):
    version_name: str | None = Field(default=None, min_length=1, max_length=160)
    language: str | None = Field(default=None, min_length=2, max_length=8)
    is_main: bool | None = None
    payload: dict | None = None


class CandidateCvVersionResponse(BaseModel):
    id: str
    user_id: int
    version_name: str
    language: str
    is_main: bool
    source_document_id: str | None = None
    payload: dict
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class ActivateBuilderDraftRequest(BaseModel):
    language: Literal["tr", "en"]


class ActivateBuilderDraftResponse(BaseModel):
    document_id: str
    main_version_id: str
    versions: list[CandidateCvVersionResponse]


class GeneratedBuilderDraftResponse(BaseModel):
    document: dict
    main_version_id: str
    versions: list[CandidateCvVersionResponse]
