# Sprint 1 — İlk Sprint

| | |
|---|---|
| **Tarih** | 19 Haziran – 5 Temmuz 2026 |
| **Süre** | ~17 gün |
| **Hedef** | Çalışan iskelet: auth, CV yükleme başlangıcı, tanıtım sitesi, API sözleşmesi v0 |
| **Mimari** | Plan A (FastAPI + Laravel) |

---

## Plan (sabit)

### Sprint hedefi (tek cümle)

Öğrenci kayıt olup CV yükleyebilsin; backend parse işini kuyruğa alsın; tanıtım sitesi canlı görünsün.

### Görev dağılımı

| Görev | Sorumlu | Bitti mi? |
|-------|---------|-----------|
| FastAPI auth (register/login JWT) | Döne | ☐ |
| DB migration (users, cohorts, cv_documents) | Döne | ☐ |
| CV upload endpoint + Celery task iskeleti | Döne | ☐ |
| `docs/openapi.yaml` v0 (auth + cv) | Döne | ☐ |
| Panel layout A + routing (`/panel/*`) | Buse | ☐ |
| `CareerTalentApiClient` (health, login, upload) | Buse | ☐ |
| Tanıtım: ana sayfa + özellikler | Bithanya | ☐ |
| CV profil JSON şeması | Yiğit | ☐ |
| `data/roles` seed (5 meslek) | Yiğit | ☐ |
| CV parse mantığı (pdf → metin → Gemini) | Döne + Yiğit | ☐ |

### Kabul kriterleri (Definition of Done)

- [ ] `GET /health` ve `POST /api/v1/auth/login` çalışıyor
- [ ] Laravel panel backend durumunu gösteriyor
- [ ] PDF yükleme FastAPI'ye ulaşıyor, job `pending` → `processing` dönüyor
- [ ] Tanıtım `/` ve `/ozellikler` 200 dönüyor
- [ ] En az 1 backend pytest + 1 frontend PHPUnit yeşil

### Mimari retro (5 Temmuz — sprint kapanışı)

> **Plan B geçişi?** Sprint 1 sonunda [teknik-mimari.md](../teknik-mimari.md#mimari-karar-ve-geçiş-planı) tetikleyici checklist'i doldur.

| Tetikleyici | Evet/Hayır | Not |
|-------------|------------|-----|
| Çift auth blokajı | | |
| API uyumsuzluğu | | |
| Upload proxy sorunu | | |
| Demo baskısı | | |

**Karar:** ☐ Plan A devam ☐ Plan B değerlendir ☐ Ertele

---

## Günlük notlar

| Tarih | Kim | Ne yapıldı? |
|-------|-----|-------------|
| 19.06 | | Sprint kickoff |
| 29.06 | | Repo: Plan A yapısı (backend/ + frontend/), mimari doküman güncellendi |
| | | |
| | | |

---

## Sprint sonu raporu

> **Teslim tarihi:** 5 Temmuz 2026  
> Şablon: [sprint-rapor-sablonu.md](sprint-rapor-sablonu.md)

### Özet

_Doldurulacak_

### Tamamlanan işler

| Görev | Sorumlu | Kanıt |
|-------|---------|-------|
| | | |

### Demo durumu (5 Temmuz)

| Akış | Durum |
|------|-------|
| Tanıtım sitesi | ☐ |
| Kayıt / giriş | ☐ |
| CV yükleme | ☐ |

### Mimari karar (Sprint 1 retro)

- Plan A / Plan B kararı: _
- Gerekçe: _

---

*Durum: Aktif (19 Haz – 5 Tem 2026)*
