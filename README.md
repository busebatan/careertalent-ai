# CareerTalent AI

**YZTA Bootcamp 2026 | Grup 92**

Repo: https://github.com/busebatan/careertalent-ai

Önceki repo: https://github.com/donesakizz/TalentCareerAI

**Canlı ortam:**
- Tanıtım: https://careertalent.ygtlabs.ai/
- Panel: https://careertalent.ygtlabs.ai/panel

---

## 1. Ürün Fikri ve Roller

### Takım İsmi

**Grup 92** (YZTA Yapay Zeka ve Teknoloji Akademisi Bootcamp 2026)

### Takım Rolleri

| İsim | Rol | Sprint odak alanı |
|------|-----|-------------------|
| Buse Batan | Scrum Master + Frontend + Teknik Mimari | Laravel panel, mimari, sprint koordinasyonu |
| Bithanya Abraham Haile | Frontend + Sunum | Tanıtım sitesi, UI/UX, demo sunumu |
| Döne Sakız | Backend | FastAPI, API, veritabanı, Celery |
| Yiğit Dede | Veri & Analiz + Product Owner | CV parse, roller kataloğu, gap algoritması |

### Ürün İsmi

**CareerTalent AI** — Kariyer hazırlık işletim sistemi

### Ürün Açıklaması

YZTA bootcamp ve benzeri programlardan mezun olan veya mezun olmaya hazırlanan öğrenciler CV'lerini objektif okuyamıyor, ChatGPT ile tek seferlik metin alıyor ve "şimdi başvurabilirim" ile "eksikleri kapatınca ulaşırım" meslekleri karıştırıyor.

**CareerTalent AI**, PDF CV'yi analiz ederek ölçülebilir **readiness skoru**, **kariyer merdiveni (A/B/C)** ve **haftalık yol haritası** üretir. Kendi kurs sunmaz; eksik yetenekler için harici eğitim ve sertifika kaynaklarına yönlendirir.

**Pitch:** ChatGPT kariyer koçu verir; CareerTalent ölçülebilir hazırlık, kariyer merdiveni, haftalık plan ve pazar gerçekliği verir.

**Mimari karar (pivot notu):** Sprint 1-2 **Plan A** (FastAPI backend + Laravel frontend) ile başlandı. Gerekirse Sprint 1 veya 2 sonrası **Plan B** (Laravel ana + Python worker) değerlendirilir. Detay: [Teknik Mimari](docs/teknik-mimari.md#mimari-karar-ve-geçiş-planı).

### Ürün Özellikleri

| Özellik | Açıklama | Sprint / Faz | Durum (5 Tem) |
|---------|----------|--------------|---------------|
| Akıllı CV ayrıştırma | PDF → metin → Gemini ile profil çıkarımı | Sprint 1 | Kısmen (API var, auth/kuyruk yok) |
| Tanıtım sitesi | Marketing rotaları + i18n | Sprint 1 | İskelet (6 sayfa placeholder) |
| Öğrenci paneli | `/panel/*` Layout A | Sprint 1 | İskelet (demo veri) |
| CV oluşturucu | Harvard format, TR/EN şablon | Sprint 1 | UI hazır |
| Kariyer merdiveni | A (≥%70), B (%40-69), C (<%40) | Sprint 2 | Demo + API iskeleti |
| Readiness skoru | Rol başına hazırlık yüzdesi | Sprint 2 | Planlı |
| SWOT (kanıtlı) | CV'den S/W, pazardan O/T | Sprint 2 | Planlı |
| Haftalık yol haritası | Gap → görev → skor güncelleme | Sprint 2 | Demo |
| Eğitim / sertifika önerisi | Ücretsiz/ücretli filtre, harici link | Sprint 2-3 | Demo seed |
| Yetenek pasaportu | Proje/GitHub/sertifika kanıtlarıyla skill proof | Sprint 2 | Demo panel |
| AI mülakat simülasyonu | Teknik/HR/vaka sorusu + demo skor/geri bildirim | Sprint 2 | Demo panel |
| Başvuru takip CRM'i | Kaydedildi/başvuruldu/mülakat aşamaları | Sprint 2 | Demo panel |
| İş radarı ve gap uyarısı | Hedef role göre ilan sinyali, maaş, eksik yetenek | Sprint 2 | Demo panel |
| Mentor değerlendirme pazarı | CV/portfolio/mülakat review paketleri | Sprint 3 | Demo panel |
| İlan eşleştirme | Semantic uyum skoru | Faz 2 | Panel iskeleti |
| Kariyer sohbet ajanı | Bağlamlı LangChain asistan | Sprint 3 | «Yakında» |
| Mentör / cohort paneli | Kim takıldı, hazırlık özeti | Sprint 3 | Planlı |

### Hedef Kitle

| Segment | İhtiyaç | CareerTalent karşılığı |
|---------|---------|-------------------------|
| **Birincil:** Bootcamp / YZTA öğrencileri | Mezuniyet sonrası hangi role hazırım, ne eksik? | Merdiven + gap + haftalık plan |
| **İkincil:** Kariyer değiştiren junior adaylar | CV'yi objektif okuma, süreklilik | Kalıcı panel, skor takibi |
| **B2B (vizyon):** Bootcamp / üniversite / kariyer merkezi | Cohort'ta kim geride kaldı? | Mentör dashboard (Sprint 3) |
| **Faz 2:** Aktif iş arayanlar | İlana uyum + eksik kapatma planı | İlan eşleştirme skoru |

### Product Backlog

Öncelik: **MoSCoW** (Must / Should / Could). Sprint ataması Product Owner (Yiğit) + SM (Buse) ile yapılır.

| ID | User Story | Öncelik | Sprint | Durum (5 Tem) |
|----|------------|---------|--------|---------------|
| US-01 | Öğrenci olarak kayıt olup giriş yapabilmeliyim | Must | 1 | Devam (UI iskelet) |
| US-02 | PDF CV yükleyebilmeliyim; iş kuyruğa alınmalı | Must | 1 | Kısmen (senkron API, Celery yok) |
| US-03 | Tanıtım sitesinde ürünü görebilmeliyim | Must | 1 | İskelet (kısmi içerik) |
| US-04 | Panelde backend/API sağlık durumunu görebilmeliyim | Must | 1 | Tamamlandı |
| US-05 | CV'den yapılandırılmış profil çıkarılmalı | Must | 1-2 | Kısmen (`/cv/analyze`) |
| US-06 | 5 hedef meslek listelenmeli (`data/roles`) | Must | 2 | Tamamlandı (seed) |
| US-07 | Seçtiğim meslek için gap + readiness % görmeliyim | Must | 2 | Planlı (panel demo) |
| US-08 | Haftalık yol haritası ve görevler oluşmalı | Must | 2 | Planlı |
| US-09 | Eksik yetenek için filtrelenmiş eğitim linki görmeliyim | Should | 2 | Demo |
| US-10 | Görev tamamlanınca skor güncellenmeli | Should | 2 | Planlı |
| US-11 | Bağlamlı kariyer sohbeti kullanabilmeliyim | Could | 3 | Planlı |
| US-12 | Mentör cohort özetini görebilmeliyim | Could | 3 | Planlı |
| US-13 | Gerçek iş ilanına uyum skoru alabilmeliyim | Could | Faz 2 | İskelet |
| US-14 | Yeteneklerimi kanıt linkleriyle pasaport halinde gösterebilmeliyim | Should | 2 | Demo panel |
| US-15 | Rol bazlı mülakat sorusu çözüp demo geri bildirim alabilmeliyim | Should | 2 | Demo panel |
| US-16 | Başvurularımı aşama bazlı takip edebilmeliyim | Should | 2 | Demo panel |
| US-17 | Hedef rol ilanlarını uyum ve gap uyarısıyla görebilmeliyim | Should | 2 | Demo panel |
| US-18 | Mentor review paketi seçip demo talep oluşturabilmeliyim | Could | 3 | Demo panel |

Detaylı sprint görevleri: [Sprint 1](docs/sprintler/sprint-1-ilk-sprint.md) · [Sprint 2](docs/sprintler/sprint-2-ikinci-sprint.md) · [Sprint 3](docs/sprintler/sprint-3-son-sprint.md)

---

## 2. Sprint Süreçleri ve Raporlar

### Backlog Dağıtma Mantığı

1. **Sprint hedefi** tek cümle ile sabitlenir (SM + PO).
2. Backlog maddeleri **Must** önce, **Should** sonra, **Could** en son sprint'e atanır.
3. Görevler **uzmanlığa** göre dağıtılır: Backend (Döne), Frontend (Buse/Bithanya), Veri/PO (Yiğit).
4. Her görevde **kabul kriteri** ve **kanıt** (PR, test, URL) tanımlanır.
5. Sprint sonunda tamamlanmayan **Should/Could** maddeler bir sonraki sprint'e taşınır; **Must** taşınırsa retro'da sebep yazılır.

Sprint board: [GitHub Issues](https://github.com/busebatan/careertalent-ai/issues)

---

### Sprint 0 — Takım Kurulumu (12 Haziran 2026)

| Alan | Özet |
|------|------|
| **Hedef** | Takım, repo, iletişim, ilk mimari karar |
| **Ürün durumu** | Proje adı ve Plan A onaylandı |
| **Review** | CareerTalent AI fikri bootcamp brief ile uyumlu bulundu |
| **Retro** | 4 kişilik takımda frontend/backend ayrımı net; Plan B yedek olarak kayıtlı |

Detay: [sprint-0-takim-kurulumu.md](docs/sprintler/sprint-0-takim-kurulumu.md)

---

### Sprint 1 — İlk Sprint (19 Haziran – 5 Temmuz 2026)

**Sprint hedefi:** Öğrenci kayıt olup CV yükleyebilsin; backend parse işini kuyruğa alsın; tanıtım sitesi canlı görünsün.

#### Daily Scrum Notları

| Tarih | Kim | Ne yapıldı? | Engel |
|-------|-----|-------------|-------|
| 19.06 | Tüm takım | Sprint kickoff, hedef ve görev dağılımı | — |
| 29.06 | Buse | Plan A repo yapısı (`backend/` + `frontend/`), mimari doküman | — |
| 29.06 | Bithanya | Marketing layout, ana sayfa, özellikler, nasıl çalışır | 6 alt sayfa henüz placeholder |
| 29.06 | Döne | FastAPI health, CV analyze endpoint, pytest | Auth ve Celery eksik |
| 29.06 | Yiğit | `data/roles` 5 meslek seed, career ladder servis testleri | — |
| 05.07 | Tüm takım | Sprint kapanış; README ve sprint raporu güncellendi | Auth + tam marketing içeriği Sprint 1 hedefinde kaldı |

#### Sprint Board Updates

| Görev | Sorumlu | Durum | Not |
|-------|---------|-------|-----|
| FastAPI auth (JWT) | Döne | To Do | Router'da yok |
| CV upload + Celery iskelet | Döne | To Do | Senkron `/cv/analyze` var |
| `docs/openapi.yaml` v0 | Döne | To Do | Henüz oluşturulmadı |
| Marketing layout + rotalar | Bithanya | In Progress | 13 rota; 6'sı placeholder |
| Panel layout `/panel/*` | Buse | In Progress | Demo veri; auth middleware yok |
| `CareerTalentApiClient` | Buse | In Progress | Health + CV analyze bağlı |
| CV profil JSON şeması | Yiğit | In Progress | `extract_profile_from_text` çalışıyor |
| `data/roles` seed (5 meslek) | Yiğit | Done | `bootcamp_roles.json` |
| CV parse (pdf → Gemini) | Döne + Yiğit | Kısmen | `POST /api/v1/cv/analyze` senkron |

#### Ürün Durumu (5 Temmuz 2026)

| Alan | Durum | Kanıt |
|------|-------|-------|
| Tanıtım sitesi | İskelet (kısmi içerik) | Layout + locale + 7 sayfa içerikli; 6 sayfa placeholder; auth demo |
| Panel iskeleti (`/panel/*`) | Kısmen | 12 rota; skor/merdiven `PanelDemoData` |
| FastAPI health | Tamamlandı | `GET /health`, `GET /health/ready` |
| CV analyze API | Kısmen | `POST /api/v1/cv/analyze`, `/analyze-text`; auth ve kuyruk yok |
| Auth (kayıt/giriş) | Devam | Marketing form UI; gerçek backend auth yok |
| Otomatik testler | Kısmen | 6 backend pytest dosyası; ~40 frontend PHPUnit testi |

**Kritik boşluk:** UI iskeleti güçlü; zeka katmanı panelde hâlâ büyük ölçüde demo. Sonraki sıçrama: CV → gerçek profil → skorların kalıcı profile bağlanması.

#### Sprint Review (5 Temmuz)

**Gösterilebilen:**
- Canlı tanıtım: https://careertalent.ygtlabs.ai/
- Canlı panel: https://careertalent.ygtlabs.ai/panel
- Tanıtım iskeleti: ana sayfa, özellikler, nasıl çalışır, bootcamp, meslek sihirbazı
- Panel: kariyer merdiveni, CV oluştur, ilan eşleştirme (demo)
- API health ve CV analyze (senkron, auth'suz demo)

**Gösterilemeyen / eksik:**
- Kayıt/giriş uçtan uca
- PDF yükleme → Celery kuyruk → kalıcı profil
- Fiyatlandırma, SSS, iletişim vb. placeholder sayfaların gerçek içeriği

#### Sprint Retrospective (5 Temmuz)

| İyi gitti | İyileştirilecek | Aksiyon |
|-----------|-----------------|---------|
| Plan A repo yapısı ve dokümantasyon disiplini | İki stack koordinasyonu (Laravel ↔ FastAPI) | OpenAPI v0 Sprint 2 başında tamamlanacak |
| Marketing + panel UI hızlı ilerledi | Tanıtım "tamamlandı" algısı; 6 sayfa boş | Sprint 2'ye taşınan içerik backlog'u |
| Test altyapısı kuruldu | Skorlar demo; güven riski | Sprint 2'de gerçek parse → skor bağlantısı |
| CV analyze API erken geldi | Auth ve kuyruk Sprint 1 hedefinde kaldı | Sprint 2 Must: JWT + kalıcı CV kaydı |

**Mimari retro (Plan A / Plan B):**

| Tetikleyici | Evet/Hayır | Not |
|-------------|------------|-----|
| Çift auth blokajı | Hayır | Henüz auth implementasyonu yok |
| API uyumsuzluğu | Kısmen | `openapi.yaml` v0 eksik |
| Upload proxy sorunu | Hayır | Panel → API analyze çalışıyor |
| Demo baskısı | Evet | Panel zengin; backend auth/kuyruk geride |

**Karar:** Plan A devam (Sprint 2 başında Plan B checklist tekrar değerlendirilecek)

Detay: [sprint-1-ilk-sprint.md](docs/sprintler/sprint-1-ilk-sprint.md)

---

### Sprint 2 — İkinci Sprint (6 Temmuz – 19 Temmuz 2026) — Planlı

**Sprint hedefi:** Öğrenci hedef mesleğini seçsin; eksik yetenekler ve haftalık yol haritasını görsün; hazırlık % panelde görünsün.

**Sprint 2 demo modu:** FastAPI entegrasyonu şimdilik kapalı; öğrenci panelindeki CV analizi ve yeni gelir odaklı sayfalar demo veriyle çalışır. Yeni öğrenci sayfaları: `/panel/is-radari`, `/panel/basvuru-takibi`, `/panel/yetenek-pasaportu`, `/panel/mulakat-simulasyonu`, `/panel/mentor-degerlendirme`, `/panel/sohbet`.

_(Sprint 2 Daily Scrum, Board Updates, Review ve Retro ilerledikçe buraya eklenecek.)_

Detay: [sprint-2-ikinci-sprint.md](docs/sprintler/sprint-2-ikinci-sprint.md)

---

### Sprint 3 — Son Sprint (20 Temmuz – 2 Ağustos 2026) — Planlı

**Sprint hedefi:** Bağlamlı sohbet, mentör paneli, jüri demo senaryosu.

Detay: [sprint-3-son-sprint.md](docs/sprintler/sprint-3-son-sprint.md)

---

## Geliştirici Notları

### Teknoloji Yığını

| Katman | Klasör | Teknoloji |
|--------|--------|-----------|
| Frontend (UI) | `frontend/` | Laravel 13, Blade, Livewire, Tailwind |
| Backend (API) | `backend/` | FastAPI, SQLAlchemy, Celery |
| Yapay zeka | `backend/` | LangChain, Gemini API |
| ML / benzerlik | `backend/` | NumPy, Scikit-learn (cosine similarity) |
| Veritabanı | `backend/` | PostgreSQL (+ Redis kuyruk) |
| Oturum (UI) | `frontend/` | SQLite (yalnızca session/cache) |

### Mimari

| Katman | Port |
|--------|------|
| FastAPI API | `:8000` |
| Laravel web | `:8080` |

Laravel tanıtım sitesi ve öğrenci panelini sunar. İş mantığı, veritabanı ve yapay zeka **FastAPI** tarafındadır.

### Canlı ortam (demo)

| Yüzey | URL |
|-------|-----|
| Tanıtım (landing) | https://careertalent.ygtlabs.ai/ |
| Öğrenci paneli | https://careertalent.ygtlabs.ai/panel |

### Kurulum (lokal)

```bash
git clone https://github.com/busebatan/careertalent-ai.git
cd careertalent-ai
```

**Backend:**

```bash
cd backend
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# backend/.env içine GEMINI_API_KEY=... ekleyin
uvicorn app.main:app --reload --port 8000
```

Sağlık kontrolü: http://localhost:8000/health

**Frontend:**

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

- Tanıtım (lokal): http://localhost:8080
- Panel (lokal): http://localhost:8080/panel

`frontend/.env` içinde `CAREERTALENT_API_URL=http://localhost:8000` olmalı.

**Docker (opsiyonel):**

```bash
cp .env.example .env
docker compose up
```

### Dokümantasyon

- [Bootcamp Takvimi](docs/bootcamp-takvimi.md)
- [Sprint Raporları](docs/sprintler/README.md)
- [Ürün Değeri v001](docs/urun/2026-06-29-v001-urun-degeri.md)
- [Teknik Mimari](docs/teknik-mimari.md)

### Eski Stack

İlk Streamlit MVP ve eski Laravel monolith denemesi `archive/` klasöründe referans olarak duruyor.

---

*Son güncelleme: 5 Temmuz 2026 | Grup 92 — CareerTalent AI*
