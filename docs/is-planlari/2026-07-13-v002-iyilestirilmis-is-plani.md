# CareerTalent AI — İyileştirilmiş İş Planı v002

| | |
|---|---|
| **Sürüm** | v002 |
| **Tarih** | 2026-07-13 |
| **Önceki sürüm** | [2026-07-13-v001-is-plani.md](2026-07-13-v001-is-plani.md) |
| **Durum** | Taslak — öneriler uygulanmış stratejik sürüm |
| **Amaç** | Product Strategy, Sales Engineer, PM/PdM, Architect ve QA iyileştirme önerilerini iş planına işlemek |
| **Kaynak erişim tarihi** | 2026-07-13 |

---

## 1. Önceki sürüme göre değişiklik

| Alan | v001 | v002 |
|---|---|---|
| Ana strateji | B2B/B2B2C model önerildi | **B2B cohort SaaS** ana strateji olarak kilitlendi |
| Satış dili | CV analizi + readiness anlatımı | “Cohort readiness + placement risk paneli” satış dili öne alındı |
| Pilot paket | 8-10 hafta pilot önerisi | 8 haftalık net paket, teslimatlar, KPI ve başarı kriterleri tanımlandı |
| Ürün önceliği | Genel yol haritası | Pricing/bootcamp paket dili, pilot raporu, auth/KVKK/export/kalıcı analiz zinciri sıraya alındı |
| QA kanıtı | Web + repo kaynakları | Her iş planı güncellemesi ayrı dosya kuralı ve docs-only doğrulama eklendi |

---

## 2. Stratejik karar

**Ana strateji:** CareerTalent AI, öğrenciden gelir alan B2C abonelik değil; bootcamp, üniversite ve kariyer merkezlerine satılan **B2B cohort SaaS** olmalı.

Neden:

- Öğrenci ödeme isteği düşük; kurumun placement, raporlama ve mezun başarısı bütçesi var.
- Ürün mevcut yapısında admin/cohort/readiness ekranlarına sahip.
- YÖK ve OECD verisi, eğitimden işe geçiş problemini kurum seviyesinde anlamlı kılıyor.
- Handshake, Coursera Career Academy ve Lightcast örnekleri, career readiness / career center / education analytics alanında kurumsal satın almanın güçlü olduğunu gösteriyor.

Tek cümle yeni konumlandırma:

> CareerTalent AI, bootcamp ve üniversiteler için öğrencilerin işe hazırlık seviyesini ölçen, riskli adayları erken gösteren ve placement sürecini aksiyona çeviren cohort readiness platformudur.

---

## 3. Satış dili — “CV analiz” değil “placement readiness”

### Eski demo dili

- CV yükle.
- Skill çıkar.
- Kariyer planı al.

### Yeni demo dili

- Cohort’un işe hazırlık fotoğrafını çıkar.
- Riskli öğrencileri erken gör.
- Mentor aksiyonlarını doğru kişiye yönlendir.
- Demo day için hazır aday havuzu oluştur.
- Placement raporunu ölçülebilir sun.

### Kuruma net değer cümlesi

> 60 öğrencilik bir cohort’ta 8 hafta sonunda kim A segment işe hazır, kim B segment mentor desteğiyle hazır olur, kim C segment uzun vadeli plan ister; CareerTalent bunu skor, kanıt ve aksiyon listesiyle gösterir.

---

## 4. Net pilot paketi

### Paket adı

**CareerTalent Cohort Readiness Pilot**

### Süre

8 hafta

### Hedef müşteri

- teknoloji bootcamp’i
- YZTA benzeri akademi
- üniversite kariyer merkezi
- fakülte/program bazlı kariyer ofisi

### Kapsam

| Hafta | Teslimat | Kurum değeri |
|---|---|---|
| 0 | Cohort onboarding + öğrenci daveti | Başlangıç havuzu oluşur |
| 1 | CV analizi + başlangıç readiness skoru | İlk risk fotoğrafı çıkar |
| 2 | Hedef rol seçimi + A/B/C segmenti | Hangi aday hangi role yakın görünür |
| 3 | Skill gap ve kaynak önerileri | Ortak eksikler görünür |
| 4 | Ara risk raporu | Mentor zamanı doğru öğrenciye gider |
| 5 | Kanıt/portfolio kontrolü | GitHub, proje, sertifika eksikleri kapanır |
| 6 | Mülakat/başvuru hazırlığı | Demo day öncesi hazır aday listesi netleşir |
| 7 | İşveren/demo day aday havuzu | A segment adaylar sunuma hazırlanır |
| 8 | Final cohort report | Placement readiness çıktısı kuruma teslim edilir |

### Pilot fiyat önerisi

| Paket | Öğrenci sayısı | Fiyat | Not |
|---|---:|---:|---|
| Pilot S | 30 öğrenci | ₺60.000 | İlk referans müşteri / sınırlı rapor |
| Pilot M | 60 öğrenci | ₺90.000 | Önerilen paket |
| Pilot L | 100 öğrenci | ₺140.000 | Çoklu mentor + geniş final raporu |

Fiyat varsayımıdır; ilk 3 satış görüşmesinde validasyon gerekir.

---

## 5. Pilot başarı kriterleri

8 hafta sonunda “başarılı pilot” sayılması için ölçülecek KPI’lar:

| KPI | Tanım | Hedef |
|---|---|---:|
| Aktivasyon | Davet edilen öğrencilerden CV/profil tamamlayan oran | %70+ |
| Target role seçimi | Hedef rolünü seçen aktif öğrenci oranı | %65+ |
| Readiness delta | Başlangıç ve final readiness ortalaması farkı | +8 puan |
| Riskli öğrenci görünürlüğü | C segment/riskli öğrencilerin kurumca aksiyonlanması | %80+ |
| Mentor aksiyon kapanışı | Mentor/review önerilerinden tamamlanan oran | %50+ |
| Kanıt tamamlama | GitHub/portfolio/sertifika kanıtı ekleyen öğrenci oranı | %40+ |
| Demo day ready aday | A segment veya B→A ilerleyen aday sayısı | cohort’un %20+ |
| Kurum rapor kullanımı | Ara veya final raporun kurum tarafından indirilmesi/paylaşılması | 2+ rapor |

---

## 6. Ürün öncelikleri

### Hemen netleştirilecek görünür paketler

1. **Pricing sayfası içeriği**
   - Ücretsiz öğrenci planı.
   - Pilot cohort paketi.
   - Growth/Enterprise kurum paketi.
   - Mentor kredisi add-on.

2. **Bootcamp sayfası CTA**
   - “Pilot Programa Katıl” yerine “Pilot Cohort Başlat”.
   - Öğrenci değil kurum karar vericisi hedeflenir.

3. **Demo dili**
   - “CV analizi” ikincil.
   - “Cohort readiness, risk ve placement paneli” birincil.

4. **Pilot raporu**
   - 1 sayfalık PDF/Markdown örnek rapor.
   - A/B/C dağılımı, risk listesi, skill gap, mentor aksiyonları, demo day adayları.

### Production öncelikleri

| Öncelik | İş | Gerekçe |
|---|---|---|
| P0 | Auth/admin yetki | Kurumsal demo güveni |
| P0 | KVKK açık rıza + veri silme/export | CV kişisel veri içerir |
| P0 | CV → profil → skor → plan zincirini DB’ye bağlama | Demo veriden ürün verisine geçiş |
| P1 | Cohort export PDF/CSV | Kurum satın alma sebebi |
| P1 | Mentor aksiyon kuyruğu | Mentor zamanını ölçülebilir kullanma |
| P1 | Pricing/bootcamp lead form | Satış pipeline oluşturma |
| P2 | İşveren sponsorlu case | Yeterli aday havuzu sonrası gelir |
| P2 | Affiliate kaynak etiketi | Güvenli yan gelir |

---

## 7. Kurum satış deck yapısı

1. Türkiye’de yükseköğretim ve genç istihdam problemi.
2. Cohort yöneten kurumun görünürlük sorunu.
3. CareerTalent: readiness skoru + A/B/C kariyer merdiveni.
4. Admin panel: risk, skill gap, mentor aksiyon, placement funnel.
5. 8 haftalık pilot plan.
6. Başarı KPI’ları.
7. Paket fiyatı.
8. Örnek final rapor.
9. Sonraki adım: pilot cohort seçimi.

---

## 8. Örnek final pilot raporu içeriği

### Cohort özeti

| Metrik | Değer |
|---|---:|
| Toplam öğrenci | 60 |
| Profil tamamlayan | 46 (%77) |
| Ortalama readiness başlangıç | %54 |
| Ortalama readiness final | %63 |
| Readiness delta | +9 puan |
| A segment | 14 öğrenci |
| B segment | 24 öğrenci |
| C segment | 8 öğrenci |

### En sık skill gap

| Skill gap | Etkilenen öğrenci | Önerilen aksiyon |
|---|---:|---|
| SQL case pratiği | 28 | 2 haftalık mini proje |
| Portfolio kanıtı | 22 | GitHub/Notion proje şablonu |
| Power BI/Tableau | 17 | Dashboard görevi |
| HR mülakat anlatımı | 15 | STAR cevap provası |

### Mentor aksiyonları

| Aksiyon | Öğrenci sayısı | Öncelik |
|---|---:|---|
| CV hızlı kontrol | 18 | Yüksek |
| Portfolio review | 12 | Yüksek |
| Mülakat provası | 9 | Orta |

### Demo day ready pool

- A segment adaylar: 14
- B segment ama 2 hafta içinde hazır olabilir: 10
- İşverenle paylaşılabilir verified portfolio: 11

---

## 9. Revenue plan — netleştirilmiş

### Ana revenue

**B2B cohort SaaS.**
Hedef: ilk 12 ayda 6 pilot + 12 growth cohort.

| Gelir kalemi | 12 ay base varsayım | Gelir |
|---|---:|---:|
| Pilot cohort | 6 × ₺75.000 | ₺450.000 |
| Growth cohort | 12 × 80 öğrenci × ₺1.200 | ₺1.152.000 |
| Enterprise pilot | 1 × ₺400.000 | ₺400.000 |
| **Core toplam** |  | **₺2.002.000** |

### Yan revenue

| Gelir kalemi | Varsayım | Gelir |
|---|---:|---:|
| Mentor GMV komisyonu | ₺600.000 × %25 | ₺150.000 |
| İşveren sponsor/case | 4 × ₺40.000 | ₺160.000 |
| Affiliate/data reports | düşük, seçici | ₺50.000 |
| **Yan toplam** |  | **₺360.000** |

### 12 ay toplam base

**₺2,36M**

Bu sayı yatırım sunumunda “base case” olarak kullanılmalı. “TAM büyük, ilk hedef dar ve satılabilir” mesajı verir.

---

## 10. Ölçek mantığı

| Aşama | Hedef | Gelir odağı |
|---|---|---|
| 0-3 ay | 1-2 pilot kurum | Pilot cohort ücreti |
| 3-6 ay | 6 pilot, ilk referans | Pilot + mentor kredisi |
| 6-12 ay | 12+ growth cohort | Cohort lisansı |
| 12-24 ay | Üniversite/kariyer merkezi | Enterprise yıllık lisans |
| 18+ ay | İşveren ağı | Sponsorlu case + talent access |

---

## 11. Uygulanmış iyileştirme önerisi matrisi

| Lens | Öneri | v002’de uygulama |
|---|---|---|
| Product Strategy | Ana strateji B2B cohort SaaS olmalı | Bölüm 2 ve 9’da ana strateji kilitlendi |
| Sales Engineer | Demo “CV analiz” değil “cohort readiness + placement risk paneli” olmalı | Bölüm 3 satış dili değiştirildi |
| PM/PdM | 8 haftalık pilot başarı kriterleri netleşmeli | Bölüm 4 ve 5 pilot plan + KPI eklendi |
| Architect | Auth, KVKK, kalıcı analiz zinciri, export şart | Bölüm 6 production öncelikleri eklendi |
| QA | Kaynaklı doküman + ayrı sürüm dosyası | Bu v002 ayrı dosya olarak eklendi; kaynakça korundu |

---

## 12. Karar notu

Bu sürüm nihai karar değildir. Bundan sonraki her iş planı güncellemesi `docs/is-planlari/` altında yeni tarihli/sürümlü dosya olarak eklenmelidir. Eski dosyalar korunur. Nihai karar alındığında yalnızca kanonik özet/temizlik yapılır.

---

## 13. Kaynakça

1. YÖK öğrenci/akademisyen istatistikleri, 2025-11-07: https://www.yok.gov.tr/tr/news/yuksekogretim-kurulu-ogrenci-ve-akademisyen-istatistiklerini-acikladi-0h1og
2. YÖK istihdam/mezun açıklaması, 2025-12-20: https://www.yok.gov.tr/tr/news/yuksekogretim-kurulu-baskani-ozvardan-onemli-aciklamalar-S68QC
3. TÜİK İşgücü İstatistikleri Mayıs 2026: https://veriportali.tuik.gov.tr/tr/press/57985
4. Trading Economics TÜİK özeti: https://tr.tradingeconomics.com/turkey/unemployment-rate
5. OECD Education at a Glance 2025, transition to work: https://www.oecd.org/en/publications/2025/09/education-at-a-glance-2025_c58fc9ae/full-report/transition-from-education-to-work-where-are-today-s-youth_b90719d0.html
6. WEF Future of Jobs Report 2025: https://www.weforum.org/publications/the-future-of-jobs-report-2025/
7. WEF 2025 press release: https://www.weforum.org/press/2025/01/future-of-jobs-report-2025-78-million-new-job-opportunities-by-2030-but-urgent-upskilling-needed-to-prepare-workforces/
8. Handshake employer benchmark: https://joinhandshake.com/employers/
9. Handshake pricing benchmark: https://joinhandshake.com/employers/products/premium/compare/
10. Coursera Career Academy: https://www.coursera.org/campus/career-academy
11. Lightcast Education: https://lightcast.io/solutions/education
12. Lightcast Career Coach: https://lightcast.io/products/software/career-coach
13. Yapay Zeka ve Teknoloji Akademisi: https://yapayzekaveteknolojiakademisi.com/
14. CareerTalent yerel repo kanıtı: `README.md`, `frontend/app/Data/AdminDemoData.php`, `frontend/app/Data/PanelDemoData.php`, `frontend/resources/views/marketing/pricing.blade.php`, `frontend/resources/views/app/account.blade.php`, `frontend/routes/web.php`

---

*CareerTalent AI — YZTA Bootcamp Grup 92*
