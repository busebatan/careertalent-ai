from celery import Celery

from app.core.config import settings

celery_app = Celery("careertalent", broker=settings.REDIS_URL, backend=settings.REDIS_URL)
celery_app.conf.update(
    task_always_eager=settings.CELERY_TASK_ALWAYS_EAGER,
    task_eager_propagates=True,
    include=["app.tasks.career"],
)
