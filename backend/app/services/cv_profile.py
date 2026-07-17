"""CV metninden gerçek AI ile yetenek profili çıkarımı."""

from __future__ import annotations

import json
import re
from typing import Any

from langchain_core.messages import HumanMessage, SystemMessage
from pydantic import BaseModel, ConfigDict, Field, ValidationError

from app.services.ai_factory import (
    AIOutputError,
    AIProviderError,
    AIUnavailableError,
    ai_configured,
    create_chat_model,
)

_SKILL_LEVEL_SCORES = {
    "ileri": 90,
    "orta": 70,
    "temel": 50,
    "başlangıç": 35,
    "baslangic": 35,
}


class CVSkill(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)

    name: str = Field(min_length=1, max_length=120)
    score: int = Field(ge=0, le=100)


class CVAIOutput(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)

    summary: str = Field(default="", max_length=1000)
    skills: list[CVSkill] = Field(default_factory=list, max_length=20)


def _normalize_skill_name(name: str) -> str:
    return re.sub(r"\s+", " ", name.strip().lower())


def extract_profile_from_text(cv_text: str) -> dict[str, Any]:
    """CV metninden yetenek listesi ve kısa özet döner."""
    if not cv_text.strip():
        raise ValueError("CV metni boş")

    if not ai_configured():
        raise AIUnavailableError("AI sağlayıcısı yapılandırılmamış")

    prompt = (
        "Aşağıdaki CV metninden yetenekleri çıkar. Yalnızca geçerli JSON döndür, markdown yok.\n"
        'Şema: {"summary":"...", "skills":[{"name":"...", "score":0-100}]}\n'
        "score: o yetenekteki güç (0-100). En fazla 15 yetenek.\n\n"
        f"CV:\n{cv_text[:12000]}"
    )
    try:
        response = create_chat_model().invoke([
            SystemMessage(content="Sen bir CV analiz asistanısın. Yalnızca JSON üret."),
            HumanMessage(content=prompt),
        ])
        content = response.content
        raw = "".join(str(item) for item in content) if isinstance(content, list) else str(content or "")
        payload = json.loads(raw)
        parsed = CVAIOutput.model_validate(payload)
        if not parsed.skills:
            raise ValueError("Boş skills")

        return {
            "summary": parsed.summary,
            "skills": parsed.model_dump(mode="json")["skills"],
            "source": "ai",
        }
    except (json.JSONDecodeError, ValidationError, ValueError, TypeError, KeyError) as exc:
        raise AIOutputError("AI yanıtı beklenen JSON şemasına uymuyor") from exc
    except (AIUnavailableError, AIOutputError):
        raise
    except Exception as exc:
        raise AIProviderError("AI sağlayıcısından yanıt alınamadı") from exc
