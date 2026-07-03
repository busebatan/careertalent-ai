"""Uygulama ayarları — .env dosyasından okunur."""

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    APP_NAME: str = "CareerTalent AI"
    APP_ENV: str = "development"
    DEBUG: bool = True
    SECRET_KEY: str = "dev-secret-change-me"

    DATABASE_URL: str = "sqlite:///./careertalent.db"
    REDIS_URL: str = "redis://localhost:6379/0"

    GEMINI_API_KEY: str = ""

    JWT_SECRET_KEY: str = "jwt-secret-change-me"
    JWT_ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 1440

    UPLOAD_DIR: str = "./uploads"
    MAX_UPLOAD_SIZE_MB: int = 10

    DEFAULT_COHORT_NAME: str = "YZTA Grup 92"


settings = Settings()
