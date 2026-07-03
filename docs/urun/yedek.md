1. Öğrenci CV’den sonra nereye gidiyor? (Hedef ürün akışı)
Ürün dokümanınızdaki (docs/urun/2026-06-29-v001-urun-degeri.md) zincir şöyle:

CV yükle / oluştur
    ↓
AI parse → yapılandırılmış profil (yetenekler, projeler, deneyim)
    ↓
Her hedef meslek için readiness % (A/B/C merdiven)
    ↓
Seçilen meslek → kanıtlı SWOT (CV’den S/W, pazardan O/T)
    ↓
Skill gap listesi
    ↓
Haftalık yol haritası + görevler
    ↓
Gap’e göre filtrelenmiş eğitim/sertifika önerileri (harici link)
    ↓
Görev tamamlanınca skor güncellenir → ilerleme hikayesi
    ↓
(Faz 2) Gerçek ilan eşleştirmesi: “Bu ilana %67 uyumlusun çünkü…”
    ↓
(Faz 3) Bağlamlı kariyer asistanı (CV + gap + roadmap bağlamında)
Öğrencinin hissetmesi gereken döngü: “CV’m bir kez okunur → hedef netleşir → her hafta ne yapacağım belli → eksik kapanınca skorum artar → işe başvuruya yaklaştım.”

ChatGPT’den fark burada: tek seferlik metin değil, ölçülebilir ve tekrarlanabilir sistem.

2. Bugün panelde gerçekte ne var?
Kod ve demo veriye göre UI iskeleti güçlü, zeka katmanı henüz demo:

Adım	Vizyon	Bugün
CV oluştur
Harvard format, TR/EN
Var, PDF, isteğe bağlı bölümler
CV yükle
PDF → FastAPI → Gemini parse
Profilde dosya adı; gerçek parse yok
Yetenek analizi
CV’den çıkarım
demoSkillAnalysis() sabit radar
Kariyer merdiveni A/B/C
Algoritmik skor
PanelDemoData statik
SWOT
Kanıtlı, açıklanabilir
Demo kartlar
Yol haritası / görevler
API + skor güncelleme
Demo + localStorage görevler
Eğitim önerileri
Gap → filtreli link
Seed liste (Coursera, Udemy vb.)
Sohbet
Bağlamlı asistan
“Yakında”
Auth / cohort / mentör
Sprint 1–3
Henüz yok (panel açık)
Yani öğrenci bugün panele girince güzel bir “kariyer kokpiti” görüyor, ama “CV’mi yükledim, sistem beni gerçekten tanıdı” hissi localStorage + demo ile sınırlı. Bir sonraki büyük sıçrama: CV → gerçek profil → skorların o profile bağlanması.

3. SWOT (durum değerlendirmesi)
Güçlü (S)
Net ürün hikayesi: merdiven + gap + görev + kaynak (kurs satmıyorsunuz, çakışma yok)
Bootcamp ortağı (YZTA): pilot cohort, jüri demosu, gerçek kullanıcı
Çift dilli CV (TR/EN): Türkiye + uluslararası başvuru segmenti
Harvard CV + ATS odaklı builder: generic “AI CV yazıcı”dan ayrışma başlangıcı
Mimari plan (Laravel UI + FastAPI zeka): ölçeklenebilir
Zayıf (W)
Çekirdek değer (readiness %, gap) henüz kanıtlanmış algoritma + gerçek CV parse değil
Veri uçurumu: öğrenci güvenir, skor demo kalırsa churn
İki stack koordinasyon maliyeti (Sprint 2 retro riski)
Pazar SWOT (O/T) Faz 2’ye bağlı; şimdilik hikaye güçlü, veri zayıf
Rakipler “AI CV” diye pazarlıyor; sizin farkınızı 10 saniyede anlatmak zor
Fırsat (O)
TR’de bootcamp mezunu dalgası + “iş bulamıyorum” acısı büyük
ChatGPT kullanımı yaygın ama süreklilik ve ölçüm yok; boşluk sizde
B2B: bootcamp / üniversite / kariyer merkezi lisansı (cohort dashboard)
Affiliate / partner: eğitim yönlendirmesinden komisyon (kurs satmadan)
Faz 2 ilan eşleştirme: Kariyer.net “liste”, siz “hazırlık önce”
Tehdit (T)
LinkedIn, Coursera, ChatGPT tek başına “yeterli” algısı
Generic CV scanner SaaS’ları (Rezi, Teal, Jobscan…) anahtar kelime oyununda önde
Skor metodolojisi şeffaf değilse güven kaybı
Bootcamp süresi bitince ürünün “yaşam döngüsü” tanımsız kalırsa tek seferlik kullanım
4. Rakiplerden tamamen ayıracak “püf nokta” adayları
Bunlar birlikte moat oluşturur; tek başına “AI CV” yetmez.

A. Kanıtlı kariyer merdiveni (en güçlü çekirdek)
“Şimdi başvur (A) / 6 haftada hazır (B) / uzun vade (C)”
Skor her madde için açıklanabilir: “SQL eksik → Coursera modül 3 → görev 2.”

Rakip farkı: ChatGPT skala değiştirir; siz aynı CV + aynı rol = aynı skor.

B. “CV satırı → gap maddesi” izlenebilirlik (XAI)
SWOT’taki her W maddesi bir skill_gap kaydına bağlı. Öğrenci tıklayınca: hangi CV satırı, hangi görev, hangi kaynak.

Rakip farkı: Soyut “zayıf yönleriniz…” listesi değil; kanıt zinciri.

C. Haftalık operasyon sistemi (OS), chatbot değil
Dashboard = kokpit: bu hafta 3 görev, 1 tamamlandı, readiness %42→%48.

Rakip farkı: Tek oturum tavsiye değil; bootcamp boyunca ilerleme hikayesi (jüri demosu için altın).

D. TR pazar gerçekliği katmanı (Faz 2 moat)
İlan scraper + “Bu rolde İstanbul’da son 30 günde X ilan, en çok istenen 5 skill.”

Rakip farkı: Global CV araçları TR maaş/ilan/ dil gerçekliğini bilmez.

E. Çift dilli kariyer çıkışı (niş ama keskin)
TR CV + EN CV aynı profilden; gap her dilde ayrı içerik, yapı senkron.

Rakip farkı: Remote / yurtdışı hedefleyen bootcamp mezunları için doğrudan değer.

F. Cohort / mentör görünürlüğü (B2B kilidi)
Mentör tek ekranda: kim %70’te, kim 2 haftadır görev yapmadı.

Rakip farkı: Bireysel araç değil; bootcamp’in ROI kanıtı.

G. “Hazırlık önce, ilan sonra” (Faz 2 positioning)
Kariyer.net önce ilan gösterir. Siz: “Henüz başvurma, önce şu 3 gap’i kapat.”

Rakip farkı: Retention + güven; spam başvuru yerine kaliteli başvuru.

5. Gelir kapıları (öncelik sırasıyla)
Kapı	Model	Neden mantıklı	Ne zaman
1. Bootcamp / kurum lisansı
Cohort başı X ₺/yıl (50–200 öğrenci)
YZTA pilotu, satış döngüsü kısa, mentör değeri net
Şimdi (MVP+gerçek skor)
2. Öğrenci Pro
99–299 ₺/ay veya bootcamp sonrası 3 aylık paket
İlan eşleştirme + sürekli skor güncelleme
Faz 2 sonrası
3. Eğitim affiliate
Udemy/Coursera/Coursera for Campus yönlendirme komisyonu
Kurs satmıyorsunuz; gap→link doğal
Sprint 2’den itibaren (ölçümle)
4. Sertifika / sınav partner
AWS, Google sertifika prep yönlendirme
has_certificate filtresi zaten var
Orta vade
5. İşveren / talent pipeline (Faz 2+)
“Hazır aday havuzu” listeleme ücreti
Readiness % ile ön eleme
Uzun vade, regülasyon dikkat
6. White-label
Üniversite kariyer merkezi markalı panel
B2B ölçek
12+ ay
İlk para muhtemelen B2B (bootcamp lisansı), çünkü:

Bireysel öğrenci ödemeye ikna olmadan önce gerçek skor görmeli
Kurum “ölçülebilir mezuniyet çıktısı” için öder
Cohort dashboard Sprint 3 ile örtüşüyor
Kaçınılması gereken erken tuzak: Kendi kursu satmak (vizyonunuzla çelişir, Udemy ile rekabet edersiniz).

6. Stratejik öneri: “Tek cümlelik ayırıcı”
Pitch’i sadeleştirirseniz:

“CV’nizi bir kez okuruz; hangi işe ne kadar hazır olduğunuzu yüzdeyle söyleriz; eksikleri haftalık plan ve doğru kaynaklarla kapatırız; hazır olunca ilan eşleştiririz.”

ChatGPT = koç konuşur.
CareerTalent = kariyer hazırlık işletim sistemi.

7. Beyin fırtınası için açık sorular (birlikte netleştirebiliriz)
İlk ücretli müşteri kim? YZTA mı, bireysel mezun mu, üniversite kariyer merkezi mi?
Readiness % metodolojisi jüriye nasıl kanıtlanacak? (Örnek CV seti + beklenen skor)
Faz 2 ilan verisi TR’de nasıl toplanacak? (Kariyer.net ToS, LinkedIn kısıtı)
Retention: Bootcamp bittikten sonra öğrenci neden aylık ödemeye devam etsin?
En sert fark: İlk 90 günde sadece bir püf noktaya odaklanmak (merdiven mi, XAI gap mi, cohort mu?)
Özet: Akış vizyonu sağlam; bugün UI öndesiniz, zeka katmanı demo. Moat = ölçülebilir merdiven + kanıtlı gap + haftalık OS + (sonra) TR ilan gerçekliği. Gelir kapısı #1 = bootcamp/kurum B2B; bireysel Pro ve affiliate ikinci dalga.



Öğrenci yolculuğu (hedef ürün)
CV yükle / oluştur
  → AI parse → yapılandırılmış profil
  → Meslek başına readiness % (A/B/C merdiven)
  → SWOT (kanıtlı)
  → Skill gap
  → Haftalık yol haritası + görevler
  → Gap’e göre eğitim önerileri (harici link)
  → Görev tamamlanınca skor güncellenir
  → (Faz 2) Gerçek ilan eşleştirmesi
  → (Faz 3) Bağlamlı kariyer asistanı
Bugün: Panel UI güçlü (CV builder, merdiven, roadmap, görevler, eğitim). Zeka katmanı çoğunlukla demo (PanelDemoData, demoSkillAnalysis). Sohbet “yakında”. Auth/API henüz tam bağlı değil.

SWOT (kısa)
Güçlü
Net hikaye (merdiven + gap + OS), YZTA pilotu, TR/EN CV, kurs satmama stratejisi
Zayıf
Gerçek parse/skor yok, demo güven riski, çift stack maliyeti
Fırsat
ChatGPT süreklilik boşluğu, B2B bootcamp lisansı, affiliate, TR ilan verisi
Tehdit
Generic CV araçları, LinkedIn/ChatGPT “yeterli” algısı, skor şeffaflığı
Rakiplerden ayıran püf noktalar
Kanıtlı kariyer merdiveni (A şimdi / B 6 hafta / C uzun vade)
XAI gap zinciri (CV satırı → gap → görev → kaynak)
Haftalık kariyer OS (chatbot değil, ölçülebilir ilerleme)
TR pazar katmanı (ilan + skill frekansı, Faz 2)
Çift dilli çıkış (TR/EN CV aynı profilden)
Cohort/mentör görünürlüğü (B2B kilidi)
Hazırlık önce, ilan sonra (Kariyer.net’in tersi)
Gelir kapıları (öncelik)
Bootcamp/kurum lisansı (ilk para, cohort dashboard ile)
Öğrenci Pro (Faz 2 sonrası, ilan + sürekli skor)
Eğitim affiliate (gap → link, kurs satmadan)
Sertifika partner yönlendirme
İşveren talent pipeline (uzun vade)
White-label (üniversite kariyer merkezi)
Tek cümle pitch: ChatGPT koç konuşur; CareerTalent CV’den readiness %, haftalık plan ve kaynak zinciri veren kariyer hazırlık işletim sistemi.

İyileştirme önerisi:

[PM/PdM 📋] puan:8/10 — İlk ücretli müşteriyi netleştir (YZTA lisansı vs bireysel Pro); jüri öncesi tek B2B paket fiyatı ve demo senaryosu yazılsın.
[Architect 🏛️] puan:7/10 — Demo → gerçek geçişte tek “source of truth” (FastAPI profil JSON); localStorage demo ile API profilinin birleşme sınırı erken tanımlansın.
[Senior 🛠️] puan:7/10 — Readiness % için 5 örnek CV + beklenen skor “golden set” oluştur; algoritma ve jüri demosu aynı veriyle kanıtlansın.
[UX 🎨] puan:6/10 — Öğrenci CV kaydettikten sonra tek ekran “sıradaki 3 adım” (merdiven → gap → bu haftanın görevi) onboarding funnel’ı churn’ü düşürür.
[CSM 📞 anlık] puan:6/10 — Demo skor görünürken “örnek analiz” etiketi şart; aksi halde güven kaybı ve destek talebi artar.
[QA 🧪] puan:5/10 — test sözleşmesi: (c)[docs] — Bu tur kod değişmedi; ürün kararları docs/urun/v002 olarak yazılıp sprint kabul kriterlerine bağlanmalı.
[CSM 📞 uzun-vadeli] puan:5/10 — Bootcamp bitince retention için “90 günlük işe hazırlık paketi” (haftalık skor + ilan eşleşme) bireysel gelir kapısını açar.