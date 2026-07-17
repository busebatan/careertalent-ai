"""Kullanıcı bağlamını kullanan sohbet ve mülakat AI servisleri."""

import json
from uuid import uuid4

from sqlalchemy import select
from sqlalchemy.orm import Session

from app.models.career_engine import CareerAnalysis, CareerTarget, CareerTask
from app.models.engagement import CareerChatMessage, CareerInterview, CareerInterviewAnswer
from app.schemas.engagement import ChatReplyAI, InterviewEvaluationAI, InterviewQuestionsAI
from app.services.career_engine import _invoke


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


def answer_chat(db: Session, user_id: int, message: str) -> CareerChatMessage:
    history = db.scalars(select(CareerChatMessage).where(CareerChatMessage.user_id == user_id).order_by(CareerChatMessage.created_at.desc()).limit(12)).all()
    output = _invoke(json.dumps({
        "purpose": "Kullanıcıya yalnız kendi kariyer verisine dayanan uygulanabilir kariyer desteği ver",
        "rules": ["CV'de veya kanıtlarda olmayan başarı uydurma", "Belirsiz bilgiyi belirt", "Yanıtı kısa ve eyleme dönük tut"],
        "career_context": career_context(db, user_id),
        "recent_messages": [{"role": row.role, "content": row.content} for row in reversed(history)],
        "user_message": message,
    }, ensure_ascii=False), ChatReplyAI)
    user_row = CareerChatMessage(id=str(uuid4()), user_id=user_id, role="user", content=message, meta={})
    assistant_row = CareerChatMessage(id=str(uuid4()), user_id=user_id, role="assistant", content=output.reply, meta={"suggested_actions": output.suggested_actions})
    db.add_all([user_row, assistant_row]); db.commit(); db.refresh(assistant_row)
    return assistant_row


def start_interview(db: Session, user_id: int, language: str = "tr") -> CareerInterview:
    context = career_context(db, user_id)
    target_role = (context.get("selected_target") or {}).get("title") or context.get("current_role") or "Genel kariyer görüşmesi"
    
    # Seçilen dile göre yapay zekaya kesin bir talimat hazırlıyoruz
    lang_instruction = "Tüm soruları KESİNLİKLE Türkçe üret." if language == "tr" else "Tüm soruları KESİNLİKLE İngilizce (English) üret."

    output = _invoke(json.dumps({
        "purpose": "Adayın hedef mesleğine ve CV boşluklarına özel mülakat soruları üret",
        "target_role": target_role, 
        "career_context": context,
        "rules": [
            "Davranışsal ve teknik soruları dengeli dağıt", 
            "Her soru farklı yetkinliği ölçsün",
            lang_instruction # Dil kuralını buraya ekliyoruz
        ],
    }, ensure_ascii=False), InterviewQuestionsAI)
    
    row = CareerInterview(id=str(uuid4()), user_id=user_id, target_role=target_role, status="active", questions=output.model_dump(mode="json")["questions"])
    db.add(row); db.commit(); db.refresh(row)
    return row


def evaluate_interview_answer(db: Session, user_id: int, interview: CareerInterview, question_id: str, answer: str) -> CareerInterviewAnswer:
    question = next((item for item in interview.questions if item.get("id") == question_id), None)
    if question is None:
        raise ValueError("Mülakat sorusu bulunamadı")
    output = _invoke(json.dumps({
        "purpose": "Mülakat cevabını hedef rol ve adayın gerçek CV bağlamına göre değerlendir",
        "career_context": career_context(db, user_id), "target_role": interview.target_role,
        "question": question, "answer": answer,
        "rules": ["Uzunluğa göre puan verme", "Somutluk, doğruluk, yapı ve role uygunluğu değerlendir", "CV'de olmayan iddiaları güçlü yan sayma"],
    }, ensure_ascii=False), InterviewEvaluationAI)
    row = CareerInterviewAnswer(id=str(uuid4()), interview_id=interview.id, user_id=user_id, question_id=question_id, answer=answer, **output.model_dump(mode="json"))
    db.add(row); db.commit(); db.refresh(row)
    return row


def serialize_chat(row: CareerChatMessage) -> dict:
    return {"id": row.id, "role": row.role, "content": row.content, "meta": row.meta, "created_at": row.created_at.isoformat() if row.created_at else None}


def serialize_answer(row: CareerInterviewAnswer) -> dict:
    return {"id": row.id, "question_id": row.question_id, "answer": row.answer, "score": row.score, "feedback": row.feedback, "strengths": row.strengths, "improvements": row.improvements}


def serialize_interview(row: CareerInterview, answers: list[CareerInterviewAnswer] | None = None) -> dict:
    return {"id": row.id, "target_role": row.target_role, "status": row.status, "questions": row.questions, "answers": [serialize_answer(item) for item in (answers or [])], "created_at": row.created_at.isoformat() if row.created_at else None}
