import json

import pytest
from sqlalchemy import select
from sqlalchemy.exc import IntegrityError

from app.core.database import get_db
from app.main import app
from app.models.career_engine import CareerAnalysis, JobOpportunity
from app.models.engagement import CareerInterview, CareerInterviewAnswer, CvDocument, JobApplication
from app.models.user import User
from app.schemas.engagement import ChatReplyAI, InterviewEvaluationAI, InterviewQuestionAI, InterviewQuestionsAI
from app.services.engagement import evaluate_interview_answer, start_interview


def register_and_headers(client, email="engagement@example.com"):
    client.post("/api/v1/auth/register", json={"full_name": "Gerçek Kullanıcı", "email": email, "password": "GucluParola123!"})
    token = client.post("/api/v1/auth/login", data={"username": email, "password": "GucluParola123!"}).json()["access_token"]
    return {"Authorization": f"Bearer {token}"}


def db_session():
    return next(app.dependency_overrides[get_db]())


def test_profile_is_account_backed_and_never_returns_demo_identity(client):
    auth = register_and_headers(client)
    empty = client.get("/api/v1/career/profile", headers=auth)
    assert empty.status_code == 200
    assert empty.json()["full_name"] == "Gerçek Kullanıcı"
    assert empty.json()["phone"] is None
    assert "Ayşe" not in str(empty.json())

    updated = client.put("/api/v1/career/profile", headers=auth, json={
        "full_name": "Yeni Kullanıcı", "phone": "+90 555 111 22 33", "location": "Ankara",
        "headline": "Ürün Tasarımcısı", "linkedin": "https://linkedin.com/in/yeni",
        "social_links": [{"platform": "Behance", "url": "https://behance.net/yeni"}],
    })
    assert updated.status_code == 200
    assert updated.json()["social_links"][0]["platform"] == "Behance"
    assert client.get("/api/v1/auth/me", headers=auth).json()["full_name"] == "Yeni Kullanıcı"


def test_chat_uses_ai_and_persists_user_and_assistant_messages(client, monkeypatch):
    auth = register_and_headers(client)
    monkeypatch.setattr("app.services.engagement._invoke", lambda _prompt, schema: ChatReplyAI(reply="Hedefine göre ilk SQL görevini tamamla.", suggested_actions=["Görevlere git"]))

    response = client.post("/api/v1/career/chat", headers=auth, json={"message": "Bugün ne yapmalıyım?"})
    assert response.status_code == 201
    assert response.json()["role"] == "assistant"
    assert response.json()["meta"]["suggested_actions"] == ["Görevlere git"]
    history = client.get("/api/v1/career/chat", headers=auth).json()
    assert [item["role"] for item in history] == ["user", "assistant"]
    assert history[0]["content"] == "Bugün ne yapmalıyım?"

    assert client.get("/api/v1/career/chat/threads", headers=auth).json() == {
        "items": [], "has_more": False,
    }

    new_chat = client.post("/api/v1/career/chat/threads", headers=auth)
    assert new_chat.status_code == 201
    assert client.get("/api/v1/career/chat", headers=auth).json() == []

    threads = client.get("/api/v1/career/chat/threads?limit=20&offset=0", headers=auth).json()
    assert threads["has_more"] is False
    assert len(threads["items"]) == 1
    assert threads["items"][0]["title"] == "Bugün ne yapmalıyım?"
    assert threads["items"][0]["message_count"] == 2

    archived = client.get(f"/api/v1/career/chat/threads/{threads['items'][0]['id']}", headers=auth)
    assert archived.status_code == 200
    assert [item["role"] for item in archived.json()["messages"]] == ["user", "assistant"]

    assert client.delete("/api/v1/career/chat", headers=auth).status_code == 204
    assert client.get("/api/v1/career/chat", headers=auth).json() == []


def test_chat_mode_is_validated_used_in_prompt_and_persisted(client, monkeypatch):
    auth = register_and_headers(client, "chat-mode@example.com")
    prompts = []

    def fake_invoke(prompt, _schema):
        prompts.append(json.loads(prompt))
        return ChatReplyAI(reply="CV odaklı yanıt", suggested_actions=[])

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)

    response = client.post(
        "/api/v1/career/chat",
        headers=auth,
        json={"message": "CV'mdeki zayıf alanlar neler?", "mode": "cv"},
    )

    assert response.status_code == 201
    assert response.json()["meta"]["mode"] == "cv"
    assert "CV" in prompts[0]["purpose"]
    history = client.get("/api/v1/career/chat", headers=auth).json()
    assert [item["meta"]["mode"] for item in history] == ["cv", "cv"]
    assert client.post(
        "/api/v1/career/chat",
        headers=auth,
        json={"message": "Geçersiz mod", "mode": "unknown"},
    ).status_code == 422


def test_chat_thread_history_is_paginated_and_user_scoped(client, monkeypatch):
    auth = register_and_headers(client, "chat-owner@example.com")
    other = register_and_headers(client, "chat-other@example.com")
    monkeypatch.setattr(
        "app.services.engagement._invoke",
        lambda _prompt, schema: ChatReplyAI(reply="Yanıt", suggested_actions=[]),
    )

    for index in range(2):
        assert client.post("/api/v1/career/chat", headers=auth, json={"message": f"Konuşma {index + 1} başlığı"}).status_code == 201
        assert client.post("/api/v1/career/chat/threads", headers=auth).status_code == 201

    first_page = client.get("/api/v1/career/chat/threads?limit=1&offset=0", headers=auth).json()
    assert len(first_page["items"]) == 1
    assert first_page["has_more"] is True
    second_page = client.get("/api/v1/career/chat/threads?limit=1&offset=1", headers=auth).json()
    assert len(second_page["items"]) == 1
    assert client.get(
        f"/api/v1/career/chat/threads/{first_page['items'][0]['id']}", headers=other,
    ).status_code == 404


def test_chat_turns_explicit_job_cv_request_into_approved_action_preview(client, monkeypatch):
    auth = register_and_headers(client)
    db = db_session()
    user = db.scalar(select(User).where(User.email == "engagement@example.com"))
    user_id = user.id
    db.add(CareerAnalysis(
        id="chat-active-cv", user_id=user_id, status="ready", source="upload",
        file_name="aktif-cv.pdf", cv_text="SQL ve Python ile veri analizi projeleri geliştirdim.",
        profile={}, skills=[{"name": "SQL", "score": 80}], radar=[],
    ))
    db.commit(); db.close()
    queued = []
    monkeypatch.setattr(
        "app.services.engagement._invoke",
        lambda _prompt, _schema: ChatReplyAI(
            reply="İlanı aktif CV'nle karşılaştırıyorum; değişiklikleri onayına sunacağım.",
            suggested_actions=[],
            action="create_cv_for_job",
        ),
    )
    monkeypatch.setattr("app.tasks.career.analyze_job_task.delay", lambda job_id, snapshot: queued.append((job_id, snapshot)))

    message = "CV'mi bu ilana göre oluştur: Data Analyst rolü için ileri SQL, Python, dashboard hazırlama ve paydaş iletişimi deneyimi arıyoruz."
    response = client.post("/api/v1/career/chat", headers=auth, json={"message": message})

    assert response.status_code == 201
    action = response.json()["meta"]["action"]
    assert action == {"type": "job_cv_draft", "job_id": queued[0][0], "status": "queued"}
    assert queued[0][1]["cv_text"] == "SQL ve Python ile veri analizi projeleri geliştirdim."
    db = db_session(); job = db.get(JobOpportunity, queued[0][0])
    assert job is not None and job.user_id == user_id and job.job_text == message
    assert job.source_analysis_id == "chat-active-cv"
    assert job.source_cv_file_name == "aktif-cv.pdf"
    db.close()


def test_chat_never_offers_cv_write_action_without_ready_cv_analysis(client, monkeypatch):
    auth = register_and_headers(client, "chat-no-cv@example.com")
    monkeypatch.setattr(
        "app.services.engagement._invoke",
        lambda _prompt, _schema: ChatReplyAI(reply="Taslak hazırlıyorum.", action="create_cv_for_job"),
    )
    queued = []
    monkeypatch.setattr("app.tasks.career.analyze_job_task.delay", lambda job_id: queued.append(job_id))

    response = client.post("/api/v1/career/chat", headers=auth, json={
        "message": "CV'mi bu ilana göre oluştur: SQL, Python, raporlama ve paydaş iletişimi bilen Data Analyst arıyoruz; en az iki proje örneği bekleniyor.",
    })

    assert response.status_code == 201
    assert "önce CV Merkezi'nden bir CV yükleyip analizini tamamla" in response.json()["content"]
    assert "action" not in response.json()["meta"]
    assert queued == []


def test_interview_questions_and_scoring_are_ai_backed(client, monkeypatch):
    auth = register_and_headers(client)
    prompts = []
    languages = []

    def fake_invoke(_prompt, schema, language="tr"):
        prompts.append(json.loads(_prompt))
        languages.append(language)
        if schema is InterviewQuestionsAI:
            return InterviewQuestionsAI(questions=[
                InterviewQuestionAI(id="q1", question="SQL optimizasyon örneğin nedir?", competency="SQL", guidance="STAR kullan"),
                InterviewQuestionAI(id="q2", question="Bir paydaş çatışmasını nasıl yönettin?", competency="İletişim", guidance="Somut ol"),
                InterviewQuestionAI(id="q3", question="Bir dashboard başarısını nasıl ölçersin?", competency="Analiz", guidance="Metrik ver"),
            ])
        return InterviewEvaluationAI(score=82, feedback="Somut örnek güçlü.", strengths=["Yapı"], improvements=["Etki metriği ekle"])

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)
    interview = client.post("/api/v1/career/interviews", headers=auth)
    assert interview.status_code == 201
    assert len(interview.json()["questions"]) == 3
    interview_id = interview.json()["id"]
    assert interview.json()["source_type"] == "general"
    assert interview.json()["question_count"] == 3
    assert interview.json()["answered_count"] == 0
    answer = client.post(f"/api/v1/career/interviews/{interview_id}/answers", headers=auth, json={"question_id": "q1", "answer": "Yavaş sorguyu execution plan ile inceledim, indeks ekleyip süreyi düşürdüm."})
    assert answer.status_code == 201
    assert answer.json()["score"] == 82
    assert answer.json()["improvements"] == ["Etki metriği ekle"]
    assert answer.json()["interview_status"] == "active"
    assert answer.json()["answered_count"] == 1
    assert answer.json()["question_count"] == 3
    assert answer.json()["completed"] is False
    answer_id = answer.json()["id"]

    rescored = client.post(f"/api/v1/career/interviews/{interview_id}/answers", headers=auth, json={"question_id": "q1", "answer": "Sorguyu planla inceledim, doğru bileşik indeksi ekledim ve p95 süreyi yüzde 60 düşürdüm."})
    assert rescored.status_code == 201
    assert rescored.json()["id"] == answer_id
    assert rescored.json()["answered_count"] == 1
    assert len(client.get("/api/v1/career/interviews/current", headers=auth).json()["answers"]) == 1

    for question_id in ("q2", "q3"):
        completed = client.post(
            f"/api/v1/career/interviews/{interview_id}/answers",
            headers=auth,
            json={"question_id": question_id, "answer": "Durumu netleştirip seçenekleri ölçtüm, paydaşlarla kararı uyguladım ve sonucu metrikle izledim."},
        )
    assert completed.status_code == 201
    assert completed.json()["interview_status"] == "completed"
    assert completed.json()["answered_count"] == 3
    assert completed.json()["completed"] is True
    assert client.get("/api/v1/career/interviews/current", headers=auth).json() is None

    history = client.get("/api/v1/career/interviews/history?limit=20&offset=0", headers=auth)
    assert history.status_code == 200
    assert history.json()["has_more"] is False
    assert history.json()["limit"] == 20
    assert history.json()["offset"] == 0
    assert history.json()["items"][0]["id"] == interview_id
    assert history.json()["items"][0]["average_score"] == 82.0
    detail = client.get(f"/api/v1/career/interviews/{interview_id}", headers=auth)
    assert detail.status_code == 200
    assert len(detail.json()["answers"]) == 3
    assert detail.json()["context_snapshot"]
    rejected = client.post(f"/api/v1/career/interviews/{interview_id}/answers", headers=auth, json={"question_id": "q1", "answer": "Bu tamamlanan mülakat cevabı artık değiştirilememeli ve puanlanmamalıdır."})
    assert rejected.status_code == 409
    assert prompts[0]["system_constraint"].startswith("[SISTEM KISITI]")
    assert "Türkçe" in " ".join(prompts[0]["rules"])
    assert "Mentör" in " ".join(prompts[1]["rules"])
    assert languages == ["tr", "tr", "tr", "tr", "tr"]


def test_interview_uses_saved_panel_language_without_request_body(client, monkeypatch):
    auth = register_and_headers(client, "english-panel@example.com")
    db = db_session()
    user = db.scalar(select(User).where(User.email == "english-panel@example.com"))
    assert user is not None
    user.preferred_locale = "en"
    db.commit()
    db.close()
    prompts = []
    languages = []

    def fake_invoke(prompt, _schema, language="tr"):
        prompts.append(json.loads(prompt))
        languages.append(language)
        return InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="Describe a production incident.", competency="Operations", guidance="Use STAR"),
            InterviewQuestionAI(id="q2", question="Explain a design tradeoff.", competency="Architecture", guidance="Be specific"),
            InterviewQuestionAI(id="q3", question="How do you test a migration?", competency="Quality", guidance="Cover failure paths"),
        ])

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)

    response = client.post("/api/v1/career/interviews", headers=auth)

    assert response.status_code == 201
    assert prompts[0]["system_constraint"].startswith("[SYSTEM CONSTRAINT]")
    assert "English" in " ".join(prompts[0]["rules"])
    assert languages == ["en"]


def test_interview_explicit_language_overrides_saved_panel_language(client, monkeypatch):
    auth = register_and_headers(client, "explicit-language@example.com")
    db = db_session()
    user = db.scalar(select(User).where(User.email == "explicit-language@example.com"))
    assert user is not None
    user.preferred_locale = "tr"
    db.commit()
    db.close()
    languages = []

    def fake_invoke(_prompt, _schema, language="tr"):
        languages.append(language)
        return InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="Describe a production incident.", competency="Operations", guidance="Use STAR"),
            InterviewQuestionAI(id="q2", question="Explain a design tradeoff.", competency="Architecture", guidance="Be specific"),
            InterviewQuestionAI(id="q3", question="How do you test a migration?", competency="Quality", guidance="Cover failure paths"),
        ])

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)

    response = client.post("/api/v1/career/interviews", headers=auth, json={"language": "en"})

    assert response.status_code == 201
    assert response.json()["language"] == "en"
    assert languages == ["en"]


def test_interview_question_ids_must_be_distinct_non_blank_strings():
    with pytest.raises(ValueError, match="must be distinct"):
        InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="Bir üretim sorununu anlat.", competency="Operasyon"),
            InterviewQuestionAI(id=" q1 ", question="Bir tasarım kararını anlat.", competency="Mimari"),
            InterviewQuestionAI(id="q2", question="Bir migration nasıl test edilir?", competency="Kalite"),
        ])
    with pytest.raises(ValueError, match="cannot be blank"):
        InterviewQuestionAI(id="   ", question="Bir üretim sorununu anlat.", competency="Operasyon")


def test_interview_answer_unique_race_reloads_and_updates_competing_answer(client, monkeypatch):
    auth = register_and_headers(client, "interview-race@example.com")

    def fake_invoke(_prompt, schema, language="tr"):
        if schema is InterviewQuestionsAI:
            return InterviewQuestionsAI(questions=[
                InterviewQuestionAI(id="q1", question="Bir üretim sorununu anlat.", competency="Operasyon"),
                InterviewQuestionAI(id="q2", question="Bir tasarım kararını anlat.", competency="Mimari"),
                InterviewQuestionAI(id="q3", question="Bir migration nasıl test edilir?", competency="Kalite"),
            ])
        return InterviewEvaluationAI(
            score=91,
            feedback="Güncel değerlendirme",
            strengths=["Somutluk"],
            improvements=["Etkiyi ölç"],
        )

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)
    interview_id = client.post("/api/v1/career/interviews", headers=auth).json()["id"]
    db = db_session()
    interview = db.get(CareerInterview, interview_id)
    assert interview is not None
    user_id = interview.user_id
    original_flush = db.flush
    race_injected = False

    def inject_competing_commit(*args, **kwargs):
        nonlocal race_injected
        pending = next((item for item in db.new if isinstance(item, CareerInterviewAnswer)), None)
        if pending is not None and not race_injected:
            race_injected = True
            db.expunge(pending)
            db.rollback()
            db.add(CareerInterviewAnswer(
                id="competing-answer",
                interview_id=interview_id,
                user_id=user_id,
                question_id="q1",
                answer="Eşzamanlı eski cevap",
                score=40,
                feedback="Eski değerlendirme",
                strengths=[],
                improvements=[],
            ))
            db.commit()
            raise IntegrityError("INSERT career_interview_answers", {}, Exception("unique"))
        return original_flush(*args, **kwargs)

    db.flush = inject_competing_commit
    answer = evaluate_interview_answer(
        db,
        user_id,
        interview,
        "q1",
        "Üretim sorununu log ve metriklerle ayırdım, düzeltmeyi kademeli yayınlayıp sonucu izledim.",
    )

    assert race_injected is True
    assert answer.id == "competing-answer"
    assert answer.score == 91
    assert answer.feedback == "Güncel değerlendirme"
    assert db.scalar(select(CareerInterviewAnswer).where(
        CareerInterviewAnswer.interview_id == interview_id,
        CareerInterviewAnswer.question_id == "q1",
    )).answer.startswith("Üretim sorununu")
    db.close()


def test_concurrent_interview_start_returns_the_winning_active_session(client, monkeypatch):
    register_and_headers(client, "interview-start-race@example.com")
    monkeypatch.setattr(
        "app.services.engagement._invoke",
        lambda _prompt, _schema, language="tr": InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="Bir üretim sorununu anlat.", competency="Operasyon"),
            InterviewQuestionAI(id="q2", question="Bir tasarım kararını anlat.", competency="Mimari"),
            InterviewQuestionAI(id="q3", question="Bir migration nasıl test edilir?", competency="Kalite"),
        ]),
    )
    db = db_session()
    user = db.scalar(select(User).where(User.email == "interview-start-race@example.com"))
    assert user is not None
    original_commit = db.commit
    race_injected = False

    def inject_competing_commit():
        nonlocal race_injected
        pending = next((item for item in db.new if isinstance(item, CareerInterview)), None)
        if pending is not None and not race_injected:
            race_injected = True
            db.expunge(pending)
            db.rollback()
            db.add(CareerInterview(
                id="winning-interview",
                user_id=user.id,
                target_role="Genel kariyer görüşmesi",
                status="active",
                language="tr",
                questions=[{"id": "winner", "question": "Kazanan soru", "competency": "Kalite"}],
                context_snapshot={},
            ))
            original_commit()
            raise IntegrityError("INSERT career_interviews", {}, Exception("unique"))
        return original_commit()

    db.commit = inject_competing_commit
    interview = start_interview(db, user.id)

    assert race_injected is True
    assert interview.id == "winning-interview"
    assert db.scalars(select(CareerInterview).where(CareerInterview.status == "active")).all() == [interview]
    db.close()


def test_interview_snapshots_cv_archives_previous_and_retries_original_context(client, monkeypatch):
    auth = register_and_headers(client, "interview-owner@example.com")
    other = register_and_headers(client, "interview-other@example.com")
    db = db_session()
    owner = db.scalar(select(User).where(User.email == "interview-owner@example.com"))
    assert owner is not None
    document = CvDocument(
        id="interview-cv-1", user_id=owner.id, kind="uploaded", display_name="ilk-cv.pdf",
        original_name="ilk-cv.pdf", file_path="/tmp/ilk-cv.pdf", file_size=123, is_current=True,
    )
    analysis = CareerAnalysis(
        id="interview-analysis-1", user_id=owner.id, cv_document_id=document.id,
        status="ready", source="upload", file_name="ilk-cv.pdf", cv_text="SQL deneyimi",
        current_role="Data Analyst", profile={"summary": "İlk bağlam"},
        skills=[{"name": "SQL", "score": 80}], radar=[], career_ladder=[],
    )
    db.add_all([document, analysis])
    db.commit()
    db.close()
    invoke_count = 0

    def fake_invoke(_prompt, _schema, language="tr"):
        nonlocal invoke_count
        invoke_count += 1
        return InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="SQL performansını nasıl iyileştirirsin?", competency="SQL", guidance="Örnek ver"),
            InterviewQuestionAI(id="q2", question="Bir öncelik çatışmasını nasıl çözersin?", competency="İletişim", guidance="STAR kullan"),
            InterviewQuestionAI(id="q3", question="Başarıyı nasıl ölçersin?", competency="Analiz", guidance="Metrik ver"),
        ])

    monkeypatch.setattr("app.services.engagement._invoke", fake_invoke)
    first = client.post("/api/v1/career/interviews", headers=auth, json={"language": "tr"})
    assert first.status_code == 201
    first_body = first.json()
    assert first_body["analysis_id"] == "interview-analysis-1"
    assert first_body["cv_document_id"] == "interview-cv-1"
    assert first_body["cv_name_snapshot"] == "ilk-cv.pdf"
    assert first_body["source_type"] == "cv"
    original_context = first_body["context_snapshot"]

    db = db_session()
    stored_analysis = db.get(CareerAnalysis, "interview-analysis-1")
    stored_analysis.profile = {"summary": "Sonradan değişen bağlam"}
    db.commit()
    db.close()

    second = client.post("/api/v1/career/interviews", headers=auth, json={"language": "tr"})
    assert second.status_code == 201
    assert second.json()["id"] != first_body["id"]
    assert client.get("/api/v1/career/interviews/current", headers=auth).json()["id"] == second.json()["id"]
    history = client.get("/api/v1/career/interviews/history", headers=auth).json()
    assert [item["id"] for item in history["items"]] == [first_body["id"]]
    assert history["items"][0]["status"] == "archived"

    assert client.get(f"/api/v1/career/interviews/{first_body['id']}", headers=other).status_code == 404
    assert client.post(f"/api/v1/career/interviews/{first_body['id']}/retry", headers=other).status_code == 404
    assert client.post(
        f"/api/v1/career/interviews/{first_body['id']}/answers",
        headers=other,
        json={"question_id": "q1", "answer": "Başka kullanıcı bu mülakat cevabını değiştirememeli veya puanlayamamalıdır."},
    ).status_code == 404
    assert client.post(f"/api/v1/career/interviews/{second.json()['id']}/retry", headers=auth).status_code == 409

    retried = client.post(f"/api/v1/career/interviews/{first_body['id']}/retry", headers=auth)
    assert retried.status_code == 201
    retry_body = retried.json()
    assert retry_body["retry_of_id"] == first_body["id"]
    assert retry_body["questions"] == first_body["questions"]
    assert retry_body["context_snapshot"] == original_context
    assert retry_body["answers"] == []
    assert retry_body["status"] == "active"
    assert retry_body["cv_name_snapshot"] == "ilk-cv.pdf"
    assert invoke_count == 2

    history = client.get("/api/v1/career/interviews/history?limit=1&offset=0", headers=auth).json()
    assert history["has_more"] is True
    assert history["items"][0]["id"] == second.json()["id"]


def test_analysis_and_all_reset_archive_active_interviews_but_keep_history(client, monkeypatch):
    auth = register_and_headers(client, "interview-reset@example.com")

    monkeypatch.setattr(
        "app.services.engagement._invoke",
        lambda _prompt, _schema, language="tr": InterviewQuestionsAI(questions=[
            InterviewQuestionAI(id="q1", question="Bir problemi nasıl çözersin?", competency="Problem çözme", guidance="Örnek ver"),
            InterviewQuestionAI(id="q2", question="Bir çatışmayı nasıl yönetirsin?", competency="İletişim", guidance="STAR kullan"),
            InterviewQuestionAI(id="q3", question="Sonucu nasıl ölçersin?", competency="Analiz", guidance="Metrik ver"),
        ]),
    )

    first = client.post("/api/v1/career/interviews", headers=auth).json()
    analysis_reset = client.post("/api/v1/career/reset", headers=auth, json={"scope": "analysis"})
    assert analysis_reset.status_code == 200
    assert client.get("/api/v1/career/interviews/current", headers=auth).json() is None
    assert client.get("/api/v1/career/interviews/history", headers=auth).json()["items"][0]["id"] == first["id"]

    retried = client.post(f"/api/v1/career/interviews/{first['id']}/retry", headers=auth).json()
    all_reset = client.post("/api/v1/career/reset", headers=auth, json={"scope": "all"})
    assert all_reset.status_code == 200
    assert client.get("/api/v1/career/interviews/current", headers=auth).json() is None
    history_ids = [item["id"] for item in client.get("/api/v1/career/interviews/history", headers=auth).json()["items"]]
    assert history_ids == [retried["id"], first["id"]]


def test_personal_tasks_persist_and_are_user_scoped(client):
    auth = register_and_headers(client)
    created = client.post("/api/v1/career/personal-tasks", headers=auth, json={"title": "Portfolyo notunu düzenle"})
    assert created.status_code == 201
    task_id = created.json()["id"]
    updated = client.patch(f"/api/v1/career/personal-tasks/{task_id}", headers=auth, json={"note": "Cuma günü", "completed": True})
    assert updated.json()["completed"] is True
    assert updated.json()["note"] == "Cuma günü"
    assert client.get("/api/v1/career/personal-tasks", headers=auth).json()[0]["id"] == task_id
    other = register_and_headers(client, "other@example.com")
    assert client.patch(f"/api/v1/career/personal-tasks/{task_id}", headers=other, json={"completed": False}).status_code == 404


def test_ai_task_status_can_be_toggled_without_evidence(client):
    auth = register_and_headers(client)
    db = db_session()
    target = __import__("app.models.career_engine", fromlist=["CareerTarget"]).CareerTarget(
        id="target-toggle",
        user_id=1,
        title="CFO",
        source="ladder",
        status="active",
        localizations={
            "tr": {"title": "CFO", "task_titles": {"task-toggle": "Finansal analiz eğitimi"}},
            "en": {"title": "CFO", "task_titles": {"task-toggle": "Financial analysis course"}},
        },
    )
    task = __import__("app.models.career_engine", fromlist=["CareerTask"]).CareerTask(
        id="task-toggle", user_id=1, target_id=target.id, title="Financial analysis course", hint="Enroll",
        status="pending", evidence_required=True, evidence_types=["link"], skill_impacts=["Finance"],
        training_suggestions=[], localizations={
            "tr": {"title": "Finansal analiz eğitimi", "hint": "Kaydol", "skill_impacts": ["Finans"], "feedback": None},
            "en": {"title": "Financial analysis course", "hint": "Enroll", "skill_impacts": ["Finance"], "feedback": None},
        },
    )
    db.add_all([target, task])
    db.commit()
    db.close()

    completed = client.patch("/api/v1/career/tasks/task-toggle", headers=auth, json={"status": "completed"})
    assert completed.status_code == 200
    assert completed.json()["status"] == "completed"

    reopened = client.patch("/api/v1/career/tasks/task-toggle", headers=auth, json={"status": "pending"})
    assert reopened.status_code == 200
    assert reopened.json()["status"] == "pending"


def test_skill_evidence_can_be_submitted_for_radar_skill_without_existing_task(client, monkeypatch):
    auth = register_and_headers(client)
    db = db_session()
    target = __import__("app.models.career_engine", fromlist=["CareerTarget"]).CareerTarget(
        id="target-skill-ev",
        user_id=1,
        title="CFO",
        source="ladder",
        status="active",
        localizations={
            "tr": {"title": "CFO", "task_titles": {}},
            "en": {"title": "CFO", "task_titles": {}},
        },
    )
    db.add(target)
    db.commit()
    db.close()
    monkeypatch.setattr("app.api.v1.engagement.review_evidence_task.delay", lambda _id: None)

    response = client.post(
        "/api/v1/career/skill-evidence/link",
        headers=auth,
        json={"skill": "ERP Systems", "target_id": "target-skill-ev", "url": "https://github.com/example/erp"},
    )
    assert response.status_code == 201
    body = response.json()
    assert body["skill"] == "ERP Systems"
    assert body["task"]["status"] == "pending"
    assert "ERP Systems" in body["task"]["skill_impacts"]


def test_saved_job_requires_explicit_applied_action(client):
    auth = register_and_headers(client)
    db = db_session()
    job = JobOpportunity(id="job-apply-1", user_id=1, status="ready", title="Data Analyst", company="Acme", saved=True)
    db.add(job); db.commit(); db.close()
    assert client.get("/api/v1/career/applications", headers=auth).json() == []

    applied = client.post("/api/v1/career/jobs/job-apply-1/application", headers=auth)
    assert applied.status_code == 201
    assert applied.json()["stage"] == "applied"
    assert applied.json()["job_id"] == "job-apply-1"
    assert client.post("/api/v1/career/jobs/job-apply-1/application", headers=auth).json()["id"] == applied.json()["id"]

    moved = client.patch(f"/api/v1/career/applications/{applied.json()['id']}", headers=auth, json={"stage": "interview", "next_action": "Salı teknik görüşme"})
    assert moved.json()["stage"] == "interview"
    assert moved.json()["next_action"] == "Salı teknik görüşme"
    db = db_session(); assert db.query(JobApplication).count() == 1; db.close()
