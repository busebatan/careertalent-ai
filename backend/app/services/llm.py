"""Yapay zeka istemcisi — public facade (factory üzerinden)."""

from langchain_core.language_models.chat_models import BaseChatModel

from app.services.ai_factory import (
    SUPPORTED_PROVIDERS,
    ai_configured,
    create_chat_model,
    get_active_model_name,
)

__all__ = [
    "SUPPORTED_PROVIDERS",
    "ai_configured",
    "create_chat_model",
    "get_active_model_name",
    "get_chat_model",
]


def get_chat_model() -> BaseChatModel:
    return create_chat_model()
