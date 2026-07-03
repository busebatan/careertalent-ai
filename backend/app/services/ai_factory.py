"""AI sağlayıcı factory — tek giriş noktası."""

from functools import lru_cache
from typing import Literal

from langchain_core.language_models.chat_models import BaseChatModel

from app.core.config import settings

AIProvider = Literal["deepseek", "gemini", "groq"]
SUPPORTED_PROVIDERS: tuple[AIProvider, ...] = ("deepseek", "gemini", "groq")


def _provider() -> str:
    return settings.AI_PROVIDER.strip().lower()


def _api_key_for(provider: str) -> str:
    keys = {
        "deepseek": settings.DEEPSEEK_API_KEY,
        "gemini": settings.GEMINI_API_KEY,
        "groq": settings.GROQ_API_KEY,
    }
    return keys.get(provider, "").strip()


def ai_configured() -> bool:
    return bool(_api_key_for(_provider()))


def get_active_model_name() -> str:
    models = {
        "deepseek": settings.DEEPSEEK_MODEL,
        "gemini": settings.GEMINI_MODEL,
        "groq": settings.GROQ_MODEL,
    }
    return models.get(_provider(), "unknown")


def _build_deepseek() -> BaseChatModel:
    from langchain_openai import ChatOpenAI

    return ChatOpenAI(
        model=settings.DEEPSEEK_MODEL,
        api_key=settings.DEEPSEEK_API_KEY,
        base_url=settings.DEEPSEEK_BASE_URL,
        temperature=settings.AI_TEMPERATURE,
    )


def _build_gemini() -> BaseChatModel:
    from langchain_google_genai import ChatGoogleGenerativeAI

    return ChatGoogleGenerativeAI(
        model=settings.GEMINI_MODEL,
        google_api_key=settings.GEMINI_API_KEY,
        temperature=settings.AI_TEMPERATURE,
    )


def _build_groq() -> BaseChatModel:
    from langchain_openai import ChatOpenAI

    return ChatOpenAI(
        model=settings.GROQ_MODEL,
        api_key=settings.GROQ_API_KEY,
        base_url=settings.GROQ_BASE_URL,
        temperature=settings.AI_TEMPERATURE,
    )


_BUILDERS = {
    "deepseek": _build_deepseek,
    "gemini": _build_gemini,
    "groq": _build_groq,
}


@lru_cache
def create_chat_model() -> BaseChatModel:
    provider = _provider()
    if provider not in _BUILDERS:
        options = ", ".join(SUPPORTED_PROVIDERS)
        raise ValueError(f"Desteklenmeyen AI_PROVIDER: {provider!r}. Seçenekler: {options}")

    if not _api_key_for(provider):
        raise RuntimeError(f"{provider} API anahtarı tanımlı değil")

    return _BUILDERS[provider]()
