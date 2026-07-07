"""CV yetenekleri → kariyer merdiveni (A/B/C) skoru."""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from app.services.roles_catalog import load_roles

_TIER_LABELS = {
    "ready": "A — Hazır",
    "near": "B — Yakın",
    "reachable": "C — Ulaşılabilir",
}

_LEVEL_SCORES = {
    "ileri": 90,
    "orta": 70,
    "temel": 50,
}


def _skill_map(skills: list[dict[str, Any]]) -> dict[str, int]:
    mapped: dict[str, int] = {}
    for item in skills:
        name = str(item.get("name", "")).strip().lower()
        if not name:
            continue
        mapped[name] = int(item.get("score", 0))
    return mapped


def _match_score(user_skills: dict[str, int], required: dict[str, Any]) -> tuple[int, list[str], list[str]]:
    name = str(required.get("name", "")).strip()
    key = name.lower()
    target = _LEVEL_SCORES.get(str(required.get("level", "temel")).lower(), 50)
    user_score = user_skills.get(key, 0)

    # Kısmi eşleşme: alt string
    if user_score == 0:
        for skill_name, score in user_skills.items():
            if key in skill_name or skill_name in key:
                user_score = max(user_score, score)

    met = user_score >= target
    return (100 if met else max(0, int((user_score / max(target, 1)) * 100)), name, met)


def _tier_for_readiness(readiness: int) -> str:
    if readiness >= 70:
        return "ready"
    if readiness >= 40:
        return "near"
    return "reachable"


def _weeks_estimate(tier: str, gap_count: int) -> str | None:
    if tier == "ready":
        return None
    if tier == "near":
        return "4–8 hafta"
    return "~6 ay" if gap_count > 8 else "3–6 ay"


def _cv_based_opportunities(role_title: str, strengths: list[str], weaknesses: list[str]) -> list[str]:
    if weaknesses:
        return [
            f"CV'deki {', '.join(strengths[:2]) or 'mevcut'} güçlü yönünü {weaknesses[0]} eksiğiyle tamamlayınca {role_title} için uyum artar",
            f"{weaknesses[0]} kanıtı eklenirse CV bu role daha net hizalanır",
        ]

    return [
        f"CV'deki {', '.join(strengths[:2]) or 'mevcut'} kanıtlar {role_title} rolüne başvuru için kullanılabilir",
        "Mevcut güçlü yetenekleri proje/sertifika linkleriyle görünür yapmak uyumu artırır",
    ]


def _cv_based_threats(weaknesses: list[str], readiness: int) -> list[str]:
    if weaknesses:
        return [
            f"CV'de {weaknesses[0]} kanıtı eksik kalırsa kısa listeye girme riski artar",
            f"Eksik görünen alanlar: {', '.join(weaknesses[:3])}",
        ]

    if readiness < 85:
        return ["CV'de güçlü sinyal var; yine de proje etkisi ve ölçülebilir sonuçlar net yazılmazsa uyum düşük algılanabilir"]

    return ["Belirgin yetenek eksiği yok; risk, CV kanıtlarının başvuru metninde yeterince görünür olmaması"]


def build_career_ladder(cv_skills: list[dict[str, Any]]) -> list[dict[str, Any]]:
    user_skills = _skill_map(cv_skills)
    ladder: list[dict[str, Any]] = []

    for role in load_roles():
        required_skills = role.get("required_skills", [])
        if not required_skills:
            continue

        scores: list[int] = []
        strengths: list[str] = []
        weaknesses: list[str] = []

        for req in required_skills:
            pct, name, met = _match_score(user_skills, req)
            scores.append(pct)
            if met:
                strengths.append(name)
            else:
                weaknesses.append(name)

        readiness = int(sum(scores) / len(scores)) if scores else 0
        tier = _tier_for_readiness(readiness)
        gap_count = len(weaknesses)
        gaps_summary = ", ".join(weaknesses[:4]) if weaknesses else "—"

        role_title = str(role.get("title", "Rol"))
        visible_strengths = strengths[:4] or ["CV'de ilgili sinyal zayıf"]
        visible_weaknesses = weaknesses[:4] or ["Belirgin eksik yok"]

        ladder.append({
            "id": role.get("id", ""),
            "tier": tier,
            "tier_label": _TIER_LABELS[tier],
            "title": role_title,
            "readiness": readiness,
            "gap_count": gap_count,
            "gaps_summary": gaps_summary,
            "weeks_estimate": _weeks_estimate(tier, gap_count),
            "swot": {
                "strengths": visible_strengths,
                "weaknesses": visible_weaknesses,
                "opportunities": _cv_based_opportunities(role_title, strengths, weaknesses),
                "threats": _cv_based_threats(weaknesses, readiness),
            },
            "swot_source": "cv_skills",
        })

    tier_order = {"ready": 0, "near": 1, "reachable": 2}
    ladder.sort(key=lambda item: (tier_order.get(item["tier"], 9), -item["readiness"]))

    return ladder[:6]


def _user_skill_score(user_skills: dict[str, int], skill_name: str) -> int:
    key = skill_name.strip().lower()
    user_score = user_skills.get(key, 0)
    if user_score > 0:
        return user_score

    for skill_key, score in user_skills.items():
        if key in skill_key or skill_key in key:
            user_score = max(user_score, score)

    return user_score


def _role_by_id(role_id: str) -> dict[str, Any] | None:
    for role in load_roles():
        if role.get("id") == role_id:
            return role
    return None


def build_skill_radar(
    cv_skills: list[dict[str, Any]],
    top_ladder_entry: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Panel skill radar şeması — hedef rol kataloğundan, skorlar CV'den."""
    user_skills = _skill_map(cv_skills)
    role = _role_by_id(str(top_ladder_entry.get("id", ""))) if top_ladder_entry else None

    skills_out: list[dict[str, Any]] = []
    seen: set[str] = set()

    if role:
        for req in role.get("required_skills", [])[:8]:
            name = str(req.get("name", "")).strip()
            if not name:
                continue
            target = _LEVEL_SCORES.get(str(req.get("level", "temel")).lower(), 50)
            user_score = _user_skill_score(user_skills, name)
            skills_out.append({
                "label": name,
                "score": user_score if user_score > 0 else 35,
                "target": target,
            })
            seen.add(_normalize_key(name))

    for item in sorted(cv_skills, key=lambda s: int(s.get("score", 0)), reverse=True):
        if len(skills_out) >= 8:
            break
        name = str(item.get("name", "")).strip()
        if not name or _normalize_key(name) in seen:
            continue
        score = int(item.get("score", 60))
        skills_out.append({
            "label": name,
            "score": score,
            "target": min(100, max(score + 10, 70)),
        })
        seen.add(_normalize_key(name))

    if not skills_out:
        skills_out = [{"label": "Genel uyum", "score": 40, "target": 70}]

    avg_score = int(sum(s["score"] for s in skills_out) / len(skills_out))
    overall = int(top_ladder_entry.get("readiness", avg_score)) if top_ladder_entry else avg_score
    target_role = (
        str(top_ladder_entry.get("title", "")).strip()
        if top_ladder_entry
        else (skills_out[0]["label"] if skills_out else "Hedef rol")
    )

    return {
        "overall_match": overall,
        "analyzed_at": datetime.now(timezone.utc).strftime("%d %b %Y"),
        "target_role": target_role or "Hedef rol",
        "skills": skills_out,
    }


def _normalize_key(name: str) -> str:
    return name.strip().lower()
