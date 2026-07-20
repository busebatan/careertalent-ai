from celery import Celery

from app.core.config import settings

celery_app = Celery("careertalent", broker=settings.REDIS_URL, backend=settings.REDIS_URL)
celery_app.conf.update(
    task_always_eager=settings.CELERY_TASK_ALWAYS_EAGER,
    task_eager_propagates=True,
    task_track_started=True,
    worker_prefetch_multiplier=1,
    include=["app.tasks.career", "app.tasks.company_recruiting"],
    beat_schedule={
        "dispatch-company-task-outbox": {
            "task": "company.dispatch_outbox",
            "schedule": 5.0,
        },
    },
    timezone="UTC",
)
