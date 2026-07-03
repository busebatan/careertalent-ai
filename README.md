# CareerTalent AI

YZTA Bootcamp 2026 — Grup 92 | **Plan A:** FastAPI backend + Laravel frontend

> **Mimari not:** Sprint 1–2 **Plan A** ile başlanır. Gerekirse Sprint 1 veya 2 sonrası **Plan B** (Laravel ana + Python worker) geçişi değerlendirilir. Detay: [Teknik Mimari](docs/teknik-mimari.md#mimari-karar-ve-geçiş-planı).

## Ne Yapar?

1. CV'ni analiz eder
2. Hedef mesleğini seçtirir
3. Eksik yeteneklerini gösterir
4. Haftalık yol haritası verir
5. Ücretsiz/ücretli eğitim ve sertifika önerir (harici yönlendirme; kendi kursumuz yok)
6. Hazır olunca iş ilanlarıyla eşleştirir (Faz 2)

## Mimari

| Katman | Klasör | Teknoloji | Port |
|--------|--------|-----------|------|
| **Backend (API)** | `backend/` | FastAPI, SQLAlchemy, Celery, Gemini | `:8000` |
| **Frontend (UI)** | `frontend/` | Laravel 13, Blade, Livewire, Tailwind | `:8080` |

Laravel tanıtım sitesi + öğrenci/mentör panelini sunar. İş mantığı, veritabanı ve yapay zeka **FastAPI** tarafındadır.

## Kurulum (lokal)

### 1. Backend (FastAPI)

```bash
cd backend
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
uvicorn app.main:app --reload --port 8000
```

Sağlık kontrolü: http://localhost:8000/health

### 2. Frontend (Laravel)

```bash
cd frontend
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install && npm run build
php artisan serve --port=8080
```

- Tanıtım: http://localhost:8080
- Panel: http://localhost:8080/panel

`frontend/.env` içinde `CAREERTALENT_API_URL=http://localhost:8000` olmalı.

### Docker (opsiyonel)

```bash
cp .env.example .env
docker compose up
```

- API: http://localhost:8000
- Web: http://localhost:8080

Backend `.env` içine `GEMINI_API_KEY=...` ekleyin.

## Dokümantasyon

- [Teknik Mimari](docs/teknik-mimari.md)
- [Bootcamp Takvimi ve Sprint Raporları](docs/bootcamp-takvimi.md)
- [Ürün Değeri ve Fark (sürümlü)](docs/urun/README.md) — güncel: [v001](docs/urun/2026-06-29-v001-urun-degeri.md)

## Eski Stack

Streamlit MVP ve eski Laravel monolith denemesi `archive/` klasöründe referans olarak duruyor.

## Takım

| İsim | Rol | Odak |
|------|-----|------|
| Buse Batan | Scrum Master + Frontend + Teknik Mimari | Laravel arayüz, mimari, sprint yönetimi |
| Bithanya Abraham Haile | Frontend + Sunum | UI/UX, tasarım, bootcamp demosu |
| Döne Sakız | Backend | FastAPI, API, servisler, veritabanı |
| Yiğit Dede | Veri & Analiz + Product Owner | CV parse, scraper, embedding, eşleştirme verisi |
