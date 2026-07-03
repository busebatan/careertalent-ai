# CareerTalent AI — Öğrenci Yolculuğu, SWOT ve Gelir Kapıları v002

| | |
|---|---|
| **Sürüm** | v002 |
| **Tarih** | 2026-06-29 |
| **Önceki sürüm** | [2026-06-29-v001-urun-degeri.md](2026-06-29-v001-urun-degeri.md) |
| **Durum** | Taslak (beyin fırtınası) |
| **Hazırlayan** | Takım + ürün oturumu |

---

## Önceki sürüme göre değişiklik

| Alan | v001 | v002 (bu sürüm) |
|------|------|-----------------|
| Problem | Genel öğrenci acıları | Aynı; CV sonrası akış ve güven riski vurgusu |
| Çözüm | Üst düzey akış diyagramı | Adım adım yolculuk + **bugün panelde ne gerçek / ne demo** tablosu |
| Değer önerisi | Pitch + öğrenci/mentör maddeleri | **Kariyer hazırlık işletim sistemi** konumlandırması |
| Rakiplerden fark | Tablo (ChatGPT, kariyer siteleri) | **7 püf nokta** (moat adayları) detaylandırıldı |
| Gelir | Dolaylı (Faz 2 ilan) | **6 gelir kapısı** öncelik sırasıyla |
| Yeni | — | SWOT (durum değerlendirmesi), açık strateji soruları |

---

## Problem

YZTA bootcamp ve benzeri programlardan mezun olan veya mezun olmaya hazırlanan öğrenciler CV yükledikten veya oluşturduktan sonra:

1. **Sıradaki adımı** net göremiyor (analiz mi, hedef meslek mi, görev mi?).
2. ChatGPT ile **tek seferlik metin** alıyor; haftalarca **ölçülebilir ilerleme** yok.
3. Panel güzel görünse bile skor **demo** ise güven kaybı riski var.
4. Bootcamp bittikten sonra ürünün **yaşam döngüsü** tanımsız kalırsa tek seferlik kullanım olur.

v001'deki acılar geçerlidir; bu sürüm **CV giriş noktasından sonraki yolculuğu** ve **ticarileşme** katmanını netleştirir.

---

## Çözüm

### Hedef ürün akışı (CV sonrası)

```
CV yükle / oluştur
    → AI parse → yapılandırılmış profil (yetenekler, projeler, deneyim)
    → Her hedef meslek için readiness % (A / B / C merdiven)
    → Seçilen meslek → kanıtlı SWOT (CV'den S/W, pazardan O/T)
    → Skill gap listesi
    → Haftalık yol haritası + görevler
    → Gap'e göre filtrelenmiş eğitim/sertifika önerileri (harici link)
    → Görev tamamlanınca skor güncellenir → ilerleme hikayesi
    → (Faz 2) Gerçek iş ilanı eşleştirmesi
    → (Faz 3) Bağlamlı kariyer asistanı (CV + gap + roadmap bağlamında)
```

**Öğrencinin hissetmesi gereken döngü:** CV bir kez okunur → hedef netleşir → her hafta ne yapacağı belli → eksik kapanınca skor artar → işe başvuruya yaklaşır.

### Bugün panelde gerçekte ne var? (2026-06-29 kod durumu)

| Adım | Vizyon | Bugün |
|------|--------|-------|
| CV oluştur | Harvard format, TR/EN | Var: PDF, isteğe bağlı bölümler, çift dilli şablon |
| CV yükle | PDF → FastAPI → Gemini parse | Profilde dosya adı; gerçek parse henüz yok |
| Yetenek analizi | CV'den çıkarım | `demoSkillAnalysis()` sabit radar |
| Kariyer merdiveni A/B/C | Algoritmik skor | `PanelDemoData` statik |
| SWOT | Kanıtlı, açıklanabilir | Demo kartlar |
| Yol haritası / görevler | API + skor güncelleme | Demo + localStorage görevler |
| Eğitim önerileri | Gap → filtreli link | Seed liste (Coursera, Udemy vb.) |
| Sohbet | Bağlamlı asistan | «Yakında» |
| Auth / cohort / mentör | Sprint 1–3 | Henüz tam bağlı değil |

**Sonuç:** UI iskeleti güçlü; zeka katmanı demo. Bir sonraki kritik sıçrama: **CV → gerçek profil → skorların profile bağlanması**.

Teknik detay: [teknik-mimari.md](../teknik-mimari.md)

---

## SWOT (durum değerlendirmesi)

### Güçlü (S)

- Net ürün hikayesi: merdiven + gap + görev + kaynak (kurs satmıyoruz).
- Bootcamp ortağı (YZTA): pilot cohort, jüri demosu, gerçek kullanıcı.
- Çift dilli CV (TR/EN): Türkiye + uluslararası başvuru segmenti.
- Harvard CV + ATS odaklı builder: generic «AI CV yazıcı»dan ayrışma başlangıcı.
- Mimari plan (Laravel UI + FastAPI zeka): ölçeklenebilir.

### Zayıf (W)

- Çekirdek değer (readiness %, gap) henüz **kanıtlanmış algoritma + gerçek CV parse** değil.
- Öğrenci güvenir; skor demo kalırsa churn riski.
- İki stack koordinasyon maliyeti (Sprint 2 retro riski).
- Pazar SWOT (O/T) Faz 2'ye bağlı; hikaye güçlü, veri zayıf.
- Rakipler «AI CV» diye pazarlıyor; farkı 10 saniyede anlatmak zor.

### Fırsat (O)

- TR'de bootcamp mezunu dalgası + «iş bulamıyorum» acısı büyük.
- ChatGPT yaygın ama **süreklilik ve ölçüm** yok.
- B2B: bootcamp / üniversite / kariyer merkezi lisansı (cohort dashboard).
- Affiliate / partner: eğitim yönlendirmesinden komisyon (kurs satmadan).
- Faz 2 ilan eşleştirme: Kariyer.net «liste», biz «hazırlık önce».

### Tehdit (T)

- LinkedIn, Coursera, ChatGPT tek başına «yeterli» algısı.
- Generic CV scanner SaaS (Rezi, Teal, Jobscan) anahtar kelime oyununda önde.
- Skor metodolojisi şeffaf değilse güven kaybı.
- Bootcamp süresi bitince ürün «yaşam döngüsü» tanımsız kalırsa tek seferlik kullanım.

---

## Değer önerisi

### Tek cümle (pitch)

> ChatGPT kariyer koçu verir; CareerTalent **ölçülebilir hazırlık, kariyer merdiveni, haftalık plan ve pazar gerçekliği** verir.

Alternatif konumlandırma:

> **CV'nizi bir kez okuruz; hangi işe ne kadar hazır olduğunuzu yüzdeyle söyleriz; eksikleri haftalık plan ve doğru kaynaklarla kapatırız; hazır olunca ilan eşleştiririz.**

ChatGPT = koç konuşur. CareerTalent = **kariyer hazırlık işletim sistemi**.

### Öğrenci için

- CV'den sıralı meslek haritası (şimdi vs sonra).
- Readiness % ve eksik yetenek listesi.
- Haftalık görev + filtrelenmiş eğitim linkleri.
- Zaman içinde ilerleme hikayesi (bootcamp boyunca).

### Mentör / bootcamp için

- Cohort özeti: kim % kaç hazır, kim takıldı.
- YZTA pilotu için ölçülebilir çıktı (jüri demosu).

---

## Rakiplerden farkımız

### Tablo (v001 özeti)

| Rakip / alternatif | Onlar ne yapar? | Biz ne yaparız? |
|--------------------|-----------------|-----------------|
| **ChatGPT / Gemini** | Serbest metin, genel SWOT | Yapılandırılmış merdiven, tutarlı readiness %, kalıcı panel |
| **Kariyer.net / LinkedIn** | İlan listesi | Önce hazırlık, sonra ilan; eksikleri kapatma planı |
| **Udemy / Coursera** | Kurs satar | Gap'e göre filtrelenmiş öneri + ücretsiz seçenek |
| **Generic CV araçları** | Anahtar kelime skoru | SWOT + yol haritası + (Faz 2) TR pazar verisi |
| **Bootcamp LMS** | Ders takibi | Kariyer çıkış odaklı: mezuniyet sonrası işe hazırlık |

### Püf nokta adayları (moat)

Birlikte rekabet bariyeri oluşturur; tek başına «AI CV» yetmez.

| # | Püf nokta | Rakip farkı |
|---|-----------|-------------|
| 1 | **Kanıtlı kariyer merdiveni** (A şimdi / B 6 hafta / C uzun vade) | ChatGPT skala değiştirir; aynı CV + rol = aynı skor |
| 2 | **XAI gap zinciri** | Her W maddesi CV satırı → gap → görev → kaynak; soyut liste değil |
| 3 | **Haftalık kariyer OS** | Tek oturum tavsiye değil; bootcamp boyunca ölçülebilir ilerleme |
| 4 | **TR pazar katmanı** (Faz 2) | İlan + skill frekansı; global araçlar TR gerçekliğini bilmez |
| 5 | **Çift dilli kariyer çıkışı** | TR/EN CV aynı profilden; remote / yurtdışı hedef segmenti |
| 6 | **Cohort / mentör görünürlüğü** | Bireysel araç değil; bootcamp ROI kanıtı (B2B kilidi) |
| 7 | **Hazırlık önce, ilan sonra** | «Henüz başvurma, önce şu 3 gap'i kapat»; spam başvuru yerine kaliteli başvuru |

---

## Gelir kapıları

| Öncelik | Kapı | Model | Ne zaman |
|---------|------|-------|----------|
| **1** | Bootcamp / kurum lisansı | Cohort başı X ₺/yıl (50–200 öğrenci) | MVP + gerçek skor (ilk para) |
| **2** | Öğrenci Pro | 99–299 ₺/ay veya bootcamp sonrası 3 aylık paket | Faz 2 sonrası |
| **3** | Eğitim affiliate | Udemy/Coursera yönlendirme komisyonu | Sprint 2'den itibaren (ölçümle) |
| **4** | Sertifika partner | AWS, Google sertifika prep yönlendirme | Orta vade |
| **5** | İşveren talent pipeline | Hazır aday havuzu listeleme | Uzun vade |
| **6** | White-label | Üniversite kariyer merkezi markalı panel | 12+ ay |

**İlk para muhtemelen B2B (bootcamp lisansı):** bireysel öğrenci ödemeden önce gerçek skor görmeli; kurum ölçülebilir mezuniyet çıktısı için öder.

**Kaçınılacak erken tuzak:** Kendi kursu satmak (v001 kuralıyla çelişir, Udemy ile doğrudan rekabet).

---

## Açık strateji soruları (sonraki sürümde yanıtlanacak)

1. İlk ücretli müşteri kim? YZTA mı, bireysel mezun mu, üniversite kariyer merkezi mi?
2. Readiness % metodolojisi jüriye nasıl kanıtlanacak? (Örnek CV seti + beklenen skor)
3. Faz 2 ilan verisi TR'de nasıl toplanacak? (Kariyer.net ToS, LinkedIn kısıtı)
4. Retention: Bootcamp bittikten sonra öğrenci neden aylık ödemeye devam etsin?
5. İlk 90 günde tek püf noktaya odak: merdiven mi, XAI gap mi, cohort dashboard mu?

---

## MVP / sprint bağlantısı

| Sprint / faz | Bu sürümle ilişki |
|--------------|-------------------|
| Sprint 1 | CV giriş noktası (yükle + builder); auth iskeleti |
| Sprint 2 | Merdiven, gap, roadmap, eğitim önerileri → **demo'dan gerçeğe geçiş kritik** |
| Sprint 3 | Sohbet (bağlamlı), mentör paneli → B2B gelir kapısı #1 |
| Faz 2 | İlan eşleştirme, TR pazar SWOT → Pro abonelik ve moat #4 |
| Jüri demosu | Merdiven + SWOT + gap→görev→link zinciri + (ideal) iki zaman noktasında skor farkı |

---

*YZTA Bootcamp Grup 92 — CareerTalent AI*  
*Önceki sürüm: [v001](2026-06-29-v001-urun-degeri.md)*
