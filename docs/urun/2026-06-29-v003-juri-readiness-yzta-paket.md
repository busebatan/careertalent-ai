# CareerTalent AI — Jüri Demosu, Readiness Algoritması ve YZTA Kurum Paketi v003

| | |
|---|---|
| **Sürüm** | v003 |
| **Tarih** | 2026-06-29 |
| **Önceki sürüm** | [2026-06-29-v002-ogrenci-yolculugu-swot-gelir.md](2026-06-29-v002-ogrenci-yolculugu-swot-gelir.md) |
| **Durum** | Taslak (operasyonel plan) |
| **Hazırlayan** | Takım + ürün oturumu |

---

## Önceki sürüme göre değişiklik

| Alan | v002 | v003 (bu sürüm) |
|------|------|-----------------|
| Jüri demosu | v001'de 4 madde checklist | **10 dk senaryo**: persona, ekran sırası, konuşma metni, yedek plan |
| Readiness | %70 / %40 eşikleri (açık karar) | **Algoritma taslağı**: formül, ağırlıklar, tier atama, golden set |
| Gelir | B2B «ilk kapı» genel | **YZTA kurum paketi**: 3 kademe fiyat, kapsam, pilot teklifi |
| Yeni | — | Demo veri seti (`Ayşe Yılmaz`), prova checklist |

---

## 1. Jüri demo senaryosu (10 dakika)

### 1.1 Hedef

Jüri şunu görmeli: **ChatGPT tek seferlik metin verir; CareerTalent ölçülebilir hazırlık + haftalık plan + kanıtlı gap zinciri verir.**

Sunum yapısı [top10-sunum.md](../sprintler/top10-sunum.md) ile uyumlu: 0–1 dk problem, 1–2 dk çözüm, **2–7 dk canlı demo**, 7–10 dk mimari + takım.

### 1.2 Demo personası

| Alan | Değer |
|------|-------|
| Ad | Ayşe Yılmaz |
| Profil | YZTA bootcamp öğrencisi, veri analitiği hattında |
| CV | Panelde hazır demo CV (İstanbul Üniversitesi, SQL/Python stajı) |
| Hedef | Junior Veri Analisti |
| Başlangıç readiness | %42 (dashboard) → görev sonrası %48 (ideal canlı artış) |

Persona, `PanelDemoData` ve mevcut panel metinleriyle uyumludur.

### 1.3 Ekran akışı (dakika dakika)

| Dk | Ekran | Aksiyon | Jüriye söylenecek (özet) |
|----|-------|---------|---------------------------|
| 0:00 | Tanıtım `/` | «Ücretsiz Başla» veya doğrudan panel | «Mezun olan öğrenci CV'sini yükler ama hangi işe ne kadar hazır olduğunu göremez.» |
| 0:30 | `/panel` Gösterge | Readiness %42, hedef meslek, haftalık görev özeti | «Tek ekranda hazırlık yüzdesi ve bu haftanın görevleri. ChatGPT bunu haftalarca takip etmez.» |
| 1:00 | CV radar / CV Oluştur | Kayıtlı CV veya Harvard önizleme | «CV ATS uyumlu; TR ve EN ayrı içerik.» |
| 1:45 | `/panel/kariyer-merdiveni` | A / B / C merdiven, Junior DA %78 açık | «Aynı CV için birden fazla meslek; A şimdi, B 6 hafta, C uzun vade.» |
| 2:30 | SWOT kartı (Junior DA) | Güçlü / zayıf / fırsat / tehdit | «Zayıf maddeler CV'den kanıtlı: Tableau eksik, portfolio zayıf.» |
| 3:15 | `/panel/yol-haritasi` | Gap listesi + haftalık plan | «Eksik yetenek doğrudan göreve dönüşüyor.» |
| 3:45 | Görev tamamla | «SQL modülü 1» işaretle | «Görev bitince skor güncellenir.» (ideal: %42 → %48) |
| 4:15 | `/panel/egitim-onerileri` | Coursera / YouTube filtre | «Kurs satmıyoruz; gap'e göre ücretsiz ve ücretli harici kaynak.» |
| 4:45 | `/panel/sohbet` | «Bu hafta ne öğrenmeliyim?» | «Asistan CV + gap + roadmap bağlamında cevap verir.» (Sprint 3) |
| 5:30 | Mentör görünümü (opsiyonel) | Cohort: kim %70 üstü | «Bootcamp mentörü tek ekranda kimin takıldığını görür.» |
| 6:00 | ChatGPT karşılaştırma slaytı | 1 slayt yan yana | v001 tablosu: metin vs panel |

**Zorunlu 4 kanıt (v001):** merdiven, SWOT + %, gap→görev→link, (ideal) skor farkı.

### 1.4 «Wow» anı (60 saniye)

> Ayşe Junior DA kartını açar → Tableau eksik görünür → yol haritasında «Tableau dashboard mini proje» görevi → eğitim önerilerinde ücretsiz YouTube + ücretli Coursera yan yana → görevi tamamlar → readiness çubuğu hareket eder.

Bu zincir **XAI gap** moat'ını jüriye somut gösterir.

### 1.5 Yedek planlar

| Risk | Yedek |
|------|-------|
| API / ağ çöker | Önceden kaydedilmiş **2 dk ekran videosu** (mp4) |
| Canlı skor artmaz | «İkinci oturum» slaytı: aynı CV, 2 hafta sonra %42→%61 grafik |
| Sohbet hazır değil | «Sprint 3'te bağlamlı asistan» slaytı + statik örnek diyalog |
| Giriş/auth takılır | Demo hesabı önceden açık tarayıcı sekmesi |

### 1.6 Prova checklist

- [ ] Demo hesabı + localStorage temiz başlangıç scripti
- [ ] Ayşe persona CV'si yüklü
- [ ] Junior DA SWOT açılıyor
- [ ] En az 1 görev tamamlanınca skor değişiyor (veya video yedek)
- [ ] 10 dk prova × 2 (farklı jüri üyesi dinleyici)
- [ ] Video yedek USB + bulutta

---

## 2. Readiness algoritması taslağı

### 2.1 İlke (v001 ile uyum)

- Skor **yapılandırılmış veri + algoritma**; LLM yalnızca CV parse ve metin üretir.
- Aynı `user_profile` + aynı `career_role` → **aynı readiness %** (deterministik).
- Her gap maddesi **açıklanabilir** (hangi skill, hangi seviye bekleniyor, CV'de ne var).

### 2.2 Girdi verileri

**Rol tanımı** (`data/roles/bootcamp_roles.json`):

```json
{
  "name": "SQL",
  "level": "orta",
  "priority": "zorunlu"
}
```

**Kullanıcı profili** (CV parse çıktısı, FastAPI `user_profiles.profile_data`):

```json
{
  "skills": [
    { "name": "SQL", "level": "orta", "evidence": "Staj: günlük sorgu yazımı" },
    { "name": "Python", "level": "temel", "evidence": "Bootcamp projesi" }
  ],
  "experience_months": 6,
  "education_level": "lisans"
}
```

### 2.3 Seviye skorları

| Profil / rol seviyesi | Sayısal karşılık |
|-----------------------|------------------|
| yok / tanımsız | 0.00 |
| temel | 0.50 |
| orta | 0.75 |
| ileri | 1.00 |

**Eşleşme kuralı:** Kullanıcı seviyesi ≥ rolün istediği seviye → tam puan (1.0). Altındaysa oran: `min(1, user_score / required_score)`.

Örnek: Rol «SQL orta» (0.75), kullanıcı «SQL temel» (0.50) → `0.50 / 0.75 = 0.67` skill puanı.

### 2.4 Öncelik ağırlıkları

| priority | Ağırlık `w` |
|----------|-------------|
| zorunlu | 1.0 |
| tercih | 0.4 |

### 2.5 Formül (MVP)

Her skill `i` için:

```
match_i = min(1, level_score(user_i) / level_score(required_i))   # skill yoksa user_i = 0
weighted_i = match_i * w_i
```

```
readiness_raw = 100 * (Σ weighted_i) / (Σ w_i)
readiness = round(clamp(readiness_raw, 0, 100))
```

**Tier atama (v001 eşikleri, cohort override opsiyonel):**

| Tier | Koşul | Etiket |
|------|-------|--------|
| A | readiness ≥ 70 | Hazır |
| B | 40 ≤ readiness < 70 | Yakın |
| C | readiness < 40 | Ulaşılabilir |

**Merdiven sıralama:** Önce tier (A→B→C), tier içinde readiness desc, eşitlikte gap_count asc.

### 2.6 Opsiyonel bonuslar (Sprint 2+, ağırlıklı eklenti)

MVP sonrası `readiness_raw`'a en fazla +10 puan (cap 100):

| Sinyal | Bonus | Kanıt |
|--------|-------|-------|
| İlgili staj ≥ 3 ay | +5 | CV experience |
| Portfolio / GitHub linki | +3 | CV projects |
| Sertifika (rol skill_tags ile örtüşen) | +2 | CV certificates |

Bonuslar ayrı JSON alanında tutulur; jüriye «neden +5?» gösterilebilir.

### 2.7 Gap listesi üretimi

```
gap_i = skill i where match_i < 1.0
severity = (1 - match_i) * w_i
```

Sıralama: `severity` desc. UI'da ilk 5 gap + «+N daha» özeti.

### 2.8 Örnek hesap: Junior Veri Analisti (Ayşe)

Rol skill seti: `data-analyst` (bootcamp_roles.json). Demo için sadeleştirilmiş profil:

| Skill | Rol isteği | Ayşe profili | match | w | weighted |
|-------|------------|--------------|-------|---|----------|
| Excel | orta zorunlu | orta | 1.00 | 1.0 | 1.00 |
| SQL | orta zorunlu | orta | 1.00 | 1.0 | 1.00 |
| Python | temel zorunlu | temel | 1.00 | 1.0 | 1.00 |
| Pandas | temel zorunlu | temel | 1.00 | 1.0 | 1.00 |
| Veri Görselleştirme | temel zorunlu | yok | 0.00 | 1.0 | 0.00 |
| İstatistik | temel tercih | temel | 1.00 | 0.4 | 0.40 |
| Power BI | temel tercih | yok | 0.00 | 0.4 | 0.00 |
| İletişim | orta zorunlu | temel | 0.67 | 1.0 | 0.67 |

```
Σ w = 1.0*5 + 0.4*2 = 5.8
Σ weighted = 1+1+1+1+0+0.4+0+0.67 = 5.07
readiness_raw = 100 * 5.07 / 5.8 ≈ 87.4
```

Demo kartında Junior DA **%78** gösterilir: bonuslar kapalı veya Tableau/portfolio manuel gap düşümü ile kalibre edilmiş hali. **Üretimde golden set ile hizalanır.**

### 2.9 Golden set (regresyon)

| Dosya | Açıklama |
|-------|----------|
| `data/fixtures/profiles/ayse-yilmaz.json` | Demo persona profili |
| `data/fixtures/expected-readiness.json` | Rol → beklenen % ±2 tolerans |

**Test sözleşmesi (backend):**

```python
def test_readiness_ayse_junior_da():
    score = GapAnalysisService.score(profile_ayse, role_data_analyst)
    assert 76 <= score <= 80
```

### 2.10 API sözleşmesi (FastAPI)

```
POST /api/v1/careers/{role_id}/analyze
Body: { "user_id": "..." }
Response: {
  "readiness_score": 78,
  "tier": "ready",
  "gaps": [
    { "skill": "Tableau", "required_level": "temel", "user_level": null, "severity": 1.0, "evidence": null }
  ],
  "swot": { "strengths": [...], "weaknesses": [...], "opportunities": [...], "threats": [...] }
}
```

SWOT zayıf maddeleri `gaps` listesinden türetilir; güçlü maddeler `match_i = 1` skill'lerden.

---

## 3. YZTA kurum paketi (B2B fiyatlandırma)

### 3.1 Satın alan kim?

| Karar verici | Motivasyon |
|--------------|------------|
| YZTA program yöneticisi | Mezuniyet çıktısı ölçülebilir olsun |
| Mentör / eğitmen | Kim takıldı, tek ekran |
| Bootcamp satış | «Kariyer paneli dahil» diferansiyatör |

### 3.2 Paket kademeleri

#### Pilot — «Grup 92» (jüri dönemi)

| | |
|---|---|
| **Fiyat** | **0 ₺** (vaka çalışması + referans) |
| **Süre** | Bootcamp süresi + 3 ay mezuniyet sonrası |
| **Koltuk** | ≤ 60 öğrenci |
| **Kapsam** | Tam öğrenci paneli, mentör read-only cohort, demo readiness |
| **Karşılık** | Logo kullanımı, jüri testimonial, anonim kullanım metrikleri |

#### Cohort — «Standart»

| | |
|---|---|
| **Fiyat** | **18.000 ₺ / cohort / dönem** (KDV hariç) |
| **Koltuk** | ≤ 40 aktif öğrenci |
| **Fazla koltuk** | +350 ₺ / öğrenci / dönem (41–60 arası) |
| **Kapsam** | CV yükle + builder, kariyer merdiveni, gap, yol haritası, eğitim önerileri, haftalık görevler |
| **Mentör** | 1 mentör hesabı, cohort dashboard (hazırlık %, son giriş) |
| **Destek** | E-posta, 48 saat yanıt |

**Öğrenci başı maliyet (40 koltuk):** ~450 ₺/dönem (~3 ay).

#### Cohort — «Pro»

| | |
|---|---|
| **Fiyat** | **32.000 ₺ / cohort / dönem** |
| **Koltuk** | ≤ 60 öğrenci |
| **Kapsam** | Standart + bağlamlı sohbet asistanı + PDF/CSV cohort raporu |
| **Mentör** | 3 mentör hesabı, öğrenci bazlı gap detayı |
| **Özel** | `bootcamp_roles.json` için 1 özel rol şablonu / dönem |
| **Destek** | Öncelikli, 24 saat |

#### Kurum — «Yıllık»

| | |
|---|---|
| **Fiyat** | **89.000 ₺ / yıl** (KDV hariç) |
| **Koltuk** | ≤ 150 öğrenci / yıl (birden fazla cohort) |
| **Kapsam** | Pro özellikleri + white-label logo (panel header) |
| **Ek** | Çeyreklik işveren buluşması için hazır aday raporu (Faz 2) |

### 3.3 Fiyatlandırma mantığı

| Karşılaştırma | Rakam |
|---------------|-------|
| Öğrenci Pro (bireysel, planlanan) | 149 ₺/ay × 3 ay ≈ 450 ₺ |
| Cohort Standart öğrenci başı | ~450 ₺/dönem |
| Bootcamp öğrenci ödemesi (tipik) | 15.000–40.000 ₺ program ücreti |
| CareerTalent cohort ek maliyeti | Program ücretinin **~%1–2**'si (kurum için düşük, değer yüksek) |

**Mesaj:** «Öğrenci başına bir kahve parasına ölçülebilir kariyer çıkışı.»

### 3.4 YZTA'ya önerilen ilk teklif (Grup 92 sonrası)

| Madde | Teklif |
|-------|--------|
| Dönem | 2026 sonbahar cohort |
| Paket | Cohort Standart |
| Fiyat | **12.000 ₺** (erken benimseyen indirimi, %33) |
| Süre | 4 ay (bootcamp + 1 ay mezuniyet) |
| Ek | Sonraki 2 cohort'ta fiyat kilidi (18.000 ₺) |

### 3.5 Dahil / hariç

| Dahil | Hariç |
|-------|-------|
| Panel hosting (SaaS) | Özel on-premise kurulum |
| TR/EN CV builder | Kendi kurs içeriği üretimi |
| Seed eğitim linkleri | Udemy/Coursera lisansları |
| Gemini API kotası (makul kullanım)* | Sınırsız API / ağır batch |

*Makul kullanım: ≤ 50 CV parse + ≤ 200 sohbet turu / öğrenci / dönem. Aşımda Pro veya kullanım paketi.

### 3.6 Gelir projeksiyonu (ilk 12 ay, konservatif)

| Kaynak | Adet | Gelir |
|--------|------|-------|
| YZTA pilot | 1 | 0 ₺ |
| YZTA sonbahar (indirimli) | 1 | 12.000 ₺ |
| 2. bootcamp (Standart) | 2 | 36.000 ₺ |
| Üniversite kariyer merkezi pilot | 1 | 18.000 ₺ |
| **Toplam** | | **~66.000 ₺** |

Faz 2 ilan eşleştirme ve bireysel Pro ile 12. ay sonunda **150.000 ₺+** hedeflenebilir (v002 gelir kapısı #2).

---

## 4. Üç başlığın kesişimi (tek hikâye)

```
YZTA cohort lisansı alır (B2B gelir)
    → Öğrenci CV yükler
    → Readiness algoritması deterministik skor üretir (güven)
    → Jüri demosunda gap→görev→kaynak zinciri gösterilir (farklılaşma)
    → Mentör cohort dashboard ile takip eder (kurum ROI)
    → Mezuniyet sonrası öğrenci bireysel Pro'ya upsell (B2C gelir)
```

---

## 5. Açık kararlar (v004 için)

| # | Soru | Önerilen varsayılan |
|---|------|---------------------|
| 1 | Readiness eşikleri cohort'a göre ayarlanır mı? | MVP'de sabit 70/40; Pro pakette yapılandırılabilir |
| 2 | Demo'da skor artışı canlı mı seed mi? | Sprint 2'de gerçek; jüri öncesi video yedek |
| 3 | YZTA pilot sonrası ücretli geçiş tarihi? | Top 10 sonrası ilk görüşme |
| 4 | Golden set kaç profil? | Min 5 profil × 5 rol = 25 beklenen skor |
| 5 | Affiliate gelir kurum paketine dahil mi? | Hayır; ayrı gelir, öğrenciye şeffaf |

---

## MVP / sprint bağlantısı

| Sprint | v003 çıktısının karşılığı |
|--------|---------------------------|
| Sprint 2 | `GapAnalysisService` = Bölüm 2 formülü; golden set testleri |
| Sprint 3 | Jüri senaryosu Bölüm 1; mentör = Kurum paketi kapsamı |
| Top 10 (14 Ağu) | Prova checklist + yedek video |
| Bootcamp sonrası | YZTA Standart teklifi (Bölüm 3.4) |

---

*YZTA Bootcamp Grup 92 — CareerTalent AI*  
*Önceki sürüm: [v002](2026-06-29-v002-ogrenci-yolculugu-swot-gelir.md)*
