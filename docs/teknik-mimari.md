# CareerTalent AI — Teknik Mimari

> **Hedef:** YZTA Bootcamp öğrencileri (pilot)  
> **Stack (aktif):** Plan A — FastAPI (backend API) + Laravel (frontend UI)  
> **Alternatif (yedek):** Plan B — Laravel ana uygulama + Python yan servis (AI/parse)  
> **Son güncelleme:** 2026-06-29

---

## Mimari Karar ve Geçiş Planı

Takım **Plan A ile başlar**. Plan B değerlendirilmiş alternatiftir; Sprint 1 veya Sprint 2 sonrası gerekirse geçiş yapılabilir.

| | Plan A (aktif) | Plan B (yedek) |
|---|----------------|----------------|
| **Yapı** | FastAPI iş mantığı + Laravel arayüz | Laravel auth/DB/UI + ince Python worker |
| **Ne zaman iyi?** | API-first hikaye, Python merkezli ML | Tek auth, daha hızlı demo, daha az koordinasyon |
| **Risk** | Çift auth, API sözleşmesi, iki deploy | İki runtime (ama Python ince kalır) |

### Geçiş tetikleyicileri (A → B)

Sprint 1 veya Sprint 2 retrosunda aşağıdakilerden **ikisi veya fazlası** varsa Plan B değerlendirilir:

- JWT + Laravel session entegrasyonu Sprint 1'i blokluyor
- API sözleşmesi uyumsuzlukları sprint'i yavaşlatıyor
- CV upload proxy veya çift migration bakımı ağır geliyor
- Demo tarihi yakın ve panel özellikleri auth'tan geride kalıyor

### Geçiş yapılırsa (özet)

1. Auth ve iş verisi Laravel + PostgreSQL'e taşınır (Breeze/Sanctum).
2. FastAPI tam API yerine **yan servis** olur: CV parse, Gemini, embedding, gap skoru.
3. Laravel `CareerTalentApiClient` internal worker endpoint'lerine döner (`/internal/parse-cv` vb.).
4. `archive/` ve mevcut FastAPI servis kodu referans olarak kalır; veri migration planı Döne yazar.

> **Not:** Bu geçiş zorunlu değil. Plan A sorunsuz ilerlerse değiştirmeye gerek yok.

---

## 1. Neden İki Katman? (Plan A)

| Katman | Sorumluluk | Neden |
|--------|------------|-------|
| **FastAPI** (`backend/`) | API, veritabanı, kuyruk, Gemini, CV parse | Python ekosistemi: ML, PDF, LangChain |
| **Laravel** (`frontend/`) | Tanıtım sitesi, panel, Livewire UI | Takımın PHP/Laravel deneyimi, hızlı arayüz |

Bootcamp takımı: Döne + Yiğit backend Python; Buse + Bithanya arayüz Laravel.

---

## 2. Büyük Resim

```
Kullanıcı (tarayıcı)
        │
        ▼
┌───────────────────────────────────────┐
│     LARAVEL — frontend/ (:8080)      │
│  ┌─────────────┐  ┌────────────────┐  │
│  │ Tanıtım     │  │ Panel          │  │
│  │ (Blade)     │  │ /panel/*       │  │
│  │ /           │  │ Livewire       │  │
│  └─────────────┘  └────────────────┘  │
│                  │ HTTP (Guzzle)        │
└──────────────────┼──────────────────────┘
                   ▼
┌───────────────────────────────────────┐
│     FASTAPI — backend/ (:8000)       │
│  Router → Service → SQLAlchemy Model  │
│                  │                    │
│     Celery worker → Gemini API       │
└──────────────────┬────────────────────┘
                   ▼
         PostgreSQL + Redis
```

### Kısa sözlük

| Terim | Anlamı |
|-------|--------|
| **Blade** | Laravel HTML şablonları (tanıtım + panel kabuğu) |
| **Livewire** | Sayfa yenilemeden panel bileşenleri |
| **CareerTalentApiClient** | Laravel'den FastAPI'ye HTTP istemcisi |
| **Router (FastAPI)** | REST endpoint tanımları |
| **Celery** | Arka plan iş kuyruğu (CV analizi) |
| **SQLAlchemy** | FastAPI tarafında ORM |

---

## 3. Teknoloji Listesi

| Katman | Araç |
|--------|------|
| Backend API | FastAPI, Pydantic, SQLAlchemy, Alembic |
| Arka plan iş | Celery + Redis |
| CV PDF | pdfplumber |
| Yapay zeka | LangChain + Gemini API |
| Frontend | Laravel 13, Blade, Livewire 3, Tailwind (Vite) |
| Panel layout | **A** — sidebar + tek sayfa (özet, CV, yol haritası, eğitim önerileri) |
| Frontend oturum | SQLite (yalnızca session/cache) |
| Veritabanı (iş verisi) | PostgreSQL (prod) / SQLite (lokal backend) |
| Auth | JWT (FastAPI) — Sprint 1; Laravel session + token |
| Test | pytest (backend), PHPUnit (frontend) |

---

## 4. Klasör Yapısı

```
careertalent-ai/
├── backend/                    # FastAPI API
│   ├── app/
│   │   ├── api/v1/             # REST router'lar (Sprint 1+)
│   │   ├── core/config.py
│   │   ├── models/             # SQLAlchemy modeller
│   │   ├── services/           # İş mantığı, Gemini
│   │   └── tasks/              # Celery job'lar
│   ├── tests/
│   └── requirements.txt
├── frontend/                   # Laravel UI
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Marketing/      # Tanıtım (herkese açık)
│   │   │   └── App/            # Panel
│   │   ├── Livewire/           # Etkileşimli bileşenler
│   │   └── Services/
│   │       └── CareerTalentApiClient.php
│   ├── resources/views/
│   │   ├── marketing/
│   │   └── app/
│   └── routes/web.php
├── data/roles/                 # Meslek JSON (seed kaynağı)
├── docs/
└── archive/                    # Eski Streamlit / monolith denemeleri
```

### Katman kuralı

**Backend:**
```
HTTP Request → Router → Service → Model (SQLAlchemy)
                              ↓
                         Celery Task
                              ↓
                         GeminiService
```

**Frontend:**
```
Route → Controller → Blade/Livewire
              ↓
       CareerTalentApiClient → FastAPI
```

Controller ince kalır; hesaplama FastAPI Service'te olur.

---

## 5. URL Haritası

### Laravel (kullanıcıya görünen)

| URL | Sayfa |
|-----|-------|
| `/` | Ana sayfa |
| `/features` | Özellikler |
| `/how-it-works` | Nasıl çalışır |
| `/bootcamp` | YZTA iş birliği |
| `/panel` | Öğrenci özeti |
| `/panel/cv` | CV yükle (Sprint 1) |
| `/panel/kariyer` | Hedef meslek (Sprint 2) |
| `/panel/yol-haritasi` | Haftalık görevler (Sprint 2) |
| `/panel/egitimler` | Eğitim/sertifika önerileri, harici yönlendirme (Sprint 2) |
| `/panel/sohbet` | Kariyer asistanı (Sprint 3) |
| `/mentor/*` | Mentör paneli (Sprint 3) |

### FastAPI (Laravel'in çağırdığı)

| Endpoint | Açıklama |
|----------|----------|
| `GET /health` | Sağlık kontrolü |
| `POST /api/v1/auth/register` | Kayıt (Sprint 1) |
| `POST /api/v1/auth/login` | Giriş (Sprint 1) |
| `POST /api/v1/cv/upload` | CV yükleme (Sprint 1) |
| `GET /api/v1/careers` | Meslek listesi (Sprint 2) |
| `GET /api/v1/learning-resources` | Eğitim önerileri + filtre (Sprint 2) |
| `POST /api/v1/chat` | Kariyer asistanı (Sprint 3) |

---

## 6. Veritabanı (FastAPI tarafı)

| Tablo | Ne tutar? |
|-------|-----------|
| `users` | Kullanıcı + `role`, `cohort_id` |
| `cohorts` | YZTA Grup 92 gibi sınıflar |
| `cv_documents` | PDF yolu, durum |
| `user_profiles` | Çıkarılmış yetenekler (JSON) |
| `career_roles` | Meslek tanımları |
| `user_career_goals` | Hedef meslek |
| `skill_gaps` | Eksikler + readiness_score |
| `roadmaps` / `roadmap_weeks` / `roadmap_tasks` | Yol haritası |
| `learning_resources` | Harici eğitim önerileri (MVP: seed; Faz 2: DB) |
| `chat_sessions` / `chat_messages` | Sohbet |
| `job_postings` / `job_matches` | Faz 2 |

Laravel SQLite yalnızca oturum ve cache içindir; iş verisi FastAPI PostgreSQL'de tutulur.

---

## 7. Modüller ve Akışlar

### CV modülü
1. Öğrenci Laravel panelinden PDF yükler
2. Laravel dosyayı `POST /api/v1/cv/upload` ile FastAPI'ye iletir
3. FastAPI Celery task başlatır: pdfplumber → Gemini → profil JSON
4. Livewire bileşeni durumu polling ile gösterir

### Kariyer modülü
1. FastAPI meslek listesini döner
2. GapAnalysisService hazırlık % hesaplar
3. Laravel Livewire eksikleri listeler

### Yol haritası modülü
1. `RoadmapService` şablon + Gemini kişiselleştirme
2. `RoadmapView` haftalık görevleri gösterir
3. Görev tamamlanınca skor güncellenir

### Eğitim önerileri modülü (kurs sağlamıyoruz)

**Ürün kuralı:** CareerTalent kendi kurs içeriği sunmaz. Gap analizine göre mevcut kaynakları önerir ve harici siteye yönlendirir.

| Faz | Ne yapılır? |
|-----|-------------|
| **MVP (Sprint 2)** | Statik/seed kaynak listesi; yetenek → kaynak eşlemesi; harici link |
| **Filtreler** | Ücretsiz / ücretli; fiyat aralığı (0–500 ₺, 500–2000 ₺, 2000+ ₺); sertifika rozeti |
| **Faz 2+** | Udemy/Coursera partner API veya embed (anlaşma sonrası) |

**Öneri türleri:** video kurs, MOOC, sertifika sınavı (AWS, Google vb.), dokümantasyon, bootcamp dışı kaynaklar.

**Akış:**
1. `skill_gaps` hangi yeteneklerin eksik olduğunu belirler
2. `LearningResourceService` (FastAPI) gap → kaynak listesi döner (`price_type`, `price_min`, `price_max`, `has_certificate`, `external_url`, `provider`)
3. Laravel panelde filtreler (Livewire) + "Siteye git" yönlendirme
4. Tıklama analytics (opsiyonel): hangi kaynak daha çok açıldı

**Veritabanı (Faz 2 tablo önerisi):**

| Tablo | Ne tutar? |
|-------|-----------|
| `learning_resources` | Başlık, provider, url, price_type, fiyat aralığı, sertifika flag, skill_tags (JSON) |
| `resource_clicks` | user_id, resource_id, timestamp (opsiyonel) |

### Sohbet modülü
1. Livewire mesaj gönderir → FastAPI chat endpoint
2. Gemini kullanıcı profili + yol haritası bağlamında cevap verir

---

## 8. Takım İş Bölümü

| Kişi | Rol | Sorumluluk |
|------|-----|------------|
| **Buse Batan** | Scrum Master + Frontend + Mimari | Laravel UI, Livewire, mimari doküman |
| **Bithanya Abraham Haile** | Frontend + Sunum | UI/UX, tanıtım tasarımı, demo |
| **Döne Sakız** | Backend | FastAPI router, auth, migration, Celery, Gemini |
| **Yiğit Dede** | Veri & Analiz + Product Owner  | CV parse, scraper, embedding, gap analizi |

### İletişim kuralı

- **Frontend:** `frontend/resources/views/`, `frontend/app/Livewire/`
- **Backend:** `backend/app/api/`, `backend/app/services/`, `backend/app/models/`
- **API sözleşmesi:** Döne yazar, Buse Laravel `CareerTalentApiClient` ile tüketir
- **Veri:** Yiğit `data/` + parse mantığı → Döne servislerine entegre eder

---

## 9. Sprint Planı (YZTA Bootcamp 2026)

> Detaylı plan, günlük not ve sprint sonu raporu: [docs/bootcamp-takvimi.md](bootcamp-takvimi.md) ve [docs/sprintler/](sprintler/)

| Sprint | Tarih | Odak |
|--------|-------|------|
| Faz 0 | 12 Haziran | Takım kurulumu |
| **Sprint 1** | 19 Haz – 5 Tem | Auth, CV upload, tanıtım sitesi |
| **Sprint 2** | 6 Tem – 19 Tem | Kariyer, gap, yol haritası |
| Soru-cevap | 6 Tem 20:00 – 20 Tem 20:00 | Mentor soruları (paralel) |
| **Sprint 3** | 20 Tem – 2 Ağu | Sohbet, mentör, sunum |
| Değerlendirme | 3 – 13 Ağu | Jüri, son fix |
| Top 10 | 14 Ağu | Sunum |

### Sprint 1 (19 Haz – 5 Tem 2026)
- [ ] **Döne:** FastAPI auth, migration, CV upload endpoint, Celery
- [ ] **Buse:** Panel layout, `CareerTalentApiClient`, routing
- [ ] **Bithanya:** Tanıtım sayfaları tasarımı
- [ ] **Yiğit:** CV profil JSON şeması, `data/roles` seed
- [ ] **Döne + Yiğit:** CV parse pipeline
- [ ] **Buse (retro):** Plan A sorunsuz mu? Değilse Plan B geçişi için not al (auth/API sürtünmesi)

### Sprint 2 (6 Tem – 19 Tem 2026)
- [ ] **Yiğit:** Gap analizi, scraper iskeleti
- [ ] **Döne:** careers + roadmap API
- [ ] **Buse:** Livewire kariyer + yol haritası
- [ ] **Bithanya:** Hazırlık % UI polish
- [ ] **Takım (retro):** Plan A devam mı, Plan B'ye geçiş mi? Tetikleyici checklist'e bak

### Sprint 3 — Son Sprint (20 Tem – 2 Ağu 2026)
- [ ] **Döne:** Chat + mentör API
- [ ] **Buse:** Sohbet + mentör panel Livewire
- [ ] **Yiğit:** İş ilanı eşleştirme
- [ ] **Bithanya:** Bootcamp sunumu ve demo

---

## 10. Kurulum

```bash
# Backend
cd backend && cp .env.example .env
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8000

# Frontend (ayrı terminal)
cd frontend && cp .env.example .env
composer install && php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm install && npm run build
php artisan serve --port=8080
```

Ortam değişkenleri:

| Dosya | Anahtar |
|-------|---------|
| `backend/.env` | `DEEPSEEK_API_KEY`, `DATABASE_URL` |
| `frontend/.env` | `CAREERTALENT_API_URL=http://localhost:8000` |

### Prod tanıtım sitesi (landing statik)

Tek kaynak: `frontend/resources/views/marketing/*.blade.php`

| Ortam | Marketing (`/`, `/ozellikler`, …) | Panel (`/panel`) |
|-------|-------------------------------------|------------------|
| Lokal dev | Laravel Blade (`php artisan serve :8080`) | Aynı |
| Prod | nginx → `landing/` statik | nginx → Laravel proxy |

```bash
bash scripts/build-landing.sh          # Blade → landing/
docker compose --profile prod up nginx  # :80 statik + proxy
```

Nginx config: `deploy/nginx/careertalent.conf` — `landing/` dosyalarını elle düzenlemeyin; drift önlemek için export komutunu kullanın.

---

## 11. Ürün değeri (sürümlü dokümantasyon)

Ürün kararları (problem, çözüm, değer önerisi, rakip farkı) **versiyonlu** tutulur. Her güncelleme yeni `.md` dosyası; eskiler silinmez.

- İndeks: [docs/urun/README.md](urun/README.md)
- Güncel sürüm: [v001 — 2026-06-29](urun/2026-06-29-v001-urun-degeri.md)

---

## 12. Arşiv

`archive/` içinde eski Streamlit MVP ve Laravel monolith denemesi referans olarak duruyor. Aktif geliştirme `backend/` + `frontend/` üzerinden yapılır.

---

*YZTA Bootcamp Grup 92 — CareerTalent AI*
