"""Kullanıcı bağlamını kullanan sohbet ve mülakat AI servisleri."""

import json
from datetime import datetime, timedelta, timezone
from uuid import uuid4

from sqlalchemy import func, select, update
from sqlalchemy.orm import Session

from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask
from app.models.engagement import CareerChatMessage, CareerChatThread, CareerInterview, CareerInterviewAnswer
from app.schemas.engagement import ChatReplyAI, InterviewEvaluationAI, InterviewQuestionsAI
from app.services.career_engine import _invoke

# ---------------------------------------------------------------------------
# Supported interview language codes
# ---------------------------------------------------------------------------
INTERVIEW_LANG_TR = "tr"
INTERVIEW_LANG_EN = "en"
SUPPORTED_INTERVIEW_LANGS = (INTERVIEW_LANG_TR, INTERVIEW_LANG_EN)


def _normalize_interview_lang(lang: str | None) -> str:
    """Return a validated interview language code; fall back to Turkish."""
    return lang if lang in SUPPORTED_INTERVIEW_LANGS else INTERVIEW_LANG_TR


def _lang_constraint(language: str) -> str:
    """Return the hard language constraint string injected at prompt top-level."""
    if language == INTERVIEW_LANG_EN:
        return (
            "[SYSTEM CONSTRAINT] Target Language: English. "
            "Fail the request if any other language is mixed. "
            "CRITICAL REQUIREMENT: You must generate ALL interview questions, "
            "technical assessments, guidance notes, feedback, strengths, and "
            "improvement suggestions EXCLUSIVELY in English. "
            "Even if the underlying database tags, job roles, or context data "
            "contain Turkish words, you must translate and present them 100% "
            "in English. No Turkish vocabulary is allowed in the output."
        )
    return (
        "[SISTEM KISITI] Hedef Dil: Türkçe. "
        "ZORUNLU GEREKSINIM: Tüm mülakat soruları, teknik değerlendirmeler, "
        "yönlendirme notları, geri bildirim, güçlü yönler ve gelişim önerileri "
        "YALNIZCA Türkçe üretilmelidir. Çıktıda İngilizce kelime kullanılamaz."
    )


def _build_prompt_with_lang(payload: dict, language: str) -> str:
    """Keep the AI prompt valid JSON while making the language rule explicit."""
    constraint = _lang_constraint(language)
    rules = payload.get("rules", [])
    constrained_payload = {
        "system_constraint": constraint,
        **payload,
        "rules": [constraint, *rules],
    }
    return json.dumps(constrained_payload, ensure_ascii=False)


def career_context(db: Session, user_id: int) -> dict:
    analysis = db.scalar(select(CareerAnalysis).where(CareerAnalysis.user_id == user_id, CareerAnalysis.status == "ready").order_by(CareerAnalysis.created_at.desc()))
    target = db.scalar(select(CareerTarget).where(CareerTarget.user_id == user_id, CareerTarget.status.in_(["active", "ready"])).order_by(CareerTarget.created_at.desc()))
    tasks = [] if target is None else db.scalars(select(CareerTask).where(CareerTask.user_id == user_id, CareerTask.target_id == target.id).order_by(CareerTask.created_at)).all()
    return {
        "current_role": analysis.current_role if analysis else None,
        "profile": analysis.profile if analysis else {}, "skills": analysis.skills if analysis else [],
        "radar": analysis.radar if analysis else [], "career_ladder": analysis.career_ladder if analysis else [],
        "selected_target": None if target is None else {"id": target.id, "title": target.title, "source": target.source, "status": target.status, "plan": target.plan},
        "tasks": [{"title": row.title, "status": row.status, "skill_impacts": row.skill_impacts} for row in tasks],
    }


def _chat_title(message: str) -> str:
    normalized = " ".join(message.split())
    return normalized[:157] + "..." if len(normalized) > 160 else normalized


def ensure_active_chat_thread(db: Session, user_id: int) -> CareerChatThread:
    thread = db.scalar(
        select(CareerChatThread).where(
            CareerChatThread.user_id == user_id,
            CareerChatThread.is_active.is_(True),
        )
    )
    if thread is None:
        thread = CareerChatThread(id=str(uuid4()), user_id=user_id, title="Yeni sohbet", is_active=True)
        db.add(thread)
        db.flush()

    db.execute(
        update(CareerChatMessage)
        .where(CareerChatMessage.user_id == user_id, CareerChatMessage.thread_id.is_(None))
        .values(thread_id=thread.id)
    )
    return thread


def current_chat_messages(db: Session, user_id: int) -> list[CareerChatMessage]:
    thread = ensure_active_chat_thread(db, user_id)
    rows = db.scalars(
        select(CareerChatMessage)
        .where(CareerChatMessage.user_id == user_id, CareerChatMessage.thread_id == thread.id)
        .order_by(CareerChatMessage.created_at, CareerChatMessage.id)
    ).all()
    db.commit()
    return list(rows)


def serialize_chat_thread(db: Session, row: CareerChatThread) -> dict:
    message_count = db.scalar(
        select(func.count(CareerChatMessage.id)).where(
            CareerChatMessage.user_id == row.user_id,
            CareerChatMessage.thread_id == row.id,
        )
    ) or 0
    return {
        "id": row.id,
        "title": row.title,
        "message_count": message_count,
        "created_at": row.created_at.isoformat() if row.created_at else None,
        "updated_at": row.updated_at.isoformat() if row.updated_at else None,
    }


def start_new_chat_thread(db: Session, user_id: int) -> tuple[CareerChatThread, dict | None]:
    current = ensure_active_chat_thread(db, user_id)
    message_count = db.scalar(
        select(func.count(CareerChatMessage.id)).where(
            CareerChatMessage.user_id == user_id,
            CareerChatMessage.thread_id == current.id,
        )
    ) or 0
    if message_count == 0:
        db.commit()
        db.refresh(current)
        return current, None

    current.is_active = False
    current.updated_at = datetime.now(timezone.utc)
    db.flush()
    archived = serialize_chat_thread(db, current)
    active = CareerChatThread(id=str(uuid4()), user_id=user_id, title="Yeni sohbet", is_active=True)
    db.add(active)
    db.commit()
    db.refresh(active)
    return active, archived


def list_chat_threads(db: Session, user_id: int, limit: int, offset: int) -> dict:
    query = (
        select(CareerChatThread)
        .where(CareerChatThread.user_id == user_id, CareerChatThread.is_active.is_(False))
        .order_by(CareerChatThread.updated_at.desc(), CareerChatThread.id.desc())
        .offset(offset)
        .limit(limit + 1)
    )
    rows = list(db.scalars(query).all())
    return {
        "items": [serialize_chat_thread(db, row) for row in rows[:limit]],
        "has_more": len(rows) > limit,
    }


def get_chat_thread(db: Session, user_id: int, thread_id: str) -> tuple[CareerChatThread, list[CareerChatMessage]] | None:
    thread = db.scalar(
        select(CareerChatThread).where(
            CareerChatThread.id == thread_id,
            CareerChatThread.user_id == user_id,
            CareerChatThread.is_active.is_(False),
        )
    )
    if thread is None:
        return None
    rows = db.scalars(
        select(CareerChatMessage)
        .where(CareerChatMessage.user_id == user_id, CareerChatMessage.thread_id == thread.id)
        .order_by(CareerChatMessage.created_at, CareerChatMessage.id)
    ).all()
    return thread, list(rows)


def answer_chat(db: Session, user_id: int, message: str) -> CareerChatMessage:
    thread = ensure_active_chat_thread(db, user_id)
    history = db.scalars(
        select(CareerChatMessage)
        .where(CareerChatMessage.user_id == user_id, CareerChatMessage.thread_id == thread.id)
        .order_by(CareerChatMessage.created_at.desc())
        .limit(12)
    ).all()
    output = _invoke(json.dumps({
        "purpose": "Kullanıcıya yalnız kendi kariyer verisine dayanan uygulanabilir kariyer desteği ver",
        "rules": [
            "CV'de veya kanıtlarda olmayan başarı uydurma",
            "Belirsiz bilgiyi belirt",
            "Yanıtı kısa ve eyleme dönük tut",
            "action=create_cv_for_job yalnız kullanıcı gerçek ilan içeriğini bu mesajda verip CV'sini ilana göre oluşturmayı veya uyarlamayı açıkça isterse seç",
            "İlan içeriği yoksa, kullanıcı yalnız niyet veya gelecek planı söylüyorsa action=none seç",
        ],
        "career_context": career_context(db, user_id),
        "recent_messages": [{"role": row.role, "content": row.content} for row in reversed(history)],
        "user_message": message,
    }, ensure_ascii=False), ChatReplyAI)
    action = None
    reply = output.reply
    suggested_actions = output.suggested_actions
    if output.action == "create_cv_for_job" and len(message.strip()) >= 80:
        analysis = db.scalar(
            select(CareerAnalysis)
            .where(CareerAnalysis.user_id == user_id, CareerAnalysis.status == "ready")
            .order_by(CareerAnalysis.created_at.desc())
        )
        if analysis is not None:
            from app.services.job_opportunity import create_job
            from app.tasks.career import analyze_job_task

            job = create_job(db, user_id, None, message)
            analyze_job_task.delay(job.id)
            action = {"type": "job_cv_draft", "job_id": job.id, "status": job.status}
        else:
            reply = "Bu ilan için CV taslağı oluşturabilmem adına önce CV Merkezi'nden bir CV yükleyip analizini tamamla."
            suggested_actions = ["CV Merkezi'ne git"]

    if not history:
        thread.title = _chat_title(message)
    thread.updated_at = datetime.now(timezone.utc)
    message_time = datetime.now(timezone.utc)
    user_row = CareerChatMessage(
        id=str(uuid4()), user_id=user_id, thread_id=thread.id, role="user", content=message, meta={}, created_at=message_time
    )
    assistant_row = CareerChatMessage(
        id=str(uuid4()),
        user_id=user_id,
        thread_id=thread.id,
        role="assistant",
        content=reply,
        meta={"suggested_actions": suggested_actions, **({"action": action} if action else {})},
        created_at=message_time + timedelta(microseconds=1),
    )
    db.add_all([user_row, assistant_row]); db.commit(); db.refresh(assistant_row)
    return assistant_row


def start_interview(db: Session, user_id: int, language: str = INTERVIEW_LANG_TR) -> CareerInterview:
    lang = _normalize_interview_lang(language)
    context = career_context(db, user_id)

    # target_role: DB'den gelen başlığı al, yoksa lang'a göre lokalize edilmiş fallback kullan
    raw_target = (
        (context.get("selected_target") or {}).get("title")
        or context.get("current_role")
    )
    if lang == INTERVIEW_LANG_EN:
        target_role = raw_target or "General career interview"
    else:
        # Hedef başlığı İngilizce bile olsa Prompt'a çevirisini yap
        target_role = raw_target or "Genel kariyer görüşmesi"

    if lang == INTERVIEW_LANG_EN:
        rules = [
            "Balance behavioural and technical questions equally.",
            "Each question must test a different competency.",
            (
                "The 'guidance' field MUST be a short, encouraging tip addressed DIRECTLY to the "
                "candidate (the person answering, not the interviewer). "
                "Write it in second person ('Think about...', 'Consider mentioning...'). "
                "Do NOT write internal HR/interviewer instructions "
                "(e.g. 'Ask the candidate to...' or 'Probe for...'). "
                "Keep it under 80 words."
            ),
        ]
    else:
        rules = [
            "Davranışsal ve teknik soruları dengeli dağıt",
            "Her soru farklı yetkinliği ölsün",
            (
                "'guidance' alanı MÜLAKATA GİREN ADAYA (yanıtlayan kişiye) kısa ve "
                "teşvik edici bir ipucu olmalıdır. "
                "'Düşün...', 'Göz önünde bulundur...' gibi ikinci tekil şahsla yaz. "
                "HİÇBİR ZAMAN İK'ya/mülakatcıya yönelik iç yönerge yazma "
                "(rn. 'Adayın iş birliği becerilerini ölçmek için...' gibi ifadeler kullanamazsın). "
                "80 kelimeyi geçme."
            ),
        ]

    # Dil kilidi: purpose ve rules seçilen mülakat diline göre yazılıyor
    prompt = _build_prompt_with_lang({
        "language_lock": f"INTERVIEW_LANGUAGE={lang.upper()} — Every field in your JSON output MUST be written in {'English' if lang == INTERVIEW_LANG_EN else 'Turkish'}. No exceptions.",
        "purpose": (
            "Generate tailored interview questions for the candidate's target role and CV gaps."
            if lang == INTERVIEW_LANG_EN
            else "Adayın hedef mesleğine ve CV boşluklarına özel mülakat soruları üret"
        ),
        "target_role": target_role,
        "career_context": context,
        "rules": rules,
    }, lang)

    output = _invoke(prompt, InterviewQuestionsAI, language=lang)

    row = CareerInterview(
        id=str(uuid4()),
        user_id=user_id,
        target_role=target_role,
        status="active",
        language=lang,
        questions=output.model_dump(mode="json")["questions"],
    )
    db.add(row); db.commit(); db.refresh(row)
    return row


def evaluate_interview_answer(db: Session, user_id: int, interview: CareerInterview, question_id: str, answer: str) -> CareerInterviewAnswer:
    question = next((item for item in interview.questions if item.get("id") == question_id), None)
    if question is None:
        raise ValueError("Mülakat sorusu bulunamadı")

    lang = _normalize_interview_lang(getattr(interview, "language", INTERVIEW_LANG_TR))

    if lang == INTERVIEW_LANG_EN:
        rules = [
            "Score based on content quality and problem-solving logic, not answer length.",
            "Evaluate concreteness, accuracy, structure, and role-fit.",
            "Do not count claims absent from the CV as strengths.",
            "Evaluate the candidate's answer without drowning them in specific technical terms "
            "(e.g. BLEU, Perplexity, Transformer, Tokenization). Not using those terms does not "
            "mean the candidate lacks understanding; assess the overall engineering approach.",
            "Never output a list of 'you didn't mention these terms'. Instead, if a critical gap "
            "exists, suggest it conceptually in the Improvements section.",
            "Act as an experienced team leader (Mentor) assessing potential, not a harsh examiner.",
            "Look for traces of the STAR method (Situation, Task, Action, Result) and reward "
            "step-by-step problem-solving.",
        ]
    else:
        rules = [
            "Uzunluğa göre puan verme, içeriğin kalitesine ve problem çözme mantığına odaklan.",
            "Somutluk, doğruluk, yapı ve role uygunluğu değerlendir.",
            "CV'de olmayan iddiaları güçlü yan sayma.",
            "Adayın cevabını spesifik teknik terimlere (örn: BLEU, Perplexity, Transformer, Tokenization) boğulmadan değerlendir. Adayın bu terimleri kullanmamış olması, o konuyu bilmediği anlamına gelmez; cevabın genel mühendislik yaklaşımını değerlendir.",
            "Asla 'Şu terimleri kullanmadın' veya 'Şu terimlere değinilmemiş' şeklinde teknik terim listesi çıkarma. Bunun yerine, eğer çok kritik bir eksiklik varsa bunu 'Gelişim Alanları'nda kavramsal olarak öner.",
            "Acımasız bir sınav okuyucusu gibi değil, adayın potansiyelini ölçen deneyimli bir takım lideri (Mentör) gibi davran.",
            "Cevapta STAR (Durum, Görev, Eylem, Sonuç) metodolojisinin izlerini ara ve problemi adım adım çözmesini ödüllendir.",
        ]

    prompt = _build_prompt_with_lang({
        "language_lock": f"INTERVIEW_LANGUAGE={lang.upper()} — Every field in your JSON output MUST be written in {'English' if lang == INTERVIEW_LANG_EN else 'Turkish'}. No exceptions.",
        "purpose": (
            "Evaluate the interview answer against the target role and the candidate's real CV context."
            if lang == INTERVIEW_LANG_EN
            else "Mülakat cevabını hedef rol ve adayın gerçek CV bağlamına göre değerlendir"
        ),
        "career_context": career_context(db, user_id),
        "target_role": interview.target_role,
        "question": question,
        "answer": answer,
        "rules": rules,
    }, lang)

    output = _invoke(prompt, InterviewEvaluationAI, language=lang)
    row = CareerInterviewAnswer(
        id=str(uuid4()),
        interview_id=interview.id,
        user_id=user_id,
        question_id=question_id,
        answer=answer,
        **output.model_dump(mode="json"),
    )
    db.add(row); db.commit(); db.refresh(row)
    return row


def serialize_chat(row: CareerChatMessage) -> dict:
    return {"id": row.id, "role": row.role, "content": row.content, "meta": row.meta, "created_at": row.created_at.isoformat() if row.created_at else None}


def serialize_answer(row: CareerInterviewAnswer) -> dict:
    return {"id": row.id, "question_id": row.question_id, "answer": row.answer, "score": row.score, "feedback": row.feedback, "strengths": row.strengths, "improvements": row.improvements}


def serialize_interview(row: CareerInterview, answers: list[CareerInterviewAnswer] | None = None) -> dict:
    return {
        "id": row.id,
        "target_role": row.target_role,
        "status": row.status,
        "language": getattr(row, "language", INTERVIEW_LANG_TR),
        "questions": row.questions,
        "answers": [serialize_answer(item) for item in (answers or [])],
        "created_at": row.created_at.isoformat() if row.created_at else None,
    }
