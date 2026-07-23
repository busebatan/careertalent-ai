"""Kullanıcı bağlamını kullanan sohbet ve mülakat AI servisleri."""

import json
from copy import deepcopy
from datetime import datetime, timedelta, timezone
from typing import Literal
from uuid import uuid4

from sqlalchemy import func, select, update
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask
from app.models.engagement import CareerChatMessage, CareerChatThread, CareerInterview, CareerInterviewAnswer, CvDocument
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


def answer_chat(
    db: Session,
    user_id: int,
    message: str,
    mode: Literal["cv", "interview", "career"] | None = None,
) -> CareerChatMessage:
    thread = ensure_active_chat_thread(db, user_id)
    history = db.scalars(
        select(CareerChatMessage)
        .where(CareerChatMessage.user_id == user_id, CareerChatMessage.thread_id == thread.id)
        .order_by(CareerChatMessage.created_at.desc())
        .limit(12)
    ).all()
    purpose = {
        "cv": "Sen yıllarını yetenek kazanımı (recruitment) sektörüne adamış, küresel teknoloji şirketlerinde on binlerce CV incelemiş ve işe alım süreçlerini yönetmiş bir Senior Lead Recruiter'sın. Amacın, karşına gelen CV'yi yalnızca dilbilgisi veya format açısından değil; 'Bu aday masamda olsaydı onu neden mülakata çağırırdım ya da neden elerdim?' gözüyle analiz etmektir. Kullanıcının kariyer verisine ve hedeflerine dayanarak, ATS (Aday Takip Sistemleri) uyumluluğunu, yeteneklerin nasıl daha somut ve etki odaklı (başarı hikayeleriyle) sunulabileceğini değerlendir. Güçlü yönlerini överken, zayıf veya soyut kalmış kısımlar için acımasızca dürüst ancak son derece yapıcı, nokta atışı ve hemen uygulanabilir revizyon önerileri ver.",
        "interview": "Sen üst düzey yöneticiler ve mühendislerle zorlu mülakatlar gerçekleştiren, sektörde isim yapmış, tecrübeli bir İK Direktörü ve Mülakat Koçu'sun. Amacın, adayı terletmek değil; onun içindeki gerçek potansiyeli ve tecrübeyi dışarı çıkarmasını sağlamaktır. Kullanıcının kariyer verisine dayanarak, gerçek bir mülakat simülasyonu yap. Adayın cevaplarında STAR (Durum, Görev, Eylem, Sonuç) metodolojisinin izlerini ara; sadece ne yaptığını değil, 'nasıl' ve 'neden' yaptığını, kuruma ne değer kattığını sorgula. Adayın eksik veya yüzeysel kalan cevaplarını tespit ettiğinde, ona anında ustaca geri bildirimler sun, cevap yapısını nasıl daha vurucu hale getirebileceğini somut örneklerle göster ve ona güven aşıla.",
        "career": "Sen Silikon Vadisi ve uluslararası arenada sayısız profesyonelin kariyer yolculuğunu şekillendirmiş, pazar trendlerine, geleceğin mesleklerine ve yetkinlik setlerine son derece hakim vizyoner bir Kariyer Mentoru ve Stratejisti'sin. Amacın, kullanıcının mevcut yetenek envanteri ile varmak istediği hedef arasındaki boşluğu tespit etmek ve bu boşluğu dolduracak 'hack'leri sunmaktır. Sıradan ve genel geçer tavsiyeler (örneğin: 'çok çalış, kursa git') yerine; kullanıcının anında uygulamaya başlayabileceği, gerçek dünyada en yüksek etkiyi yaratacak somut adımlara odaklan. Kullanıcıyı uzak gelecekle veya soyut vadelerle boğmak yerine, 'şu an ilk olarak ne yapmalı ve bunu NEDEN yapmalı' yaklaşımını benimse. Hangi sertifikaların sektörde gerçek bir ağırlığı olduğunu, hangi projelerin portföyde fark yaratacağını belirterek gerekçelendirilmiş, net ve heyecan verici öncelikli kariyer adımları tasarla.",
    }.get(mode, "Kullanıcıya yalnız kendi kariyer verisine dayanan uygulanabilir kariyer desteği ver")
    output = _invoke(json.dumps({
        "purpose": purpose,
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
            from app.services.job_opportunity import create_job, cv_snapshot, dispatch_job_analysis
            from app.tasks.career import analyze_job_task

            snapshot = cv_snapshot(analysis)
            job = create_job(db, user_id, None, message, analysis)
            dispatch_job_analysis(db, job, snapshot, analyze_job_task.delay)
            action = {"type": "job_cv_draft", "job_id": job.id, "status": job.status}
        else:
            reply = "Bu ilan için CV taslağı oluşturabilmem adına önce CV Merkezi'nden bir CV yükleyip analizini tamamla."
            suggested_actions = ["CV Merkezi'ne git"]

    if not history:
        thread.title = _chat_title(message)
    thread.updated_at = datetime.now(timezone.utc)
    message_time = datetime.now(timezone.utc)
    mode_meta = {"mode": mode} if mode else {}
    user_row = CareerChatMessage(
        id=str(uuid4()), user_id=user_id, thread_id=thread.id, role="user", content=message, meta=mode_meta, created_at=message_time
    )
    assistant_row = CareerChatMessage(
        id=str(uuid4()),
        user_id=user_id,
        thread_id=thread.id,
        role="assistant",
        content=reply,
        meta={"suggested_actions": suggested_actions, **mode_meta, **({"action": action} if action else {})},
        created_at=message_time + timedelta(microseconds=1),
    )
    db.add_all([user_row, assistant_row]); db.commit(); db.refresh(assistant_row)
    return assistant_row



class InterviewStateError(RuntimeError):
    """Raised when an interview operation conflicts with its lifecycle state."""


def _question_ids(interview: CareerInterview) -> list[str]:
    return list(dict.fromkeys(
        str(item.get("id"))
        for item in (interview.questions or [])
        if isinstance(item, dict) and item.get("id")
    ))


def interview_answers(db: Session, user_id: int, interview_id: str) -> list[CareerInterviewAnswer]:
    return list(db.scalars(
        select(CareerInterviewAnswer)
        .where(
            CareerInterviewAnswer.interview_id == interview_id,
            CareerInterviewAnswer.user_id == user_id,
        )
        .order_by(CareerInterviewAnswer.created_at, CareerInterviewAnswer.id)
    ).all())


def archive_active_interviews(db: Session, user_id: int) -> int:
    ended_at = datetime.now(timezone.utc)
    return db.execute(
        update(CareerInterview)
        .where(CareerInterview.user_id == user_id, CareerInterview.status == "active")
        .values(status="archived", ended_at=ended_at, updated_at=ended_at)
    ).rowcount


def _interview_source(db: Session, user_id: int) -> tuple[CareerAnalysis | None, CvDocument | None]:
    analysis = db.scalar(
        select(CareerAnalysis)
        .where(CareerAnalysis.user_id == user_id, CareerAnalysis.status == "ready")
        .order_by(CareerAnalysis.created_at.desc(), CareerAnalysis.id.desc())
    )
    document = None
    if analysis is not None and analysis.cv_document_id:
        document = db.scalar(
            select(CvDocument).where(
                CvDocument.id == analysis.cv_document_id,
                CvDocument.user_id == user_id,
            )
        )
    return analysis, document


def _commit_active_interview(db: Session, row: CareerInterview) -> CareerInterview:
    db.add(row)
    try:
        db.commit()
    except IntegrityError as exc:
        db.rollback()
        constraint_name = getattr(getattr(exc, "orig", None), "diag", None)
        constraint_name = getattr(constraint_name, "constraint_name", None)
        if constraint_name not in {None, "uq_career_interviews_active_user"}:
            raise
        winner = db.scalar(
            select(CareerInterview)
            .where(CareerInterview.user_id == row.user_id, CareerInterview.status == "active")
            .order_by(CareerInterview.created_at.desc(), CareerInterview.id.desc())
            .limit(1)
        )
        if winner is None:
            raise
        return winner
    db.refresh(row)
    return row


def start_interview(db: Session, user_id: int, language: str = INTERVIEW_LANG_TR) -> CareerInterview:
    lang = _normalize_interview_lang(language)
    context = career_context(db, user_id)
    analysis, document = _interview_source(db, user_id)

    raw_target = (
        (context.get("selected_target") or {}).get("title")
        or context.get("current_role")
    )
    if lang == INTERVIEW_LANG_EN:
        target_role = raw_target or "General career interview"
    else:
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
            "Her soru farklı yetkinliği ölçsün",
            (
                "'guidance' alanı MÜLAKATA GİREN ADAYA (yanıtlayan kişiye) kısa ve "
                "teşvik edici bir ipucu olmalıdır. "
                "'Düşün...', 'Göz önünde bulundur...' gibi ikinci tekil şahısla yaz. "
                "HİÇBİR ZAMAN İK'ya/mülakatçıya yönelik iç yönerge yazma "
                "(örn. 'Adayın iş birliği becerilerini ölçmek için...' gibi ifadeler kullanma). "
                "80 kelimeyi geçme."
            ),
        ]

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

    archive_active_interviews(db, user_id)
    row = CareerInterview(
        id=str(uuid4()),
        user_id=user_id,
        analysis_id=analysis.id if analysis else None,
        cv_document_id=document.id if document else None,
        cv_name_snapshot=(document.display_name if document else analysis.file_name if analysis else None),
        context_snapshot=deepcopy(context),
        target_role=target_role,
        status="active",
        language=lang,
        questions=output.model_dump(mode="json")["questions"],
    )
    return _commit_active_interview(db, row)


def retry_interview(db: Session, user_id: int, source: CareerInterview) -> CareerInterview:
    if source.user_id != user_id:
        raise ValueError("Mülakat bulunamadı")
    if source.status == "active":
        raise InterviewStateError("Aktif mülakat geçmişten yeniden başlatılamaz")

    archive_active_interviews(db, user_id)
    row = CareerInterview(
        id=str(uuid4()),
        user_id=user_id,
        analysis_id=source.analysis_id,
        cv_document_id=source.cv_document_id,
        cv_name_snapshot=source.cv_name_snapshot,
        context_snapshot=deepcopy(source.context_snapshot or {}),
        retry_of_id=source.id,
        target_role=source.target_role,
        status="active",
        language=_normalize_interview_lang(source.language),
        questions=deepcopy(source.questions or []),
    )
    return _commit_active_interview(db, row)


def evaluate_interview_answer(
    db: Session,
    user_id: int,
    interview: CareerInterview,
    question_id: str,
    answer: str,
) -> CareerInterviewAnswer:
    if interview.user_id != user_id:
        raise ValueError("Mülakat bulunamadı")
    if interview.status != "active":
        raise InterviewStateError("Yalnız aktif mülakat cevapları değerlendirilebilir")

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
            "Evaluate the interview answer against the target role and the candidate's original CV context."
            if lang == INTERVIEW_LANG_EN
            else "Mülakat cevabını hedef rol ve adayın özgün CV bağlamına göre değerlendir"
        ),
        "career_context": deepcopy(interview.context_snapshot or {}),
        "target_role": interview.target_role,
        "question": question,
        "answer": answer,
        "rules": rules,
    }, lang)

    output = _invoke(prompt, InterviewEvaluationAI, language=lang)
    locked_interview = db.scalar(
        select(CareerInterview)
        .where(
            CareerInterview.id == interview.id,
            CareerInterview.user_id == user_id,
        )
        .with_for_update()
    )
    if locked_interview is None:
        raise ValueError("Mülakat bulunamadı")
    if locked_interview.status != "active":
        raise InterviewStateError("Yalnız aktif mülakat cevapları değerlendirilebilir")
    interview = locked_interview
    row = db.scalar(
        select(CareerInterviewAnswer).where(
            CareerInterviewAnswer.interview_id == interview.id,
            CareerInterviewAnswer.user_id == user_id,
            CareerInterviewAnswer.question_id == question_id,
        )
    )
    values = output.model_dump(mode="json")

    def persist_answer(current: CareerInterviewAnswer | None) -> CareerInterviewAnswer:
        if current is None:
            current = CareerInterviewAnswer(
                id=str(uuid4()),
                interview_id=interview.id,
                user_id=user_id,
                question_id=question_id,
                answer=answer,
                **values,
            )
            db.add(current)
        else:
            current.answer = answer
            for field, value in values.items():
                setattr(current, field, value)
        return current

    row = persist_answer(row)
    try:
        db.flush()
    except IntegrityError as exc:
        db.rollback()
        interview = db.scalar(
            select(CareerInterview)
            .where(
                CareerInterview.id == interview.id,
                CareerInterview.user_id == user_id,
            )
            .with_for_update()
        )
        if interview is None:
            raise ValueError("Mülakat bulunamadı") from exc
        if interview.status != "active":
            raise InterviewStateError("Yalnız aktif mülakat cevapları değerlendirilebilir") from exc
        concurrent_answer = db.scalar(
            select(CareerInterviewAnswer).where(
                CareerInterviewAnswer.interview_id == interview.id,
                CareerInterviewAnswer.user_id == user_id,
                CareerInterviewAnswer.question_id == question_id,
            )
        )
        if concurrent_answer is None:
            raise
        row = persist_answer(concurrent_answer)
        db.flush()

    answered_ids = set(db.scalars(
        select(CareerInterviewAnswer.question_id).where(
            CareerInterviewAnswer.interview_id == interview.id,
            CareerInterviewAnswer.user_id == user_id,
        )
    ).all())
    question_ids = set(_question_ids(interview))
    if question_ids and question_ids.issubset(answered_ids):
        now = datetime.now(timezone.utc)
        interview.status = "completed"
        interview.ended_at = now
        interview.updated_at = now

    db.commit()
    db.refresh(row)
    db.refresh(interview)
    return row


def list_interview_history(db: Session, user_id: int, limit: int, offset: int) -> dict:
    rows = list(db.scalars(
        select(CareerInterview)
        .where(CareerInterview.user_id == user_id, CareerInterview.status != "active")
        .order_by(CareerInterview.ended_at.desc(), CareerInterview.created_at.desc(), CareerInterview.id.desc())
        .offset(offset)
        .limit(limit + 1)
    ).all())
    page = rows[:limit]
    answers_by_interview: dict[str, list[CareerInterviewAnswer]] = {row.id: [] for row in page}
    if page:
        answer_rows = db.scalars(
            select(CareerInterviewAnswer)
            .where(
                CareerInterviewAnswer.user_id == user_id,
                CareerInterviewAnswer.interview_id.in_([row.id for row in page]),
            )
            .order_by(CareerInterviewAnswer.created_at, CareerInterviewAnswer.id)
        ).all()
        for answer in answer_rows:
            answers_by_interview[answer.interview_id].append(answer)
    return {
        "items": [serialize_interview_summary(row, answers_by_interview[row.id]) for row in page],
        "limit": limit,
        "offset": offset,
        "has_more": len(rows) > limit,
    }


def serialize_chat(row: CareerChatMessage) -> dict:
    return {
        "id": row.id,
        "role": row.role,
        "content": row.content,
        "meta": row.meta,
        "created_at": row.created_at.isoformat() if row.created_at else None,
    }


def serialize_answer(row: CareerInterviewAnswer) -> dict:
    return {
        "id": row.id,
        "question_id": row.question_id,
        "answer": row.answer,
        "score": row.score,
        "feedback": row.feedback,
        "strengths": row.strengths,
        "improvements": row.improvements,
    }


def _interview_metrics(row: CareerInterview, answers: list[CareerInterviewAnswer]) -> dict:
    valid_question_ids = set(_question_ids(row))
    answers_by_question = {
        item.question_id: item for item in answers if item.question_id in valid_question_ids
    }
    scored_answers = list(answers_by_question.values())
    return {
        "question_count": len(valid_question_ids),
        "answered_count": len(scored_answers),
        "average_score": round(sum(item.score for item in scored_answers) / len(scored_answers), 1)
        if scored_answers else None,
        "completed": row.status == "completed",
    }


def _interview_metadata(row: CareerInterview, answers: list[CareerInterviewAnswer]) -> dict:
    return {
        "id": row.id,
        "target_role": row.target_role,
        "status": row.status,
        "language": getattr(row, "language", INTERVIEW_LANG_TR),
        "analysis_id": row.analysis_id,
        "cv_document_id": row.cv_document_id,
        "cv_name_snapshot": row.cv_name_snapshot,
        "source_type": "cv" if row.analysis_id or row.cv_document_id or row.cv_name_snapshot else "general",
        "retry_of_id": row.retry_of_id,
        "ended_at": row.ended_at.isoformat() if row.ended_at else None,
        "created_at": row.created_at.isoformat() if row.created_at else None,
        "updated_at": row.updated_at.isoformat() if row.updated_at else None,
        **_interview_metrics(row, answers),
    }


def serialize_interview_summary(
    row: CareerInterview,
    answers: list[CareerInterviewAnswer] | None = None,
) -> dict:
    return _interview_metadata(row, answers or [])


def serialize_interview(
    row: CareerInterview,
    answers: list[CareerInterviewAnswer] | None = None,
) -> dict:
    answer_rows = answers or []
    return {
        **_interview_metadata(row, answer_rows),
        "questions": deepcopy(row.questions or []),
        "answers": [serialize_answer(item) for item in answer_rows],
        "context_snapshot": deepcopy(row.context_snapshot or {}),
    }


def serialize_scored_answer(
    row: CareerInterviewAnswer,
    interview: CareerInterview,
    answers: list[CareerInterviewAnswer],
) -> dict:
    metrics = _interview_metrics(interview, answers)
    return {
        **serialize_answer(row),
        "interview_status": interview.status,
        "answered_count": metrics["answered_count"],
        "question_count": metrics["question_count"],
        "completed": metrics["completed"],
    }
