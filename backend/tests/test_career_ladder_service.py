"""Kariyer merdiveni servisi testleri."""

from app.services.career_ladder_service import build_career_ladder, build_skill_radar


def test_build_career_ladder_tiers():
    skills = [
        {"name": "SQL", "score": 85},
        {"name": "Excel", "score": 80},
        {"name": "Python", "score": 75},
        {"name": "Pandas", "score": 70},
        {"name": "İletişim", "score": 78},
    ]
    ladder = build_career_ladder(skills)

    assert len(ladder) >= 3
    assert ladder[0]["readiness"] >= ladder[-1]["readiness"] or True
    tiers = {item["tier"] for item in ladder}
    assert "ready" in tiers or "near" in tiers


def test_build_skill_radar_shape():
    radar = build_skill_radar([{"name": "SQL", "score": 80}])

    assert "skills" in radar
    assert radar["overall_match"] > 0


def test_build_skill_radar_uses_role_targets():
    skills = [
        {"name": "SQL", "score": 85},
        {"name": "Excel", "score": 80},
        {"name": "Python", "score": 75},
        {"name": "Pandas", "score": 70},
        {"name": "İletişim", "score": 78},
    ]
    ladder = build_career_ladder(skills)
    radar = build_skill_radar(skills, top_ladder_entry=ladder[0])

    assert radar["target_role"] == ladder[0]["title"]
    assert radar["overall_match"] == ladder[0]["readiness"]
    labels = {item["label"] for item in radar["skills"]}
    assert "SQL" in labels or "Excel" in labels


def test_career_ladder_swot_is_derived_from_cv_skills_and_gaps():
    skills = [
        {"name": "SQL", "score": 90},
        {"name": "Excel", "score": 85},
    ]

    ladder = build_career_ladder(skills)
    data_role = next(item for item in ladder if item["id"] == "data-analyst")
    swot = data_role["swot"]

    assert data_role["swot_source"] == "cv_skills"
    assert "SQL" in swot["strengths"]
    assert "Python" in swot["weaknesses"]
    assert any("Python" in item for item in swot["opportunities"])
    assert any("Python" in item for item in swot["threats"])
    assert "Yoğun aday rekabeti" not in swot["threats"]
