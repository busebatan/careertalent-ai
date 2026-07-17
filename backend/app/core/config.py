"""Uygulama ayarları — .env dosyasından okunur."""

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    APP_NAME: str = "CareerTalent AI"
    APP_ENV: str = "development"
    DEBUG: bool = True
    AUTO_CREATE_TABLES: bool = False
    SECRET_KEY: str = "dev-secret-change-me"

    DATABASE_URL: str = "postgresql+psycopg2://careertalent:careertalent@127.0.0.1:5432/careertalent"
    REDIS_URL: str = "redis://localhost:6379/0"
    CELERY_TASK_ALWAYS_EAGER: bool = False

    # Yapay zeka — AI_PROVIDER ile seçilir: deepseek | gemini | groq
    AI_PROVIDER: str = "deepseek"
    AI_TEMPERATURE: float = 0.3

    DEEPSEEK_API_KEY: str = ""
    DEEPSEEK_BASE_URL: str = "https://api.deepseek.com"
    DEEPSEEK_MODEL: str = "deepseek-v4-flash"

    GEMINI_API_KEY: str = ""
    GEMINI_MODEL: str = "gemini-2.5-flash"

    GROQ_API_KEY: str = ""
    GROQ_BASE_URL: str = "https://api.groq.com/openai/v1"
    GROQ_MODEL: str = "llama-3.3-70b-versatile"

    EDUCATION_SEARCH_PROVIDER: str = "duckduckgo"
    TAVILY_API_KEY: str = ""
    BRAVE_SEARCH_API_KEY: str = ""

    JWT_SECRET_KEY: str = "jwt-secret-change-me"
    JWT_ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 1440

    UPLOAD_DIR: str = "./uploads"
    MAX_UPLOAD_SIZE_MB: int = 10

    DEFAULT_COHORT_NAME: str = "YZTA Grup 92"


settings = Settings()
