Company panelinde bulunması gerekenler


4.1 Genel Bakış

Kurum giriş yaptığında yalnızca genel sayılar değil, yapılması gereken işleri görmeli.

Üst göstergeler
Aktif pozisyon
Yeni başvuru
Değerlendirme bekleyen aday
Teknik ekip incelemesi bekleyen aday
Kısa listedeki aday
Bu ay kullanılan değerlendirme hakkı
Yapılacaklar
Backend ilanında 12 yeni aday incelenmeyi bekliyor.
4 aday teknik görevi tamamladı.
2 aday için teknik yönetici puanı eksik.
QA ilanının başvuru süresi yarın bitiyor.
3 aday veri saklama süresinin sonuna yaklaştı.
Kısa özet
Başvuru → değerlendirme tamamlama oranı
Değerlendirme → görüşme oranı
Ortalama kısa liste oluşturma süresi
En fazla aday kaybedilen aşama

Dashboard şu anda mevcut; onu bu işe alım göstergeleriyle zenginleştirmek gerekir.

4.2 Pozisyonlar

Company panelinin ana modülü burası olmalı.

Pozisyon listesi

Sekmeler:

Taslak
Yayında
Başvurusu durduruldu
Kapalı
Arşiv

Her pozisyonda:

Başlık
Departman
Konum
Çalışma biçimi
Açılış tarihi
Başvuru sayısı
Değerlendirme tamamlayanlar
Kısa listedekiler
Sorumlu işe alım uzmanı
Sorumlu teknik yönetici
Yeni pozisyon oluşturma

Kurum ilan metnini yapıştırır veya formu doldurur.

Alanlar:

Pozisyon adı
Departman
Seviye
Çalışma biçimi
Konum
Ücret aralığı — isteğe bağlı
Pozisyonun gerçek görevleri
Vazgeçilmez yetenekler
Tercih edilen yetenekler
Öğrenilebilir yetenekler
Deneyim beklentisi
Dil ve çalışma izni şartları
Son başvuru tarihi
İşe başlangıç hedefi
Yapay zekâ desteği

Sistem ilanı inceleyerek öneri sunmalı:

Belirsiz şartlar
Birbiriyle çelişen gereksinimler
Gereğinden yüksek deneyim beklentisi
İşle ilgisi zayıf şartlar
Değerlendirmede ölçülmesi gereken yetenekler
Önerilen ağırlıklar

Fakat kurum onaylamadan hiçbir ölçüt kesinleşmemeli.

4.3 Pozisyon ayrıntı ekranı

Her pozisyon içinde şu sekmeler olmalı:

Özet
İlan durumu
Sorumlular
Başvuru bağlantısı
Süreç özeti
Son hareketler
Gereksinimler
Vazgeçilmezler
Tercih edilenler
Öğrenilebilirler
Ağırlıklar
Ön koşullar
Skor sürümü
Başvurular
Aday listesi
Aşama
Tamamlanma durumu
Eksik belgeler
Son işlem tarihi
Değerlendirme
Kullanılan görevler
Süre
İzin verilen araçlar
Puanlama anahtarı
Başarı eşiği
İnsan incelemesi gereksinimi
Karşılaştırma
Yan yana aday karşılaştırması
Kanıtlar
Belirsizlikler
Teknik görev sonuçları
Etkinlik Geçmişi
Ölçüt değişiklikleri
Aday aşama değişiklikleri
Notlar
İnsan kararları
Kim hangi işlemi yaptı?
Ayarlar
İlanı durdurma
Kapatma
Kopyalama
Arşivleme
Veri saklama süresi
4.4 Adaylar ve başvurular

Company panelindeki “aday”, kurumun gördüğü global aday profili değildir.

Kurum yalnızca:

Kendi pozisyonuna yapılan başvuruyu ve adayın o başvuruda paylaşmayı kabul ettiği verileri

görmelidir.

Başvuru listesi
Aday
Başvurduğu pozisyon
Aşama
Temel şartlar
Yetenek uyumu
Değerlendirme durumu
Kanıt güveni
Son işlem
Sorumlu kişi
Süreç aşamaları

Örnek:

Yeni başvuru
→ ilk inceleme
→ değerlendirme daveti
→ değerlendirme tamamlandı
→ teknik inceleme
→ kısa liste
→ görüşme
→ teklif
→ işe alındı / süreç kapandı

Aşamalar kurum tarafından özelleştirilebilir; ancak ilk sürümde fazla serbestlik vermek yerine standart bir süreç sunmak daha doğru olur.

4.5 Aday başvuru ayrıntısı

Bu ekran company panelinin en kritik ekranlarından biridir.

Kimlik alanı

İlk incelemede isteğe bağlı olarak gizlenebilir:

İsim
Fotoğraf
Yaş
Cinsiyet çağrıştırabilecek bilgiler
Tam adres
Başvuru özeti
Başvuru tarihi
Paylaşılan CV sürümü
Pozisyon
Mevcut aşama
Son tarih
Adayın verdiği izinler
Şart karşılaştırması
Şart	Durum	Kanıt
Laravel	Güçlü	İki iş deneyimi, iki proje
MySQL	Güçlü	İş deneyimi ve değerlendirme
Docker	Belirsiz	Beyan var, yeterli kanıt yok
Otomatik test	Eksik	Kanıt bulunamadı
Teknik değerlendirme
Genel sonuç
Alt beceri sonuçları
Gönderilen çalışma
Kod inceleme notları
Yapay zekâ açıklaması
İnsan puanı
Yapay zekâ ve insan puanı arasındaki fark
Kanıtlar
Projeler
GitHub
Sertifikalar
İş deneyimi
Genel değerlendirme sonuçları
Kuruma özel görev
İnsan kararı
Sonraki aşamaya geçir
Ek değerlendirme iste
Teknik yöneticiye gönder
Kısa listeye ekle
Süreci kapat
İnsan incelemesi bekliyor

Her karar için gerekçe alanı olmalı.

4.6 Aday karşılaştırma ekranı

Kullanıcı iki ile beş adayı yan yana seçebilmeli.

Alan	Aday A	Aday B	Aday C
Vazgeçilmez şartlar	5/5	4/5	5/5
Teknik görev	86	74	91
Kanıt güveni	Yüksek	Orta	Yüksek
Hata ayıklama	91	68	84
Kod kalitesi	82	88	79
Teknik iletişim	74	91	72
Belirsiz alanlar	Docker	API ölçeği	Takım deneyimi

Bu ekran yalnızca genel puana göre sıralama yapmamalı.

Her aday için:

Neden güçlü?
Neden zayıf?
Hangi konuda veri yok?
Hangi sonuç yapay zekâ çıkarımı?
Hangi sonuç insan tarafından doğrulandı?

gösterilmeli.

4.7 Değerlendirme merkezi

Kurum burada değerlendirme şablonları oluşturmalı.

Değerlendirme türleri
Çoktan seçmeli teknik sorular
Kod görevi
Hatalı kodu düzeltme
Kod inceleme
SQL görevi
API tasarım görevi
Vaka çalışması
Teknik yazılı açıklama
Yapılandırılmış görüşme
Şablon alanları
Değerlendirme adı
İlgili meslek
Seviye
Ölçülen yetenekler
Süre
Son teslim tarihi
İzin verilen araçlar
Yapay zekâ kullanım kuralı
Puanlama anahtarı
İnsan incelemesi gerekip gerekmediği
Sonucun yeniden kullanılabilir olup olmadığı
Geçerlilik süresi

İlk sürümde kurumun sınırsız değerlendirme üretmesine izin vermek yerine doğrulanmış hazır şablonlar + düzenlenebilir alanlar sunmak daha güvenli olur.

4.8 Görüşme ve puan kartları

Teknik görevden sonra yapılandırılmış görüşme yapılmalı.

Her görüşmeci aynı puan kartını doldurmalı:

Teknik derinlik
Problem çözme
Teknik iletişim
Gereksinimi anlama
Kararlarını açıklama
İş birliği yaklaşımı
Pozisyona özel ölçütler

Her alan için:

1–5 puan
Gözlemlenen kanıt
Not
Görüşmecinin güven düzeyi

bulunmalı.

Bu sayede “bence iyi aday” gibi belirsiz kararlar azalır.

4.9 Ekip ve yetkiler

Ekip modülü halihazırda var ve sahip, yönetici, işe alım uzmanı, teknik yönetici ve görüntüleyici rollerini destekliyor.

Fakat izinler genişletilmeli.

Önerilen izinler:

dashboard.view

jobs.view
jobs.create
jobs.update
jobs.publish
jobs.close

applications.view
applications.manage
applications.identity_reveal

assessments.view
assessments.create
assessments.score

scorecards.view
scorecards.submit
decisions.make

reports.view
reports.export

members.view
members.invite
members.manage

organization.update
billing.view
billing.manage

audit.view
privacy.manage

Teknik yönetici faturalamayı görmemeli; işe alım uzmanı kurum sahibinin yetkisini değiştirememeli.

4.10 Raporlar

İlk sürümde sınırlı ama işe yarayan raporlar:

İlan başına başvuru
Değerlendirme tamamlama oranı
Aşama geçiş oranları
Ortalama kısa liste oluşturma süresi
Teknik yöneticinin değerlendirdiği aday sayısı
Kaynak bazında aday kalitesi
Özgeçmiş puanı ile teknik görev arasındaki ilişki
Görüşmeciler arası puan uyumu
Adayların en fazla zorlandığı yetenekler

“Çalışan performansı” veya “doğru işe alım” gibi iddialı raporlar yeterli uzun dönem verisi oluşmadan sunulmamalı.

4.11 Gizlilik ve denetim

Company panelinde ayrı bir alan bulunmalı:

Aday izinleri
Veri saklama süreleri
Silme talepleri
Dışa aktarma talepleri
İnsan incelemesi talepleri
Kimlik açma geçmişi
Ölçüt değişiklik geçmişi
Yapay zekâ model ve skor sürümü
Kim hangi adayı ne zaman görüntüledi?
Kim hangi kararı değiştirdi?

Bu alan kurumsal müşterilerde güven sağlar.

4.12 Kullanım ve faturalandırma
Mevcut paket
Aktif ilan sınırı
Kullanıcı koltuğu
Kullanılan değerlendirme
Kalan değerlendirme hakkı
Aylık kullanım
Faturalar
Paket yükseltme
Fazla kullanım uyarıları

Bu bölüm ilk pilotta elle yönetilebilir; self-servis ödeme sonraki aşamaya bırakılabilir.

5. Önerilen company panel menüsü
Genel Bakış

İşe Alım
├── Pozisyonlar
├── Başvurular
├── Aday Karşılaştırma
├── Değerlendirmeler
└── Görüşmeler

Raporlar
├── İşe Alım Hunisi
├── Değerlendirme Sonuçları
└── Ekip Performansı

Kurum
├── Ekip ve Yetkiler
├── Kurum Profili
├── Gizlilik ve Denetim
├── Kullanım ve Paket
└── Bağlantılar

Ajans hesabında ayrıca:

Müşteriler
├── Müşteri çalışma alanları
├── Müşteri pozisyonları
├── Paylaşılan kısa listeler
└── Beyaz etiketli raporlar

bulunabilir.

6. İlk sürüm için neyi gerçekten yapmalıyız?

Company panelini bir anda on modülle doldurmak yerine şu çekirdekle başlamalıyız:

Aday paneline eklenecek altı zorunlu özellik
Birden fazla CV sürümü
Başvuruya özel paylaşım ekranı
Başvuru anı profil kopyası
Bekleyen ve tamamlanan değerlendirmeler
Değerlendirme sonucu paylaşım izinleri
Veri düzeltme ve insan incelemesi talebi
Company paneline eklenecek sekiz zorunlu özellik
Pozisyon oluşturma
İlan gereksinimleri ve ağırlıkları
Özel başvuru bağlantısı
Başvuru listesi ve aşamaları
Aday başvuru ayrıntısı
Teknik değerlendirme gönderme
İki–beş aday karşılaştırma
Kısa liste ve gerekçeli insan kararı

Bunların dışında kalan raporlar, bağlantılar, ajans müşterileri ve gelişmiş faturalama ikinci aşamaya bırakılabilir.

Net değerlendirme

Aday paneli yüzey olarak yaklaşık dörtte üç oranında hazır. B2B’ye geçiş için eksik olan bölüm yeni kariyer özellikleri değil; kurumla kurulan başvuru bağlantısı, veri paylaşımı, başvuru anı kopyası ve değerlendirme merkezi.

Company paneli ise yalnızca temel kabuğa sahip. Giriş, tenant üyeliği, kurum profili, ekip ve yetki iskeleti kurulmuş durumda; ancak pozisyon, başvuru, değerlendirme, karşılaştırma ve karar modülleri henüz eklenmeli. Mevcut tenant ve üyelik yaklaşımı doğru başlangıçtır.

En doğru geliştirme sırası:

1. Pozisyon oluşturma
2. Özel başvuru bağlantısı
3. Aday başvuru anı kopyası
4. Kurum başvuru listesi
5. Değerlendirme gönderme
6. Aday sonuç kartı
7. Aday karşılaştırma
8. İnsan kararı ve denetim kaydı

Bu sekiz adım tamamlandığında mevcut aday paneli ile company paneli gerçekten birbirine bağlanmış olur.