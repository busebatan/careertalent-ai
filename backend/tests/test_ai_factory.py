"""AI factory testleri."""

import pytest

from app.services import ai_factory


@pytest.fixture(autouse=True)
def clear_model_cache():
    ai_factory.create_chat_model.cache_clear()
    yield
    ai_factory.create_chat_model.cache_clear()


def test_ai_configured_deepseek(monkeypatch):
    monkeypatch.setattr(ai_factory.settings, "AI_PROVIDER", "deepseek")
    monkeypatch.setattr(ai_factory.settings, "DEEPSEEK_API_KEY", "sk-test")
    assert ai_factory.ai_configured() is True


def test_ai_configured_gemini(monkeypatch):
    monkeypatch.setattr(ai_factory.settings, "AI_PROVIDER", "gemini")
    monkeypatch.setattr(ai_factory.settings, "GEMINI_API_KEY", "gem-test")
    assert ai_factory.ai_configured() is True


def test_ai_configured_groq(monkeypatch):
    monkeypatch.setattr(ai_factory.settings, "AI_PROVIDER", "groq")
    monkeypatch.setattr(ai_factory.settings, "GROQ_API_KEY", "gsk-test")
    assert ai_factory.ai_configured() is True


def test_ai_not_configured_without_key(monkeypatch):
    monkeypatch.setattr(ai_factory.settings, "AI_PROVIDER", "deepseek")
    monkeypatch.setattr(ai_factory.settings, "DEEPSEEK_API_KEY", "")
    assert ai_factory.ai_configured() is False


def test_unknown_provider_raises(monkeypatch):
    monkeypatch.setattr(ai_factory.settings, "AI_PROVIDER", "openai")
    monkeypatch.setattr(ai_factory.settings, "DEEPSEEK_API_KEY", "sk-test")
    with pytest.raises(ValueError, match="Desteklenmeyen AI_PROVIDER"):
        ai_factory.create_chat_model()


def test_missing_key_raises(monkeypatch):
    monkeypatch.setattr(ai_factory.settings, "AI_PROVIDER", "gemini")
    monkeypatch.setattr(ai_factory.settings, "GEMINI_API_KEY", "")
    with pytest.raises(RuntimeError, match="gemini API anahtarı"):
        ai_factory.create_chat_model()


def test_get_active_model_name(monkeypatch):
    monkeypatch.setattr(ai_factory.settings, "AI_PROVIDER", "groq")
    monkeypatch.setattr(ai_factory.settings, "GROQ_MODEL", "llama-test")
    assert ai_factory.get_active_model_name() == "llama-test"
