# Sprint 2 — İkinci Sprint

| | |
|---|---|
| **Tarih** | 6 Temmuz – 19 Temmuz 2026 |
| **Süre** | ~14 gün |
| **Hedef** | Kariyer seçimi, gap analizi, yol haritası MVP, hazırlık % göstergesi |
| **Mimari** | Plan A (FastAPI + Laravel) |
| **Durum** | Devam ediyor (14 Temmuz 2026 ara güncelleme) |

---

## Sprint 1'den devralınan durum (5 Temmuz kapanışı)

Sprint 1 tablosu ve backlog [sprint-1-ilk-sprint.md](sprint-1-ilk-sprint.md) dosyasında **değiştirilmeden** duruyor. Özet:

| Alan | Sprint 1 kapanışı | Sprint 2 başlangıcında taşınan |
|------|-------------------|--------------------------------|
| Auth (JWT) | Tamamlanmadı | Must |
| Celery CV kuyruk | Tamamlanmadı | Should |
| `docs/openapi.yaml` v0 | Eksik | Must |
| Marketing 6 placeholder sayfa | Eksik | Should |
| Panel demo veri | Zengin iskelet | Gerçek API'ye bağlama |
| Admin paneli | Planlı | Sprint 2 kapsamı |

**Sprint 1 görselleri (referans):** [Ürün durumu görselleri — Sprint 1](sprint-1-ilk-sprint.md#ürün-durumu-görselleri-sprint-1-teslimi)

---

## Güncel ürün durumu (14 Temmuz 2026)

> Sprint 1 kapanışından bu yana kod tabanı önemli ölçüde ilerledi. Aşağıdaki envanter **canlı ortam + repo** kanıtına dayanır.

**Canlı URL'ler:**
- Tanıtım: https://careertalent.ygtlabs.ai/
- Panel giriş / kayıt: https://careertalent.ygtlabs.ai/panel/login · https://careertalent.ygtlabs.ai/panel/register
- Admin giriş: https://careertalent.ygtlabs.ai/admin/login
- Öğrenci paneli: https://careertalent.ygtlabs.ai/panel
- Admin paneli: https://careertalent.ygtlabs.ai/admin (admin JWT gerekir)
- Eski kısayollar: `/giris` → `/panel/login`, `/kayit` → `/panel/register` (301)

### Katman özeti

| Katman | Sprint 1 (5 Tem) | Bugün (14 Tem) | Delta |
|--------|------------------|----------------|-------|
| Marketing UI | ~55% | ~70% | Meslek sihirbazı, auth formları gerçek; 6 sayfa hâlâ placeholder |
| Panel UI | ~70% demo | ~85% gerçek API | Auth, CV merkezi, kariyer rotam, görevler, AI asistan, ilan analizi |
| Admin UI | Planlı | ~60% demo layout | 11 rota + demo veri; gerçek cohort verisi yok |
| Backend API | ~40% | ~75% | JWT, Celery, career engine, engagement, CV CRUD |
| Veri / roller | ~60% | ~80% | 5 rol seed + career engine analizi |
| Test | ~50% | ~85% | Frontend 116 PHPUnit yeşil; backend 12 test dosyası (~59 test) |

### Sprint 1'den tamamlanan (Sprint 2 öncesi / hafta 1)

| Özellik | Kanıt |
|---------|-------|
| JWT auth (register/login/me) | `backend/app/api/v1/auth.py`, `frontend/app/Http/Middleware/EnsureApiAuthenticated.php` |
| Laravel ↔ FastAPI oturum köprüsü | `AuthController.php`, `AuthFlowTest.php` |
| Celery CV analiz kuyruğu | `backend/app/tasks/career.py`, `analyze_cv` task |
| Career engine (CV analiz, hedef, görev planı) | `backend/app/services/career_engine.py`, `backend/app/api/v1/career.py` |
| Panel gerçek API entegrasyonu | `CareerTalentApiClient.php`, `RoadmapController.php`, `TasksController.php` |
| Hazırlık % hesaplama | `TaskReadinessCalculator.php` |
| AI kariyer asistanı | `ChatController.php`, `engagement.py` |
| İlan URL analizi (tek ilan) | `job_opportunity.py`, `JobMatchesController.php` |
| Admin panel iskeleti (demo) | `AdminController.php`, `AdminDemoData.php`, 11 rota |
| Ayrı auth yüzeyleri (panel + admin login) | `AuthController.php`, `panel/login`, `admin/login` |
| Canlı deploy | careertalent.ygtlabs.ai |

### Tanıtım sitesi envanteri (14 Temmuz)

| Rota | Durum | Not |
|------|-------|-----|
| `/` | İçerik var | Hero, özellikler, panel önizlemesi, i18n TR/EN |
| `/ozellikler`, `/nasil-calisir`, `/bootcamp` | İçerik var | Lang dosyalarından gerçek metin |
| `/meslekler` | İnteraktif | 4 adımlı sihirbaz + `careers-catalog.json` |
| `/panel/login`, `/panel/register` | Gerçek auth | Panel güvenlik yüzeyi (yeşil tema); FastAPI JWT |
| `/admin/login` | Gerçek auth | Admin güvenlik yüzeyi (amber tema); yalnız `is_admin` |
| `/giris`, `/kayit` | Redirect | 301 → `/panel/login`, `/panel/register` |
| `/fiyatlandirma`, `/galeri`, `/faq`, `/blog`, `/hakkimizda`, `/iletisim` | Placeholder | «İçerik yakında eklenecek» |

### Auth yüzeyleri — ayrı ekran görüntüleri (14 Temmuz)

> Panel ve admin için **ayrı güvenlik yüzeyleri**; marketing `/giris` artık yalnızca redirect. Görseller: `screenshots/sprint-2/auth/`

| Yüzey | URL | Tema | Dosya |
|-------|-----|------|-------|
| Panel giriş | `/panel/login` | Yeşil (öğrenci) | `auth/panel-login.png` |
| Panel kayıt | `/panel/register` | Yeşil (öğrenci) | `auth/panel-register.png` |
| Admin giriş | `/admin/login` | Amber (yönetici) | `auth/admin-login.png` |

**Panel giriş** — https://careertalent.ygtlabs.ai/panel/login

![Panel giriş — güvenli oturum yüzeyi](screenshots/sprint-2/auth/panel-login.png)

**Panel kayıt** — https://careertalent.ygtlabs.ai/panel/register

![Panel kayıt — kariyer profili oluştur](screenshots/sprint-2/auth/panel-register.png)

**Admin giriş** — https://careertalent.ygtlabs.ai/admin/login

![Admin giriş — yönetim alanı](screenshots/sprint-2/auth/admin-login.png)

### Panel envanteri (14 Temmuz)

| Rota | Özellik | Veri kaynağı |
|------|---------|--------------|
| `/panel` | Dashboard | Career API + readiness hesabı |
| `/panel/cv-merkezi` | CV yükle / oluştur / analiz | FastAPI CV + Celery |
| `/panel/kariyer-rotam` | Hedef rol, merdiven, görevler, eğitim | Career engine + education search |
| `/panel/kariyer-rotam/gorevler` | Görev listesi, durum, kanıt | Career tasks API |
| `/panel/ilan-analizi` | Tek ilan URL analizi | `job_opportunity.py` |
| `/panel/basvurularim` | Başvuru CRM | Engagement API |
| `/panel/mulakat-hazirligi` | Mülakat simülasyonu | Engagement API |
| `/panel/yetenek-pasaportu` | Yetenek kanıtları | Career evidence API |
| `/panel/ai-yardimcisi` | Kariyer sohbet | LangChain engagement |
| `/panel/uzmanlardan-destek` | Mentor marketplace | Demo fallback (`PanelDemoData`) |
| `/panel/hesap` | Profil + CV geçmişi | Career profile + CV documents |

Eski Sprint 1 rotaları (`/panel/cv-olustur`, `/panel/yol-haritasi` vb.) yeni isimlere **redirect** edilir.

### Admin envanteri (14 Temmuz)

| Rota | Modül | Veri |
|------|-------|------|
| `/admin` | Dashboard | Demo (`AdminDemoData`) |
| `/admin/ogrenciler` | Öğrenci listesi | Demo |
| `/admin/cohortlar` | Cohort yönetimi | Demo |
| `/admin/readiness` | Readiness analitiği | Demo |
| `/admin/yetenek-pasaportu` | Kanıt onay kuyruğu | Demo |
| `/admin/is-radari` | İlan kaynakları | Demo |
| `/admin/basvurular` | Başvuru funnel | Demo |
| `/admin/mulakatlar` | Soru bankası | Demo |
| `/admin/mentorlar` | Mentor marketplace | Demo |
| `/admin/egitimler` | Eğitim affiliate | Demo |
| `/admin/ayarlar` | Ürün ayarları | Demo |

**Not:** Admin rotaları `auth.api` + `auth.api.admin` middleware ile korunur. İçerik henüz gerçek DB'ye bağlı değil.

### Backend API envanteri (14 Temmuz)

| Grup | Endpoint örnekleri | Durum |
|------|-------------------|-------|
| Health | `GET /health`, `GET /health/ready` | Tamamlandı |
| Auth | `POST /auth/register`, `/login`, `GET /auth/me` | Tamamlandı |
| CV | `POST /cv/analyze`, `/analyze-text`, CRUD documents | Tamamlandı (Celery kuyruk) |
| Career | `POST /career/targets`, `GET .../tasks`, evidence, jobs | Tamamlandı |
| Career roles | `GET /career-roles/` (5 rol) | Tamamlandı |
| Engagement | chat, interview, applications, personal tasks | Tamamlandı |
| Panel (legacy demo) | `GET /panel/dashboard`, `/job-radar`, `/mentors` | Demo fallback; yeni iş `career/*` üzerinden |

**Eksik:** `docs/openapi.yaml` (commit edilmiş sözleşme yok; runtime `/openapi.json` var).

### Test durumu (14 Temmuz)

| Katman | Araç | Sonuç |
|--------|------|-------|
| Frontend | PHPUnit | **116 test, 116 geçti** |
| Backend | pytest | 12 dosya, ~59 test (CI/local ortamda koşulmalı) |

---

## Plan (sabit)

### Sprint hedefi

Öğrenci hedef mesleğini seçsin, eksik yeteneklerini ve haftalık yol haritasını görsün; hazırlık yüzdesi panelde görünsün.

### Görev dağılımı (güncel durum)

| Görev | Sorumlu | Bitti mi? | Kanıt / not |
|-------|---------|-----------|-------------|
| Gap analizi algoritması | Yiğit | ☑ kısmen | `career_ladder_service.py` + AI gap in `career_engine.py` |
| `GapAnalysisService` + API endpoint | Döne | ☑ kısmen | Ayrı servis sınıfı yok; `/career/analysis/*` üzerinden |
| `RoadmapService` + haftalık görev API | Döne | ☑ | `plan_target()` + Celery; `POST /career/targets` |
| İş ilanı scraper iskeleti | Yiğit | ☑ kısmen | Tek URL parse (`job_listing_parser.py`); toplu scraper yok |
| Livewire: kariyer seçici | Buse | ☐ | Paket kurulu; `app/Livewire/` boş, Blade kullanılıyor |
| Livewire: yol haritası görünümü | Buse | ☐ | `RoadmapController` + Blade |
| Livewire: eğitim önerileri | Buse | ☑ kısmen | Filtre UI var; `education_search.py` canlı arama |
| `data/learning_resources` seed | Yiğit | ☐ | Statik seed yok; AI arama ile dinamik |
| Hazırlık % UI + görsel polish | Bithanya | ☑ kısmen | Dashboard + kariyer rotamda %0 gösterimi |
| Admin panel layout + rotalar (`/admin/*`) | Buse | ☑ | 11 rota, middleware, demo veri |
| Admin demo veri + cohort/readiness ekranları | Bithanya | ☑ | `AdminDemoData.php` |
| Admin gerçek veri bağlantısı | Döne + Buse | ☐ | Sprint 2 kalan iş |
| `openapi.yaml` v1 (careers, roadmap) | Döne | ☐ | Runtime OpenAPI var; dosya commit edilmedi |
| JWT auth + kalıcı kullanıcı (Sprint 1 carry) | Döne | ☑ | Sprint 2 hafta 1'de tamamlandı |
| Panel + admin ayrı login/register UI | Bithanya + Buse | ☑ | `/panel/login`, `/panel/register`, `/admin/login` |
| Marketing placeholder sayfaları | Bithanya | ☐ | 6 sayfa hâlâ placeholder |

### Kabul kriterleri

- [x] 5 meslek listeleniyor (`GET /api/v1/career-roles/`)
- [x] Seçilen meslek için gap listesi + readiness_score dönüyor (CV analizi sonrası)
- [x] Haftalık yol haritası / görev listesi oluşturuluyor (`plan_target`)
- [x] Panelde hazırlık % görünüyor
- [ ] Görev tamamlanınca skor güncelleniyor (MVP: kısmen; kanıt akışı var, otomatik yeniden hesap sınırlı)
- [ ] `docs/openapi.yaml` v1 commit edildi
- [ ] Admin gerçek cohort/öğrenci verisi gösteriyor

### Mimari retro (19 Temmuz — sprint kapanışı)

> **Son Plan B karar noktası.** Sprint 2 sonrası geçiş maliyeti artar; checklist mutlaka doldurulmalı.

| Tetikleyici | Evet/Hayır | Not |
|-------------|------------|-----|
| Çift auth blokajı | Hayır | Laravel session + FastAPI JWT köprüsü çalışıyor |
| API uyumsuzluğu | Kısmen | `openapi.yaml` eksik; panel `career/*` ile hizalandı |
| Upload proxy sorunu | Hayır | CV upload + Celery kuyruk stabil |
| Demo baskısı | Kısmen | Admin + mentor/radar demo; çekirdek panel gerçek API |

**Karar:** ☑ Plan A devam ☐ Plan B'ye geç ☐ Kısmi (sadece worker ayrımı)

**Ara gerekçe (14 Tem):** Auth ve career engine Sprint 2 hedeflerinin çoğunu karşıladı. Plan B tetikleyicileri aktifleşmedi.

---

## Günlük notlar

| Tarih | Kim | Ne yapıldı? | Engel / not |
|-------|-----|-------------|-------------|
| 6.07 | Tüm takım | Sprint 2 kickoff; Sprint 1 carry (auth, Celery) önceliklendi | — |
| 8.07 | Döne | JWT auth, CV Celery task, career engine API | — |
| 10.07 | Buse | Panel rotaları yeniden adlandırıldı (`cv-merkezi`, `kariyer-rotam`); API client genişletildi | — |
| 11.07 | Bithanya | Admin panel layout + demo modüller | Admin gerçek veri bekliyor |
| 12.07 | Yiğit | Career ladder + education search entegrasyonu | Toplu job scraper ertelendi |
| 13.07 | Buse | İş planı v002 (B2B cohort SaaS pivot) taslağı | `docs/is-planlari/2026-07-13-v002-iyilestirilmis-is-plani.md` |
| 14.07 | Bithanya + Buse | Panel/admin ayrı auth yüzeyleri (`/panel/login`, `/panel/register`, `/admin/login`); sprint görselleri güncellendi | `/giris` → 301 redirect |

---

## Sprint Board Updates (14 Temmuz ara özeti)

| Kolon | Öğe sayısı | Örnekler |
|-------|------------|----------|
| **Done** | 8 | Auth, Celery CV, career engine, panel API, admin layout, readiness UI, AI chat, ilan analizi |
| **In Progress** | 4 | Admin gerçek veri, openapi v1, marketing placeholder, görev→skor otomasyonu |
| **To Do** | 3 | Livewire geçişi, learning_resources seed, toplu job scraper |

GitHub board: https://github.com/busebatan/careertalent-ai/issues

---

## Sprint sonu raporu (ara — 14 Temmuz)

> **Tam teslim:** 19 Temmuz 2026  
> Şablon: [sprint-rapor-sablonu.md](sprint-rapor-sablonu.md)

### Özet (3–5 cümle)

Sprint 1'den taşınan auth, Celery ve OpenAPI borçlarının büyük kısmı Sprint 2'nin ilk haftasında kapatıldı. Öğrenci paneli artık demo yerine **career engine API** üzerinden CV analizi, hedef rol seçimi, görev planı, hazırlık yüzdesi, AI asistan ve tek-ilan analizi sunuyor. Admin paneli layout ve 11 demo modül ile canlıda; gerçek cohort verisi henüz bağlanmadı. Tanıtım sitesinde auth ve meslek sihirbazı çalışıyor; 6 alt sayfa placeholder. Strateji tarafında iş planı v002 ile B2B cohort SaaS pivotu dokümante edildi.

### Tamamlanan işler (Sprint 1 sonrası kümülatif)

| Görev | Sorumlu | Kanıt |
|-------|---------|-------|
| JWT auth + Laravel oturum köprüsü | Döne + Buse | `auth.py`, `AuthFlowTest.php` |
| Celery CV analiz kuyruğu | Döne | `tasks/career.py` |
| Career engine (analiz, hedef, görev) | Döne + Yiğit | `career_engine.py`, `test_career_engine.py` |
| Panel gerçek API entegrasyonu | Buse | `CareerTalentApiClient.php`, 116 PHPUnit |
| Hazırlık % UI | Bithanya | `panel-dashboard.png`, `TaskReadinessCalculator.php` |
| AI kariyer asistanı | Döne | `ChatController.php`, `engagement.py` |
| Admin panel (demo) | Buse + Bithanya | `AdminController.php`, `admin-dashboard.png` |
| Ayrı auth yüzeyleri (panel + admin) | Bithanya + Buse | `panel-login.png`, `panel-register.png`, `admin-login.png` |
| Canlı deploy güncellemesi | Buse | careertalent.ygtlabs.ai |

### Tamamlanmayan / devam eden

| Görev | Sebep | Hedef |
|-------|-------|-------|
| `docs/openapi.yaml` v1 | Zaman; runtime OpenAPI yeterli görüldü | Sprint 2 kapanış |
| Admin gerçek veri | Demo önce layout kanıtı | Sprint 2 kapanış |
| Marketing 6 placeholder | İçerik üretimi | Sprint 2 Should |
| Livewire bileşenleri | Blade yeterli görüldü | Sprint 3 veya ertele |
| Toplu job scraper | Tek-ilan MVP öncelik | Sprint 3 |

### Demo durumu (14 Temmuz)

| Akış | Çalışıyor mu? | Not |
|------|---------------|-----|
| Tanıtım sitesi | Kısmen | Çekirdek + meslek sihirbazı evet; 6 sayfa placeholder |
| Kayıt / giriş | Evet | `/panel/login`, `/panel/register`, `/admin/login`; `/giris` redirect |
| CV yükleme | Evet | Celery kuyruk + kalıcı document |
| Kariyer seçimi + gap | Kısmen | CV analizi sonrası hedef plan |
| Yol haritası / görevler | Kısmen | AI plan; boş state yeni kullanıcıda |
| Hazırlık % | Evet | Panel dashboard + kariyer rotam |
| AI sohbet | Evet | Bağlamlı asistan |
| İlan analizi (tek URL) | Evet | URL yapıştır → eşleşme |
| Admin paneli | Kısmen | Layout demo; admin login gerekir |
| Mentor / iş radarı | Demo | `PanelDemoData` fallback |

### Ürün durumu özeti

| Katman | Tamamlanma (tahmini) | Açıklama |
|--------|----------------------|----------|
| Marketing UI | ~70% | Auth + sihirbaz; placeholder sayfalar eksik |
| Panel UI | ~85% | Gerçek API; birkaç modül demo fallback |
| Admin UI | ~60% | Layout tamam; veri demo |
| Backend API | ~75% | Career engine tamam; openapi dosyası eksik |
| Veri / roller | ~80% | 5 rol + AI analiz |
| Test | ~85% | Frontend 116/116; backend suite geniş |

### Riskler ve engeller

1. Admin demo zenginliği «cohort yönetimi bitti» algısı yaratabilir; jüriye demo vs gerçek ayrımı net anlatılmalı.
2. `openapi.yaml` eksikliği panel-backend sözleşmesini kırılgan bırakıyor.
3. README (5 Tem) güncel değil; auth/Celery/admin durumu eski.

### Sonraki sprint önceliği (kalan Sprint 2 — max 3)

1. **Admin gerçek veri** (öğrenci, cohort, readiness) + openapi v1 commit
2. Marketing **SSS + fiyatlandırma + iletişim** içeriği
3. Görev tamamlama → readiness skor otomasyonu + kanıt akışı polish

### Mimari karar (ara retro — 14 Tem)

- **Plan A / Plan B:** Plan A devam
- **Gerekçe:** Auth köprüsü ve career engine iki stack ayrımını doğruladı. Plan B maliyeti hâlâ gereksiz.

---

## Ürün durumu görselleri (Sprint 2 — 14 Temmuz)

> Canlı ortam ekran görüntüleri. Admin dashboard yerel render (demo veri); canlı `/admin` admin JWT gerektirir.

### Tanıtım ve güvenli oturum yüzeyleri

**Ana sayfa** — https://careertalent.ygtlabs.ai/

![Sprint 2 tanıtım ana sayfa](screenshots/sprint-2/marketing-ana-sayfa.png)

**Meslek sihirbazı** — https://careertalent.ygtlabs.ai/meslekler

![Sprint 2 meslek sihirbazı](screenshots/sprint-2/meslekler.png)

**Auth (panel + admin)** — ayrı görseller: [Auth yüzeyleri bölümü](#auth-yüzeyleri--ayrı-ekran-görüntüleri-14-temmuz) · `screenshots/sprint-2/auth/`

### Öğrenci paneli (kayıtlı kullanıcı)

**Dashboard** — `/panel`

![Sprint 2 panel dashboard](screenshots/sprint-2/panel-dashboard.png)

**CV Merkezi** — `/panel/cv-merkezi`

![Sprint 2 CV merkezi](screenshots/sprint-2/panel-cv-merkezi.png)

**Kariyer Rotam** — `/panel/kariyer-rotam`

![Sprint 2 kariyer rotam](screenshots/sprint-2/panel-kariyer-rotam.png)

**İlan Analizi** — `/panel/ilan-analizi`

![Sprint 2 ilan analizi](screenshots/sprint-2/panel-ilan-analizi.png)

**AI Yardımcısı** — `/panel/ai-yardimcisi`

![Sprint 2 AI yardımcısı](screenshots/sprint-2/panel-ai-yardimcisi.png)

### Admin paneli (demo veri)

**Dashboard** — `/admin`

![Sprint 2 admin dashboard](screenshots/sprint-2/admin-dashboard.png)

**Readiness Analitiği** — `/admin/readiness`

![Sprint 2 admin readiness](screenshots/sprint-2/admin-readiness.png)

| Ekran | URL | Sprint | Veri |
|-------|-----|--------|------|
| Ana sayfa | `/` | 1→2 | Gerçek |
| Meslek sihirbazı | `/meslekler` | 2 | Gerçek |
| Panel giriş | `/panel/login` | 2 | Gerçek API |
| Panel kayıt | `/panel/register` | 2 | Gerçek API |
| Admin giriş | `/admin/login` | 2 | Gerçek API (admin rol) |
| Panel dashboard | `/panel` | 1→2 | Gerçek API |
| CV merkezi | `/panel/cv-merkezi` | 2 | Gerçek API |
| Kariyer rotam | `/panel/kariyer-rotam` | 2 | Gerçek API |
| AI yardımcısı | `/panel/ai-yardimcisi` | 2 | Gerçek API |
| İlan analizi | `/panel/ilan-analizi` | 2 | Gerçek API |
| Admin dashboard | `/admin` | 2 | Demo |
| Admin readiness | `/admin/readiness` | 2 | Demo |

**Sprint 1 karşılaştırma:** [sprint-1 görselleri](sprint-1-ilk-sprint.md#ürün-durumu-görselleri-sprint-1-teslimi)

### Ekran görüntüsü / video

- Demo URL (canlı): https://careertalent.ygtlabs.ai/panel/login · https://careertalent.ygtlabs.ai/panel/register · https://careertalent.ygtlabs.ai/admin/login · https://careertalent.ygtlabs.ai/panel · https://careertalent.ygtlabs.ai/admin
- Ekran görüntüleri: `screenshots/sprint-2/` · auth ayrı: `screenshots/sprint-2/auth/`
- Video linki (varsa): _

---

*Raporu hazırlayan: Grup 92*  
*Ara güncelleme: 14 Temmuz 2026*

*Durum: Devam ediyor (6 Tem – 19 Tem 2026)*

