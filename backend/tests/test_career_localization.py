from datetime import datetime, timezone

import pytest

from app.models.career_engine import CareerAnalysis, CareerTask, CareerTarget, Evidence
from app.models.user import User
from app.schemas.career import (
    CareerAnalysisAI,
    CareerAnalysisLocalizationsAI,
    CareerPlanAI,
    CareerPlanLocalizationsAI,
)
from app.services import career_engine
from app.services.ai_factory import AIOutputError, AIProviderError


def register_and_headers(client, email="locale@example.com"):
    client.post(
        "/api/v1/auth/register",
        json={"full_name": "Locale User", "email": email, "password": "GucluParola123!"},
    )
    token = client.post(
        "/api/v1/auth/login",
        data={"username": email, "password": "GucluParola123!"},
    ).json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


def db_session():
    app = __import__("app.main", fromlist=["app"]).app
    get_db = __import__("app.core.database", fromlist=["get_db"]).get_db
    return next(app.dependency_overrides[get_db]())


def analysis_payload():
    return {
        "current_role": "Veri Analisti",
        "profile": {"seniority": "Orta seviye"},
        "skills": [{"name": "SQL", "score": 80}],
        "radar": [{"label": "İletişim", "score": 70, "target": 85}],
        "roles": [
            {
                "tier": "A",
                "title": "Veri Analisti",
                "readiness": 80,
                "swot": {
                    "strengths": ["Güçlü SQL"],
                    "weaknesses": ["Sunum pratiği"],
                    "opportunities": ["Fintech büyümesi"],
                    "threats": ["Yoğun rekabet"],
                },
            },
            {
                "tier": "B",
                "title": "BI Analisti",
                "readiness": 60,
                "swot": {"strengths": ["SQL"], "weaknesses": ["Power BI"], "opportunities": [], "threats": []},
            },
            {
                "tier": "C",
                "title": "Veri Bilimci",
                "readiness": 35,
                "swot": {"strengths": [], "weaknesses": ["Machine Learning"], "opportunities": [], "threats": []},
            },
        ],
    }


def analysis_localizations_payload():
    tr_roles = [
        {"title": "Veri Analisti", "strengths": ["Güçlü SQL"], "weaknesses": ["Sunum pratiği"], "opportunities": ["Fintech büyümesi"], "threats": ["Yoğun rekabet"]},
        {"title": "BI Analisti", "strengths": ["SQL"], "weaknesses": ["Power BI"], "opportunities": [], "threats": []},
        {"title": "Veri Bilimci", "strengths": [], "weaknesses": ["Machine Learning"], "opportunities": [], "threats": []},
    ]
    en_roles = [
        {"title": "Data Analyst", "strengths": ["Strong SQL"], "weaknesses": ["Presentation practice"], "opportunities": ["Fintech growth"], "threats": ["Strong competition"]},
        {"title": "BI Analyst", "strengths": ["SQL"], "weaknesses": ["Power BI"], "opportunities": [], "threats": []},
        {"title": "Data Scientist", "strengths": [], "weaknesses": ["Machine Learning"], "opportunities": [], "threats": []},
    ]
    return {
        "tr": {
            "current_role": "Veri Analisti",
            "profile": {"seniority": "Orta seviye"},
            "skill_names": ["SQL"],
            "radar_labels": ["İletişim"],
            "roles": tr_roles,
        },
        "en": {
            "current_role": "Data Analyst",
            "profile": {"seniority": "Mid-level"},
            "skill_names": ["SQL"],
            "radar_labels": ["Communication"],
            "roles": en_roles,
        },
    }


def test_user_preferred_locale_is_persisted_and_returned(client):
    auth = register_and_headers(client)

    assert client.get("/api/v1/auth/me", headers=auth).json()["preferred_locale"] == "tr"
    changed = client.patch(
        "/api/v1/auth/me/locale",
        headers=auth,
        json={"preferred_locale": "en"},
    )

    assert changed.status_code == 200
    assert changed.json()["preferred_locale"] == "en"
    assert client.get("/api/v1/auth/me", headers=auth).json()["preferred_locale"] == "en"
    assert client.patch(
        "/api/v1/auth/me/locale",
        headers=auth,
        json={"preferred_locale": "de"},
    ).status_code == 422


def test_new_analysis_stores_both_panel_languages_independent_of_cv_language(client, monkeypatch):
    register_and_headers(client)
    db = db_session()
    row = CareerAnalysis(
        id="localized-analysis",
        user_id=1,
        status="queued",
        source="upload",
        cv_text="Experienced data analyst with SQL and dashboard projects.",
    )
    db.add(row)
    db.commit()
    prompts = []

    def fake_invoke(prompt, schema):
        prompts.append(prompt)
        if schema is CareerAnalysisAI:
            return CareerAnalysisAI.model_validate(analysis_payload())
        if schema is CareerAnalysisLocalizationsAI:
            return CareerAnalysisLocalizationsAI.model_validate(analysis_localizations_payload())
        raise AssertionError(schema)

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)
    career_engine.analyze_row(db, row)

    assert row.status == "ready"
    assert set(row.localizations) == {"tr", "en"}
    assert career_engine.serialize_analysis(row, "tr")["career_ladder"][0]["title"] == "Veri Analisti"
    english = career_engine.serialize_analysis(row, "en")
    assert english["current_role"] == "Data Analyst"
    assert english["radar"][0] == {"label": "Communication", "score": 70, "target": 85}
    assert english["career_ladder"][0]["swot"]["strengths"] == ["Strong SQL"]
    assert "CV'nin dili çıktı dilini belirlemez" in prompts[0]
    assert "Python, SQL, AWS" in prompts[1]
    db.close()


def test_new_analysis_releases_database_transaction_during_ai_calls(client, monkeypatch):
    register_and_headers(client)
    db = db_session()
    row = CareerAnalysis(
        id="transaction-safe-analysis",
        user_id=1,
        status="queued",
        source="upload",
        cv_text="Experienced data analyst with SQL and dashboard projects.",
    )
    db.add(row)
    db.commit()

    def fake_invoke(_prompt, schema):
        assert db.in_transaction() is False
        if schema is CareerAnalysisAI:
            return CareerAnalysisAI.model_validate(analysis_payload())
        if schema is CareerAnalysisLocalizationsAI:
            return CareerAnalysisLocalizationsAI.model_validate(analysis_localizations_payload())
        raise AssertionError(schema)

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)

    result = career_engine.analyze_row(db, row)

    assert result.status == "ready"
    db.close()


def test_new_plan_localizes_task_text_but_preserves_external_course_title(client, monkeypatch):
    register_and_headers(client)
    db = db_session()
    analysis = CareerAnalysis(
        id="plan-analysis",
        user_id=1,
        status="ready",
        source="upload",
        cv_text="Data analyst",
        current_role="Veri Analisti",
        profile={},
        skills=[{"name": "SQL", "score": 80}],
        radar=[],
        career_ladder=[],
        localizations={
            "tr": {"current_role": "Veri Analisti", "profile": {}, "skills": [{"name": "SQL", "score": 80}], "radar": [], "career_ladder": []},
            "en": {"current_role": "Data Analyst", "profile": {}, "skills": [{"name": "SQL", "score": 80}], "radar": [], "career_ladder": []},
        },
    )
    target = CareerTarget(id="localized-target", user_id=1, title="Data Engineer", source="custom", status="queued")
    db.add_all([analysis, target])
    db.commit()

    def fake_invoke(_prompt, schema):
        if schema is CareerPlanAI:
            return CareerPlanAI.model_validate({
                "target_title": "Veri Mühendisi",
                "tasks": [{
                    "title": "Bir veri hattı geliştir",
                    "hint": "Python ve SQL kullan",
                    "evidence_required": True,
                    "evidence_types": ["link"],
                    "skill_impacts": ["Veri modelleme"],
                    "training_queries": [{"query": "data engineering course", "skill": "Data Engineering", "reason": "Beceri açığı"}],
                }],
            })
        if schema is CareerPlanLocalizationsAI:
            return CareerPlanLocalizationsAI.model_validate({
                "tr": {"target_title": "Veri Mühendisi", "tasks": [{"id": "0", "title": "Bir veri hattı geliştir", "hint": "Python ve SQL kullan", "skill_impacts": ["Veri modelleme"], "feedback": None}]},
                "en": {"target_title": "Data Engineer", "tasks": [{"id": "0", "title": "Build a data pipeline", "hint": "Use Python and SQL", "skill_impacts": ["Data modeling"], "feedback": None}]},
            })
        raise AssertionError(schema)

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)
    monkeypatch.setattr(career_engine, "search_training", lambda *_args: [{
        "catalog_id": "course-1",
        "title": "AWS Data Engineering Professional Certificate",
        "provider": "Coursera",
        "url": "https://example.com/course",
        "skills": ["AWS"],
    }])

    career_engine.plan_target(db, target)
    task = db.query(CareerTask).filter_by(target_id=target.id).one()

    assert career_engine.serialize_target(target, "en")["title"] == "Data Engineer"
    assert career_engine.serialize_target(target, "en")["plan"]["tasks"][0]["title"] == "Build a data pipeline"
    english = career_engine.serialize_task(task, db, "en")
    assert english["title"] == "Build a data pipeline"
    assert english["hint"] == "Use Python and SQL"
    assert english["training_suggestions"][0]["title"] == "AWS Data Engineering Professional Certificate"
    db.close()


def test_new_plan_releases_database_transaction_during_ai_and_training_calls(client, monkeypatch):
    register_and_headers(client)
    db = db_session()
    analysis = CareerAnalysis(
        id="transaction-safe-plan-analysis",
        user_id=1,
        status="ready",
        source="upload",
        current_role="Veri Analisti",
        profile={},
        skills=[{"name": "SQL", "score": 80}],
        radar=[],
        career_ladder=[],
        localizations={
            "tr": {"current_role": "Veri Analisti", "profile": {}, "skills": [{"name": "SQL", "score": 80}], "radar": [], "career_ladder": []},
            "en": {"current_role": "Data Analyst", "profile": {}, "skills": [{"name": "SQL", "score": 80}], "radar": [], "career_ladder": []},
        },
    )
    target = CareerTarget(id="transaction-safe-target", user_id=1, title="Data Engineer", source="custom", status="queued")
    db.add_all([analysis, target])
    db.commit()

    def fake_invoke(_prompt, schema):
        assert db.in_transaction() is False
        if schema is CareerPlanAI:
            return CareerPlanAI.model_validate({
                "target_title": "Veri Mühendisi",
                "tasks": [{
                    "title": "Bir veri hattı geliştir",
                    "hint": "Python ve SQL kullan",
                    "evidence_required": True,
                    "evidence_types": ["link"],
                    "skill_impacts": ["Veri modelleme"],
                    "training_queries": [{"query": "data engineering course", "skill": "Data Engineering", "reason": "Beceri açığı"}],
                }],
            })
        if schema is CareerPlanLocalizationsAI:
            return CareerPlanLocalizationsAI.model_validate({
                "tr": {"target_title": "Veri Mühendisi", "tasks": [{"id": "0", "title": "Bir veri hattı geliştir", "hint": "Python ve SQL kullan", "skill_impacts": ["Veri modelleme"], "feedback": None}]},
                "en": {"target_title": "Data Engineer", "tasks": [{"id": "0", "title": "Build a data pipeline", "hint": "Use Python and SQL", "skill_impacts": ["Data modeling"], "feedback": None}]},
            })
        raise AssertionError(schema)

    def fake_search(*_args):
        assert db.in_transaction() is False
        return []

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)
    monkeypatch.setattr(career_engine, "search_training", fake_search)

    result = career_engine.plan_target(db, target)

    assert result.status == "active"
    db.close()


def test_skill_evidence_task_is_created_in_both_panel_languages(client):
    register_and_headers(client)
    db = db_session()
    target = CareerTarget(id="skill-target", user_id=1, title="Data Engineer", source="custom", status="active")
    db.add(target)
    db.commit()

    task = career_engine.ensure_skill_evidence_task(db, 1, target.id, "AWS")

    assert career_engine.serialize_task(task, db, "tr")["title"] == "AWS kanıtı"
    assert career_engine.serialize_task(task, db, "en")["title"] == "AWS evidence"
    assert "GitHub" in career_engine.serialize_task(task, db, "en")["hint"]
    db.close()


def test_legacy_content_is_backfilled_once_and_cached_for_both_languages(client, monkeypatch):
    auth = register_and_headers(client)
    db = db_session()
    db.add(CareerAnalysis(
        id="legacy-analysis",
        user_id=1,
        status="ready",
        source="upload",
        cv_text="English CV",
        current_role="Data Analyst",
        profile={"seniority": "Mid-level"},
        skills=[{"name": "SQL", "score": 80}],
        radar=[{"label": "Communication", "score": 70, "target": 85}],
        career_ladder=analysis_payload()["roles"],
        localizations={},
    ))
    db.commit()
    calls = []

    def fake_invoke(_prompt, schema):
        calls.append(schema)
        return CareerAnalysisLocalizationsAI.model_validate(analysis_localizations_payload())

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)

    first = client.get("/api/v1/career/analysis/current", headers=auth)
    second = client.get("/api/v1/career/analysis/current", headers=auth)
    assert first.status_code == 200
    assert first.json()["current_role"] == "Veri Analisti"
    assert second.json()["current_role"] == "Veri Analisti"
    assert calls == [CareerAnalysisLocalizationsAI]

    client.patch("/api/v1/auth/me/locale", headers=auth, json={"preferred_locale": "en"})
    english = client.get("/api/v1/career/analysis/current", headers=auth)
    assert english.json()["current_role"] == "Data Analyst"
    assert calls == [CareerAnalysisLocalizationsAI]
    db.close()


def test_legacy_backfill_releases_database_transaction_during_ai_call(client, monkeypatch):
    register_and_headers(client)
    db = db_session()
    db.add(CareerAnalysis(
        id="transaction-safe-legacy-analysis",
        user_id=1,
        status="ready",
        source="upload",
        cv_text="English CV",
        current_role="Data Analyst",
        profile={"seniority": "Mid-level"},
        skills=[{"name": "SQL", "score": 80}],
        radar=[{"label": "Communication", "score": 70, "target": 85}],
        career_ladder=analysis_payload()["roles"],
        localizations={},
    ))
    db.commit()

    def fake_invoke(_prompt, schema):
        assert db.in_transaction() is False
        return CareerAnalysisLocalizationsAI.model_validate(analysis_localizations_payload())

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)

    career_engine.ensure_career_localizations(db, 1, include_targets=False)

    saved = db.get(CareerAnalysis, "transaction-safe-legacy-analysis")
    assert set(saved.localizations) == {"tr", "en"}
    db.close()


def test_legacy_plan_is_backfilled_once_and_keeps_course_titles_original(client, monkeypatch):
    auth = register_and_headers(client)
    db = db_session()
    db.add(CareerAnalysis(
        id="legacy-plan-analysis",
        user_id=1,
        status="ready",
        source="upload",
        cv_text="English CV",
        current_role="Data Analyst",
        profile={},
        skills=[],
        radar=[],
        career_ladder=[],
        localizations={
            "tr": {"current_role": "Veri Analisti", "profile": {}, "skills": [], "radar": [], "career_ladder": []},
            "en": {"current_role": "Data Analyst", "profile": {}, "skills": [], "radar": [], "career_ladder": []},
        },
    ))
    target = CareerTarget(
        id="legacy-plan-target",
        user_id=1,
        title="Data Engineer",
        source="custom",
        status="active",
        plan={"tasks": [{"id": "legacy-plan-task", "title": "Build a data pipeline"}]},
        localizations={},
    )
    task = CareerTask(
        id="legacy-plan-task",
        user_id=1,
        target_id=target.id,
        title="Build a data pipeline",
        hint="Use Python and SQL",
        status="pending",
        evidence_types=["link"],
        skill_impacts=["Data modeling"],
        training_suggestions=[{
            "catalog_id": "course-1",
            "title": "AWS Data Engineering Professional Certificate",
            "provider": "Coursera",
            "url": "https://example.com/course",
        }],
        localizations={},
    )
    db.add_all([target, task])
    db.commit()
    calls = []

    def fake_invoke(_prompt, schema):
        calls.append(schema)
        return CareerPlanLocalizationsAI.model_validate({
            "tr": {"target_title": "Veri Mühendisi", "tasks": [{"id": task.id, "title": "Bir veri hattı geliştir", "hint": "Python ve SQL kullan", "skill_impacts": ["Veri modelleme"], "feedback": None}]},
            "en": {"target_title": "Data Engineer", "tasks": [{"id": task.id, "title": "Build a data pipeline", "hint": "Use Python and SQL", "skill_impacts": ["Data modeling"], "feedback": None}]},
        })

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)

    turkish = client.get(f"/api/v1/career/targets/{target.id}/tasks", headers=auth)
    assert turkish.status_code == 200
    assert turkish.json()[0]["title"] == "Bir veri hattı geliştir"
    assert turkish.json()[0]["training_suggestions"][0]["title"] == "AWS Data Engineering Professional Certificate"
    assert calls == [CareerPlanLocalizationsAI]

    assert client.patch(
        "/api/v1/auth/me/locale",
        headers=auth,
        json={"preferred_locale": "en"},
    ).status_code == 200
    english_target = client.get(f"/api/v1/career/targets/{target.id}", headers=auth).json()
    english_task = client.get(f"/api/v1/career/targets/{target.id}/tasks", headers=auth).json()[0]
    assert english_target["title"] == "Data Engineer"
    assert english_target["plan"]["tasks"][0]["title"] == "Build a data pipeline"
    assert english_task["title"] == "Build a data pipeline"
    assert calls == [CareerPlanLocalizationsAI]
    db.close()


def test_localization_failure_returns_explicit_error_instead_of_wrong_language(client, monkeypatch):
    auth = register_and_headers(client)
    db = db_session()
    db.add(CareerAnalysis(
        id="failed-localization",
        user_id=1,
        status="ready",
        source="upload",
        cv_text="English CV",
        current_role="Data Analyst",
        profile={},
        skills=[],
        radar=[],
        career_ladder=[],
        localizations={},
    ))
    db.commit()
    db.close()
    monkeypatch.setattr(career_engine, "_invoke", lambda *_args: (_ for _ in ()).throw(AIProviderError("translation down")))

    response = client.patch(
        "/api/v1/auth/me/locale",
        headers=auth,
        json={"preferred_locale": "en"},
    )

    assert response.status_code == 503
    assert response.json()["detail"]["code"] == "career_localization_failed"
    assert client.get("/api/v1/auth/me", headers=auth).json()["preferred_locale"] == "tr"
    assert client.get("/api/v1/career/analysis/current", headers=auth).status_code == 503


def test_archived_analysis_is_localized_lazily_by_its_own_endpoint(client, monkeypatch):
    auth = register_and_headers(client)
    db = db_session()
    db.add_all([
        CareerAnalysis(
            id="legacy-archived-analysis",
            user_id=1,
            status="ready",
            source="upload",
            cv_text="English CV",
            current_role="Data Analyst",
            profile={"seniority": "Mid-level"},
            skills=[{"name": "SQL", "score": 80}],
            radar=[{"label": "Communication", "score": 70, "target": 85}],
            career_ladder=analysis_payload()["roles"],
            localizations={},
            created_at=datetime(2026, 1, 1, tzinfo=timezone.utc),
        ),
        CareerAnalysis(
            id="latest-localized-analysis",
            user_id=1,
            status="ready",
            source="upload",
            cv_text="Current CV",
            current_role="Veri Analisti",
            profile={},
            skills=[],
            radar=[],
            career_ladder=[],
            localizations={
                "tr": {"current_role": "Veri Analisti", "profile": {}, "skills": [], "radar": [], "career_ladder": []},
                "en": {"current_role": "Data Analyst", "profile": {}, "skills": [], "radar": [], "career_ladder": []},
            },
            created_at=datetime(2026, 2, 1, tzinfo=timezone.utc),
        ),
    ])
    db.commit()
    db.close()
    calls = []

    def fake_invoke(_prompt, schema):
        calls.append(schema)
        return CareerAnalysisLocalizationsAI.model_validate(analysis_localizations_payload())

    monkeypatch.setattr(career_engine, "_invoke", fake_invoke)

    response = client.get("/api/v1/career/analysis/legacy-archived-analysis", headers=auth)

    assert response.status_code == 200
    assert response.json()["current_role"] == "Veri Analisti"
    assert calls == [CareerAnalysisLocalizationsAI]


def test_localized_swot_keeps_source_item_counts():
    localized = analysis_localizations_payload()
    localized["en"]["roles"][0]["strengths"] = []

    with pytest.raises(AIOutputError, match="SWOT"):
        career_engine._build_analysis_localizations(
            {
                "current_role": analysis_payload()["current_role"],
                "profile": analysis_payload()["profile"],
                "skills": analysis_payload()["skills"],
                "radar": analysis_payload()["radar"],
                "roles": analysis_payload()["roles"],
            },
            CareerAnalysisLocalizationsAI.model_validate(localized),
        )


def test_skill_task_matches_localized_impact_without_creating_duplicate(client):
    register_and_headers(client)
    db = db_session()
    target = CareerTarget(
        id="localized-skill-target",
        user_id=1,
        title="İletişim Uzmanı",
        source="custom",
        status="active",
    )
    task = CareerTask(
        id="localized-skill-task",
        user_id=1,
        target_id=target.id,
        title="İletişim pratiği yap",
        hint="Bir sunum hazırla",
        status="pending",
        evidence_types=["link"],
        skill_impacts=["İletişim"],
        localizations={
            "tr": {"title": "İletişim pratiği yap", "hint": "Bir sunum hazırla", "skill_impacts": ["İletişim"], "feedback": None},
            "en": {"title": "Practice communication", "hint": "Prepare a presentation", "skill_impacts": ["Communication"], "feedback": None},
        },
    )
    db.add_all([target, task])
    db.commit()

    matched = career_engine.ensure_skill_evidence_task(db, 1, target.id, "Communication")

    assert matched.id == task.id
    assert db.query(CareerTask).filter_by(target_id=target.id).count() == 1
    db.close()


def test_new_skill_evidence_task_uses_analysis_skill_names_in_both_languages(client):
    register_and_headers(client)
    db = db_session()
    analysis = CareerAnalysis(
        id="skill-name-analysis",
        user_id=1,
        status="ready",
        source="upload",
        current_role="Veri Analisti",
        profile={},
        skills=[],
        radar=[{"label": "İletişim", "score": 70, "target": 85}],
        career_ladder=[],
        localizations={
            "tr": {"current_role": "Veri Analisti", "profile": {}, "skills": [], "radar": [{"label": "İletişim", "score": 70, "target": 85}], "career_ladder": []},
            "en": {"current_role": "Data Analyst", "profile": {}, "skills": [], "radar": [{"label": "Communication", "score": 70, "target": 85}], "career_ladder": []},
        },
    )
    target = CareerTarget(id="new-skill-target", user_id=1, title="Veri Analisti", source="custom", status="active")
    db.add_all([analysis, target])
    db.commit()

    task = career_engine.ensure_skill_evidence_task(db, 1, target.id, "Communication")

    assert career_engine.serialize_task(task, db, "tr")["title"] == "İletişim kanıtı"
    assert career_engine.serialize_task(task, db, "en")["title"] == "Communication evidence"
    db.close()


def test_task_mutation_and_evidence_feedback_follow_database_locale(client):
    auth = register_and_headers(client)
    db = db_session()
    user = db.get(User, 1)
    user.preferred_locale = "en"
    target = CareerTarget(
        id="mutation-locale-target",
        user_id=1,
        title="Veri Mühendisi",
        source="custom",
        status="active",
        localizations={
            "tr": {"title": "Veri Mühendisi", "task_titles": {"mutation-locale-task": "Bir veri hattı geliştir"}},
            "en": {"title": "Data Engineer", "task_titles": {"mutation-locale-task": "Build a data pipeline"}},
        },
    )
    task = CareerTask(
        id="mutation-locale-task",
        user_id=1,
        target_id=target.id,
        title="Bir veri hattı geliştir",
        hint="Python ve SQL kullan",
        status="pending",
        evidence_types=["link"],
        skill_impacts=["Veri modelleme"],
        feedback="Tekrar dene",
        localizations={
            "tr": {"title": "Bir veri hattı geliştir", "hint": "Python ve SQL kullan", "skill_impacts": ["Veri modelleme"], "feedback": "Tekrar dene"},
            "en": {"title": "Build a data pipeline", "hint": "Use Python and SQL", "skill_impacts": ["Data modeling"], "feedback": "Try again"},
        },
    )
    evidence = Evidence(
        id="localized-evidence",
        user_id=1,
        task_id=task.id,
        kind="link",
        url="https://example.com/proof",
        status="revision_required",
        feedback="Tekrar dene",
    )
    pending_evidence = Evidence(
        id="pending-localized-evidence",
        user_id=1,
        task_id=task.id,
        kind="link",
        url="https://example.com/new-proof",
        status="pending",
    )
    db.add_all([target, task, evidence, pending_evidence])
    db.commit()
    db.close()

    updated = client.patch(
        "/api/v1/career/tasks/mutation-locale-task",
        headers=auth,
        json={"status": "completed"},
    )
    evidence_response = client.get("/api/v1/career/evidence/localized-evidence", headers=auth)
    pending_response = client.get("/api/v1/career/evidence/pending-localized-evidence", headers=auth)

    assert updated.status_code == 200
    assert updated.json()["title"] == "Build a data pipeline"
    assert evidence_response.status_code == 200
    assert evidence_response.json()["feedback"] == "Try again"
    assert pending_response.status_code == 200
    assert pending_response.json()["feedback"] is None
