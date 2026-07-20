# Company Dashboard İşe Alım Veri Sözleşmesi — v006

| | |
|---|---|
| **Sürüm** | v006 |
| **Tarih** | 20 Temmuz 2026 |
| **Önceki sürüm** | [v005 B2B teknik işe alım güncel](2026-07-19-v005-b2b-teknik-ise-alim-guncel.md) |
| **Durum** | Uygulama planı onaylandı |
| **Kapsam** | `/company` dashboard, pozisyon ve aday hedef listeleri |

---

## 1. Amaç

Kurum kullanıcısı giriş yaptığında yalnızca ekip ve üyelik sayılarını değil, işe alım sürecinde **bugün yapılması gereken işleri** görmelidir.

Dashboard üç soruya cevap verir:

1. İşe alım kuyruğunda kaç iş var?
2. Önce hangi işlem yapılmalı?
3. Adaylar en çok hangi aşamada kaybediliyor?

## 2. Mevcut durum ve sınır

Mevcut `/company` dashboard yalnızca şu üyelik verilerini gösteriyor:

- toplam ekip üyesi,
- aktif ekip üyesi,
- bekleyen davet.

Mevcut veritabanında kuruma bağlı pozisyon, başvuru, değerlendirme, teknik puan kartı, aşama geçmişi ve değerlendirme kullanım hakkı tabloları bulunmuyor.

`job_applications` tablosu adayın kişisel başvuru takibi içindir. `organization_id` taşımadığı için B2B dashboard hesabında kullanılmayacak ve yeni kurumsal tablolara otomatik taşınmayacak.

## 3. Onaylanan ürün kararları

| Konu | Karar |
|---|---|
| Yeni başvuru | Kurum tarafından henüz incelenmemiş başvuru |
| Performans dönemi | Varsayılan son 30 gün; 7, 30 ve 90 gün seçilebilir |
| Aşama analizi | Değiştirilemez aşama olay geçmişinden hesaplanır |
| Değerlendirme hakkı | Harcama ve iadeleri tutan idempotent kullanım defterinden hesaplanır |
| Dashboard aksiyonları | Her yapılacak maddesi filtrelenmiş pozisyon veya aday listesine gider |
| Tenant sınırı | Bütün sorgular aktif `X-Organization-ID` kapsamıyla çalışır |

## 4. Dashboard bilgi mimarisi

### 4.1 Üst göstergeler

Altı gösterge, masaüstünde 3x2; tablette 2x3; mobilde tek kolon gösterilir.

| Gösterge | Kesin tanım | Tıklanınca |
|---|---|---|
| Aktif pozisyon | `status=open` ve son başvuru tarihi geçmemiş pozisyon | `/{kurum}/pozisyonlar?status=open` |
| Yeni başvuru | `first_reviewed_at IS NULL` olan aktif başvuru | `/{kurum}/adaylar?queue=new` |
| Değerlendirme bekleyen aday | Zorunlu değerlendirmesi atanmış fakat tamamlanmamış tekil başvuru | `/{kurum}/adaylar?queue=assessment_pending` |
| Teknik ekip incelemesi bekleyen aday | Zorunlu değerlendirmeleri tamamlanmış, teknik puan kartı bekleyen tekil başvuru | `/{kurum}/adaylar?queue=technical_review` |
| Kısa listedeki aday | Güncel aşaması `shortlisted` olan tekil başvuru | `/{kurum}/adaylar?stage=shortlisted` |
| Bu ay kullanılan değerlendirme hakkı | Kurum saat dilimindeki ay için kullanım defteri net toplamı | `/{kurum}/degerlendirmeler?kullanim=bu-ay` |

Değerlendirme hakkı kartı `kullanılan / toplam` biçiminde gösterilir. Örnek: `42 / 100`. Limitsiz planda `42 / Sınırsız` gösterilir.

### 4.2 Yapılacaklar

Yapılacaklar statik bildirim değildir. Güncel tablolardan üretilen, gruplanmış operasyon kuyruğudur.

| Kural | Örnek metin | Hedef |
|---|---|---|
| Pozisyonda incelenmemiş başvuru var | Backend ilanında 12 yeni aday incelenmeyi bekliyor. | Pozisyon adayları, `queue=new` |
| Teknik görev tamamlandı | 4 aday teknik görevi tamamladı. | Adaylar, `queue=technical_review` |
| Teknik yönetici puanı eksik | 2 aday için teknik yönetici puanı eksik. | Adaylar, `queue=scorecard_missing` |
| Pozisyonun son başvuru tarihi yaklaştı | QA ilanının başvuru süresi yarın bitiyor. | Pozisyon detayı |
| Veri saklama süresi yaklaştı | 3 aday veri saklama süresinin sonuna yaklaştı. | Adaylar, `queue=retention_due` |

Öncelik sırası:

1. Veri saklama süresi,
2. yarın veya bugün bitecek pozisyon,
3. eksik teknik yönetici puanı,
4. tamamlanan teknik görev,
5. yeni başvuru.

İlk ekranda en fazla 5 grup gösterilir. “Tümünü gör” ilgili filtreli listeye gider. Sonuç yoksa sahte örnek metin değil, “Şu anda bekleyen işlem yok” boş durumu gösterilir.

### 4.3 Kısa özet

Özet alanı varsayılan son 30 günü gösterir. Kullanıcı 7, 30 veya 90 günü seçebilir. Seçim URL sorgusunda tutulur: `?period=7d|30d|90d`.

| Metrik | Hesap |
|---|---|
| Başvuru → değerlendirme tamamlama oranı | Dönemde başvuran ve değerlendirme gereken adaylardan değerlendirmeyi tamamlayanlar / değerlendirme gereken başvurular |
| Değerlendirme → görüşme oranı | Dönemde değerlendirmeyi tamamlayanlardan ilk görüşme aşamasına geçenler / değerlendirmeyi tamamlayanlar |
| Ortalama kısa liste oluşturma süresi | Dönemde ilk kez kısa listeye girenlerin `ilk_shortlist_at - applied_at` sürelerinin ortalaması |
| En fazla aday kaybedilen aşama | Dönemde `rejected` veya `withdrawn` geçişinden hemen önceki aşamalar içinde en yüksek adet |

Payda sıfırsa yüzde `0` değil `—` gösterilir. Süre 24 saatten azsa saat, diğer durumlarda bir ondalıkla gün gösterilir.

Üst göstergeler anlık operasyon durumudur; dönem filtresi yalnızca kısa özeti etkiler. “Bu ay kullanılan hak” her zaman kurum saat dilimindeki takvim ayını kullanır.

## 5. İşe alım aşama sözleşmesi

Başvurunun güncel aşaması hızlı listeleme için başvuru üzerinde tutulur. Her değişiklik ayrıca değiştirilemez olay kaydı üretir.

```text
new
  -> assessment_pending
  -> assessment_in_progress
  -> technical_review
  -> shortlisted
  -> interview
  -> offer
  -> hired

Her aktif aşama -> rejected | withdrawn
```

Kurallar:

- `current_stage` yalnızca geçerli bir aşama olayıyla değişir.
- Aynı `idempotency_key` ile ikinci geçiş oluşturulmaz.
- Olay geçmişi güncellenmez veya silinmez.
- `first_reviewed_at`, kurum üyesi aday detayını ilk kez açtığında bir kez yazılır.
- Kısa liste süresi, ilk `shortlisted` olayıyla hesaplanır; geri dönüşler süreyi değiştirmez.
- Ret kararı `reason_code` taşır; serbest not ayrı ve erişim kontrollüdür.

## 6. Veri modeli

### 6.1 `recruiting_positions`

| Alan | Tür / kural |
|---|---|
| `id` | UUID, PK |
| `organization_id` | UUID, zorunlu FK ve indeks |
| `title` | varchar(160) |
| `status` | `draft`, `open`, `paused`, `closed`, `archived` |
| `application_deadline` | timezone-aware datetime, nullable |
| `opened_at`, `closed_at` | timezone-aware datetime |
| `created_by_membership_id` | kurumdaki üyelik FK |
| `created_at`, `updated_at` | timezone-aware datetime |

İndeksler: `(organization_id, status)`, `(organization_id, application_deadline)`.

### 6.2 `recruiting_applications`

| Alan | Tür / kural |
|---|---|
| `id` | UUID, PK |
| `organization_id` | UUID, zorunlu FK |
| `position_id` | UUID, zorunlu FK |
| `candidate_user_id` | mevcut aday kullanıcısına FK |
| `snapshot_id` | başvuru anındaki paylaşım kopyasına FK |
| `current_stage` | işe alım aşaması |
| `first_reviewed_at` | nullable; ilk kurum incelemesi |
| `applied_at` | timezone-aware datetime |
| `retention_expires_at` | nullable; saklama son tarihi |
| `created_at`, `updated_at` | timezone-aware datetime |

Benzersizlik: aynı adayın aynı pozisyona tek aktif başvurusu. İndeksler: `(organization_id, current_stage)`, `(organization_id, first_reviewed_at)`, `(organization_id, retention_expires_at)`, `(position_id, current_stage)`.

### 6.3 `recruiting_application_snapshots`

Başvuru anında adayın paylaşmayı kabul ettiği CV, profil, kanıt ve değerlendirme referanslarının değiştirilemez kopyasıdır. Ana aday profili daha sonra değişse bile eski başvuru görünümü değişmez.

Asgari alanlar: `id`, `application_id`, `schema_version`, `payload`, `consent_scope`, `created_at`.

### 6.4 `recruiting_application_stage_events`

| Alan | Tür / kural |
|---|---|
| `id` | UUID, PK |
| `organization_id`, `position_id`, `application_id` | zorunlu tenant zinciri |
| `from_stage`, `to_stage` | geçerli aşamalar |
| `reason_code` | ret/geri çekilme nedeni, nullable |
| `actor_membership_id` | kurum işlemi ise üyelik FK, nullable |
| `idempotency_key` | tenant içinde benzersiz |
| `occurred_at` | timezone-aware datetime |

İndeksler: `(organization_id, occurred_at)`, `(position_id, to_stage, occurred_at)`, `(application_id, occurred_at)`.

### 6.5 `recruiting_assessments`

Asgari alanlar: `id`, `organization_id`, `application_id`, `template_id`, `template_version`, `required`, `status`, `assigned_at`, `started_at`, `completed_at`, `expires_at`.

Durumlar: `assigned`, `in_progress`, `completed`, `expired`, `cancelled`.

### 6.6 `recruiting_scorecards`

Teknik ekip incelemesini ve eksik yönetici puanını belirler.

Asgari alanlar: `id`, `organization_id`, `application_id`, `reviewer_membership_id`, `scorecard_type`, `status`, `requested_at`, `due_at`, `submitted_at`, `overall_score`, `payload`.

Durumlar: `pending`, `in_progress`, `submitted`, `cancelled`.

### 6.7 `organization_assessment_entitlements`

Planın değerlendirme hakkını dönem bazında sabitler: `organization_id`, `period_start`, `period_end`, `quota`, `source`, `created_at`. `quota=NULL` limitsiz plan demektir.

### 6.8 `assessment_usage_ledger`

| Alan | Tür / kural |
|---|---|
| `id` | UUID, PK |
| `organization_id` | UUID, zorunlu FK |
| `assessment_id` | değerlendirme FK |
| `entry_type` | `consume`, `credit`, `adjustment` |
| `units` | işaretli integer; sıfır olamaz |
| `idempotency_key` | tenant içinde benzersiz |
| `reason_code` | zorunlu sınıflandırma |
| `occurred_at` | timezone-aware datetime |

Kayıtlar güncellenmez. Düzeltme ters işaretli yeni kayıtla yapılır.

### 6.9 Kurum işe alım ayarları

İlk sürümde `organizations.settings.recruiting` altında sürümlü yapı kullanılabilir:

```json
{
  "schema_version": 1,
  "timezone": "Europe/Istanbul",
  "retention_days": 180,
  "retention_warning_days": [30, 7, 1]
}
```

Sorgulanan veya raporlanan ayarlar çoğalırsa ayrı `organization_recruiting_settings` tablosuna taşınır. Başvuruya yazılmış `retention_expires_at`, sonraki ayar değişiklikleriyle sessizce değiştirilmez.

## 7. Tenant ve yetki sözleşmesi

Her backend sorgusu mevcut `X-Organization-ID` bağlamını kullanır. URL slug yalnızca frontend çalışma alanıdır; veri yetkisi değildir.

Yeni yetkiler:

- `positions.view`, `positions.write`, `positions.delete`
- `applications.view`, `applications.write`
- `assessments.view`, `assessments.write`
- `scorecards.view`, `scorecards.submit`
- mevcut `dashboard.view`

`delete` yetkisi normal akışta arşivleme/kapama yapar. Saklama süresi dolan kişisel verinin fiziksel silme veya anonimleştirme işi ayrı, denetlenebilir retention sürecidir.

Tenant güvenliği:

- Bütün ilişkilerde `organization_id` bulunur.
- Servis sorguları `organization_id` filtresi olmadan çalıştırılmaz.
- Pozisyon-başvuru-değerlendirme ilişkilerinde çapraz tenant bağını engelleyen birleşik constraint kullanılır.
- Kurum A, Kurum B’nin UUID’sini bilse bile `404` alır; kaydın varlığı sızdırılmaz.
- Dashboard toplamları yalnızca aktif kurum bağlamından hesaplanır.

## 8. API sözleşmesi

### 8.1 Dashboard

```http
GET /api/v1/company/dashboard?period=30d
X-Organization-ID: <uuid>
```

Geçerli dönemler: `7d`, `30d`, `90d`. Geçersiz değer `422` döndürür.

Örnek cevap:

```json
{
  "organization": {"id": "...", "name": "ABC Teknoloji", "slug": "abc-teknoloji"},
  "as_of": "2026-07-20T16:00:00Z",
  "period": {"key": "30d", "from": "2026-06-20T16:00:00Z", "to": "2026-07-20T16:00:00Z"},
  "indicators": {
    "active_positions": 6,
    "new_applications": 12,
    "assessment_pending": 8,
    "technical_review_pending": 4,
    "shortlisted": 5,
    "assessment_usage": {"used": 42, "quota": 100}
  },
  "tasks": [
    {
      "type": "new_applications",
      "priority": 50,
      "count": 12,
      "position": {"id": "...", "title": "Backend Developer"},
      "target": "/abc-teknoloji/pozisyonlar/.../adaylar?queue=new"
    }
  ],
  "summary": {
    "application_to_assessment_rate": 0.64,
    "assessment_to_interview_rate": 0.31,
    "average_shortlist_hours": 52.5,
    "largest_loss_stage": {"stage": "technical_review", "count": 7}
  }
}
```

Backend çevrilmiş cümle döndürmez. `type`, `count`, pozisyon ve hedef döndürür; metni panel diliyle frontend üretir.

### 8.2 Hedef listeler

```http
GET /api/v1/company/positions?status=open
GET /api/v1/company/positions/{position_id}/applications?queue=new
GET /api/v1/company/applications?queue=technical_review
GET /api/v1/company/applications?queue=scorecard_missing
GET /api/v1/company/applications?queue=retention_due
GET /api/v1/company/applications?stage=shortlisted
GET /api/v1/company/assessment-usage?period=current_month
```

Frontend route’ları:

```text
/{organizationSlug}/pozisyonlar
/{organizationSlug}/pozisyonlar/{position}
/{organizationSlug}/pozisyonlar/{position}/adaylar
/{organizationSlug}/adaylar
/{organizationSlug}/degerlendirmeler
```

Filtreler URL’de kalır. Böylece dashboard bağlantısı yenileme, geri dönme ve ekip içinde link paylaşma sonrasında aynı listeyi açar.

## 9. Sorgu ve performans yaklaşımı

İlk sürümde doğru indeksli SQL aggregate sorguları kullanılır; ayrı sayaç tablosu veya cache eklenmez.

- Kartlar tek dashboard servisinde paralel olmayan, aynı `as_of` zamanını kullanan sorgularla hesaplanır.
- Başvurular `count(distinct application_id)` ile sayılır; birden fazla değerlendirme adayı iki kez saydırmaz.
- Aşama metrikleri olay tablosundan, operasyon kartları güncel durum tablolarından hesaplanır.
- Liste endpoint’leri cursor veya sayfa bazlı pagination kullanır.
- Gerçek veriyle p95 dashboard süresi hedefi `500 ms` altıdır.
- Bu hedef indeksli sorguyla sağlanamazsa ölçüm sonrası kısa süreli tenant cache veya özet tablo değerlendirilir.

## 10. Ekran davranışı

- Dashboard başlığında aktif kurum ve “Son 30 gün” dönem seçici bulunur.
- Kota kartı yüzde progress ve sayısal değer gösterir; yüzde 80 ve 100 eşikleri renk/ikon yanında metinle de belirtilir.
- Yapılacak kartının tamamı klavye ile erişilebilir bağlantıdır.
- Yüklenirken iskelet, hata halinde tekrar dene, veri yoksa gerçek boş durum gösterilir.
- Üyelik sayıları silinmez; işe alım alanının altında ikincil “Kurum ve ekip” özetine taşınır.
- Türkçe ve İngilizce metinler Laravel çeviri anahtarlarından üretilir; backend panel dili taşımaz.

## 11. Uygulama sırası

### Faz 1 — Domain ve migration

1. Pozisyon, başvuru snapshot’ı, aşama olayı, değerlendirme, puan kartı, entitlement ve kullanım defteri tabloları.
2. Tenant constraint, indeks ve enum/check constraint’leri.
3. Aşama geçiş ve kullanım defteri servisleri.

### Faz 2 — Pozisyon ve aday listeleri

1. Yeni company yetkileri.
2. Pozisyon CRUD ve arşivleme.
3. Aday listesi, kuyruk filtreleri ve aday detayına ilk inceleme kaydı.
4. Teknik puan kartı gönderimi.

### Faz 3 — Dashboard API ve arayüz

1. Altı gösterge.
2. Dinamik yapılacaklar.
3. 7/30/90 günlük kısa özet.
4. Bütün kart ve görev bağlantılarının filtreli listelere bağlanması.

### Faz 4 — Pilot ve gözlem

1. Test kurumunda gerçekçi fixture ile doğrulama.
2. Sorgu planı ve p95 süre ölçümü.
3. Kota sınırı ve retention uyarısı gözlemi.
4. Tenant erişim logları ve hata oranı takibi.

## 12. Test ve kabul kriterleri

### Backend contract/integration

- Kurum A’nın bütün dashboard sonuçları yalnızca Kurum A verisini içerir.
- Kurum A, Kurum B pozisyon/başvuru UUID’sine `404` alır.
- Aynı başvuruda iki değerlendirme olsa bile aday kartlarda bir kez sayılır.
- Aday detayı ilk açılışta `first_reviewed_at` yazar; sonraki açılışlarda değiştirmez.
- Aynı aşama `idempotency_key` değeri ikinci olay ve ikinci yan etki üretmez.
- Aşama geçmişi doğrudan update/delete edilemez.
- Kullanım defteri consume/credit toplamı doğru; aynı anahtar iki kez harcama üretmez.
- `7d`, `30d`, `90d` sınırları ve saat dilimi geçişleri test edilir.
- Paydası sıfır olan oran `null` döner.
- Retention eşiği ve son başvuru tarihi bugün/yarın sınırları test edilir.
- Yetkisiz üyeler ilgili endpoint’ten `403` alır.

### Frontend feature/component

- Altı gösterge API değerini ve doğru hedef URL’yi gösterir.
- Dönem seçimi URL ve API sorgusunu günceller.
- Görev tipi panel dilinde doğru cümleye çevrilir.
- Kota limitsiz, yüzde 80 ve tükenmiş durumları gösterilir.
- API hata ve boş durumları sahte sayı göstermez.

### Browser/E2E

1. Company kullanıcısı giriş yapar ve kendi kurum dashboard’unu açar.
2. “Yeni başvuru” kartına tıklar; yalnızca incelenmemiş adayları görür.
3. Teknik inceleme görevine tıklar; doğru pozisyon/aday filtresi korunur.
4. Dönemi 30 günden 90 güne alır; özet değişir, anlık kartlar değişmez.
5. İkinci tenant UUID’siyle doğrudan URL denemesi veri sızdırmaz.

## 13. Tamamlanmış sayılma koşulu

Dashboard ancak şu koşullar birlikte sağlandığında tamamlanmış sayılır:

- Kartlar gerçek tenant verisinden hesaplanıyor.
- Bütün yapılacaklar tıklanabilir ve doğru filtreli listeyi açıyor.
- Dönüşüm metrikleri aşama olay geçmişinden geliyor.
- Aylık kullanım, değişmez kullanım defteriyle doğrulanabiliyor.
- Boş kurumda sahte örnek sayı görünmüyor.
- Tenant izolasyonu backend contract testi ve browser smoke ile kanıtlanıyor.
- Migration, backend full suite, frontend suite, build ve canlı HTTP readback geçiyor.

---

*CareerTalent AI — Company B2B teknik işe alım ürünü*
