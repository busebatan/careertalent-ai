<?php

namespace App\Data;

class AdminDemoData
{
    /**
     * @return array<string, mixed>
     */
    public static function dashboard(): array
    {
        return [
            'stats' => [
                ['label' => 'Toplam öğrenci', 'value' => '428', 'trend' => '+36 bu hafta'],
                ['label' => 'Ortalama readiness', 'value' => '%64', 'trend' => '+7 puan'],
                ['label' => 'İşe hazır aday', 'value' => '119', 'trend' => '%70+ skor'],
                ['label' => 'Aktif başvuru', 'value' => '1.284', 'trend' => '2.3x takip oranı'],
            ],
            'cohorts' => [
                ['name' => 'YZTA Grup 92', 'students' => 42, 'readiness' => 68, 'risk' => 'Orta', 'focus' => 'Portfolio kanıtı'],
                ['name' => 'Data Analytics Bootcamp', 'students' => 96, 'readiness' => 72, 'risk' => 'Düşük', 'focus' => 'İş başvurusu'],
                ['name' => 'Frontend Career Track', 'students' => 64, 'readiness' => 58, 'risk' => 'Yüksek', 'focus' => 'GitHub proje kanıtı'],
            ],
            'modules' => self::modules(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function modules(): array
    {
        return [
            ['key' => 'students', 'title' => 'Öğrenciler', 'desc' => 'Profil, CV, hedef rol ve panel durumları.', 'admin_route' => 'admin.students', 'panel_route' => 'panel.dashboard', 'metric' => '428 öğrenci'],
            ['key' => 'cohorts', 'title' => 'Cohortlar', 'desc' => 'Bootcamp sınıfları, risk ve readiness takibi.', 'admin_route' => 'admin.cohorts', 'panel_route' => 'panel.roadmap', 'metric' => '12 cohort'],
            ['key' => 'readiness', 'title' => 'Readiness Analitiği', 'desc' => 'Rol bazlı hazırlık skoru ve gap dağılımı.', 'admin_route' => 'admin.readiness', 'panel_route' => 'panel.career-ladder', 'metric' => '%64 ortalama'],
            ['key' => 'skill-passport', 'title' => 'Yetenek Pasaportu', 'desc' => 'Kanıt linki, sertifika ve GitHub doğrulama kuyruğu.', 'admin_route' => 'admin.skill-passport', 'panel_route' => 'panel.skill-passport', 'metric' => '73 bekleyen'],
            ['key' => 'job-radar', 'title' => 'İş Radarı', 'desc' => 'İlan sinyali, maaş aralığı ve skill gap uyarıları.', 'admin_route' => 'admin.job-radar', 'panel_route' => 'panel.job-radar', 'metric' => '312 ilan'],
            ['key' => 'applications', 'title' => 'Başvuru Takibi', 'desc' => 'Kaydedildi/başvuruldu/mülakat/teklif funnelı.', 'admin_route' => 'admin.applications', 'panel_route' => 'panel.applications', 'metric' => '1.284 başvuru'],
            ['key' => 'interviews', 'title' => 'Mülakat Simülasyonu', 'desc' => 'Soru bankası, rubric ve skor trendleri.', 'admin_route' => 'admin.interviews', 'panel_route' => 'panel.interview', 'metric' => '86 oturum'],
            ['key' => 'mentors', 'title' => 'Mentor Marketplace', 'desc' => 'Review paketleri, mentor slotları ve gelir takibi.', 'admin_route' => 'admin.mentors', 'panel_route' => 'panel.mentors', 'metric' => '₺18.4K GMV'],
            ['key' => 'learning', 'title' => 'Eğitim & Affiliate', 'desc' => 'Kurs önerileri, partner linkleri ve dönüşüm.', 'admin_route' => 'admin.learning', 'panel_route' => 'panel.learning', 'metric' => '24 kaynak'],
            ['key' => 'settings', 'title' => 'Ürün Ayarları', 'desc' => 'Demo mod, FastAPI durumu, panel yayın kontrolleri.', 'admin_route' => 'admin.settings', 'panel_route' => 'panel.account', 'metric' => 'Demo açık'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function pages(): array
    {
        return [
            'students' => [
                'title' => 'Öğrenciler',
                'subtitle' => 'Öğrenci paneliyle birebir bağlı profil, hedef rol ve risk takibi.',
                'panel_route' => 'panel.dashboard',
                'actions' => ['Riskli öğrencileri filtrele', 'Panel durumunu görüntüle', 'Cohort’a taşı'],
                'rows' => [
                    ['name' => 'Ayşe Yılmaz', 'meta' => 'YZTA Grup 92 · Junior Veri Analisti', 'score' => '%72', 'status' => 'Başvuruya hazır', 'next' => 'Yetenek pasaportunu onayla'],
                    ['name' => 'Mehmet Kaya', 'meta' => 'Frontend Career Track · Frontend Developer', 'score' => '%58', 'status' => 'Riskli', 'next' => 'GitHub proje kanıtı iste'],
                    ['name' => 'Derya Şahin', 'meta' => 'Data Analytics · BI Analyst', 'score' => '%81', 'status' => 'İşveren havuzuna hazır', 'next' => 'İlan eşleştirmeye gönder'],
                ],
            ],
            'cohorts' => [
                'title' => 'Cohortlar',
                'subtitle' => 'Bootcamp ve üniversite sınıfları için readiness, gap ve placement görünümü.',
                'panel_route' => 'panel.roadmap',
                'actions' => ['Yeni cohort aç', 'Haftalık rapor indir', 'Mentor ataması yap'],
                'rows' => [
                    ['name' => 'YZTA Grup 92', 'meta' => '42 öğrenci · Data + Web karışık', 'score' => '%68', 'status' => 'Orta risk', 'next' => 'Portfolio sprinti'],
                    ['name' => 'Data Analytics Bootcamp', 'meta' => '96 öğrenci · 5 mentor', 'score' => '%72', 'status' => 'İyi', 'next' => 'İşveren demo günü'],
                    ['name' => 'Frontend Career Track', 'meta' => '64 öğrenci · 3 mentor', 'score' => '%58', 'status' => 'Yüksek risk', 'next' => 'CV + GitHub audit'],
                ],
            ],
            'readiness' => [
                'title' => 'Readiness Analitiği',
                'subtitle' => 'Kariyer merdiveni ve skill radar çıktılarını admin karar ekranına taşır.',
                'panel_route' => 'panel.career-ladder',
                'actions' => ['Rol eşiği düzenle', 'Gap dağılımı export et', 'A/B/C segmenti gönder'],
                'rows' => [
                    ['name' => 'Junior Veri Analisti', 'meta' => '119 aday · A segment', 'score' => '%78', 'status' => 'İşe hazır', 'next' => 'İşveren havuzuna aç'],
                    ['name' => 'BI Analisti', 'meta' => '184 aday · B segment', 'score' => '%61', 'status' => 'Yakın', 'next' => 'Power BI planı ata'],
                    ['name' => 'ML Engineer', 'meta' => '51 aday · C segment', 'score' => '%34', 'status' => 'Uzun vade', 'next' => 'Temel ML roadmap'],
                ],
            ],
            'skill-passport' => [
                'title' => 'Yetenek Pasaportu Onay Kuyruğu',
                'subtitle' => 'Öğrencilerin GitHub, sertifika, demo ve sunum kanıtlarını doğrula.',
                'panel_route' => 'panel.skill-passport',
                'actions' => ['Kanıtı onayla', 'Revizyon iste', 'İşveren görünürlüğünü aç'],
                'rows' => [
                    ['name' => 'SQL kanıtı', 'meta' => 'Ayşe · GitHub sales analysis', 'score' => '85/100', 'status' => 'Onay bekliyor', 'next' => 'Notebook linkini kontrol et'],
                    ['name' => 'Power BI dashboard', 'meta' => 'Mehmet · Portfolio', 'score' => '62/100', 'status' => 'Revizyon', 'next' => 'Canlı demo linki iste'],
                    ['name' => 'İngilizce sunum', 'meta' => 'Derya · Video', 'score' => '74/100', 'status' => 'Onaylanabilir', 'next' => 'Skill passport’a ekle'],
                ],
            ],
            'job-radar' => [
                'title' => 'İş Radarı Yönetimi',
                'subtitle' => 'Öğrenci panelindeki ilan sinyallerini, maaş aralığını ve gap uyarılarını yönet.',
                'panel_route' => 'panel.job-radar',
                'actions' => ['İlan kaynağı ekle', 'Maaş bandı düzenle', 'Hazır adaylara bildir'],
                'rows' => [
                    ['name' => 'Trendyol · Junior Data Analyst', 'meta' => 'LinkedIn · İstanbul', 'score' => '%84', 'status' => 'Yüksek uyum', 'next' => 'A segment adaylara göster'],
                    ['name' => 'Logo · BI Analyst', 'meta' => 'Kariyer.net · Hibrit', 'score' => '%67', 'status' => 'Hazırlık gerek', 'next' => 'Power BI gap uyarısı'],
                    ['name' => 'Remote EU · Product Analyst', 'meta' => 'Remote · EUR', 'score' => '%58', 'status' => 'Uzun vade', 'next' => 'İngilizce pitch görevi'],
                ],
            ],
            'applications' => [
                'title' => 'Başvuru Funnelı',
                'subtitle' => 'Öğrencinin başvuru CRM’ini admin tarafında funnel ve aksiyon listesine çevir.',
                'panel_route' => 'panel.applications',
                'actions' => ['Takip maili öner', 'Mülakat hazırlığı ata', 'Teklif raporu çıkar'],
                'rows' => [
                    ['name' => 'Kaydedildi', 'meta' => '428 ilan', 'score' => '%33', 'status' => 'Üst funnel', 'next' => 'Uygunluk analizini tamamla'],
                    ['name' => 'Başvuruldu', 'meta' => '216 başvuru', 'score' => '%17', 'status' => 'Aktif', 'next' => 'Takip maili hatırlat'],
                    ['name' => 'Mülakat', 'meta' => '86 oturum', 'score' => '%7', 'status' => 'Kritik', 'next' => 'Simülasyon ata'],
                ],
            ],
            'interviews' => [
                'title' => 'Mülakat Simülasyonu Yönetimi',
                'subtitle' => 'Rol bazlı soru bankası, rubric ve öğrenci skor trendlerini yönet.',
                'panel_route' => 'panel.interview',
                'actions' => ['Soru ekle', 'Rubric güncelle', 'Düşük skorluya görev ata'],
                'rows' => [
                    ['name' => 'SQL case', 'meta' => 'Junior Veri Analisti · Teknik', 'score' => '82 ort.', 'status' => 'Güçlü', 'next' => 'Zor seviye ekle'],
                    ['name' => 'STAR davranışsal', 'meta' => 'Tüm roller · HR', 'score' => '64 ort.', 'status' => 'Gelişmeli', 'next' => 'Örnek cevap göster'],
                    ['name' => 'Product metrics case', 'meta' => 'Product Analyst · Vaka', 'score' => '58 ort.', 'status' => 'Risk', 'next' => 'Funnel dersi öner'],
                ],
            ],
            'mentors' => [
                'title' => 'Mentor Marketplace',
                'subtitle' => 'Mentor paketleri, slotlar, review talepleri ve gelir görünümü.',
                'panel_route' => 'panel.mentors',
                'actions' => ['Mentor slotu aç', 'Paket fiyatı düzenle', 'Review eşleştir'],
                'rows' => [
                    ['name' => 'CV hızlı kontrol', 'meta' => '₺299 · 24 saat', 'score' => '42 satış', 'status' => 'Aktif', 'next' => '2 mentor daha ekle'],
                    ['name' => 'Portfolio review', 'meta' => '₺499 · 48 saat', 'score' => '18 satış', 'status' => 'Büyüyor', 'next' => 'BI uzmanı ata'],
                    ['name' => 'Mülakat provası', 'meta' => '₺699 · canlı 45 dk', 'score' => '9 satış', 'status' => 'Premium', 'next' => 'Takvim entegrasyonu'],
                ],
            ],
            'learning' => [
                'title' => 'Eğitim & Affiliate Yönetimi',
                'subtitle' => 'Gap analizinden çıkan kaynak önerilerini, sertifika ve partner gelirini yönet.',
                'panel_route' => 'panel.learning',
                'actions' => ['Kaynak ekle', 'Affiliate etiketi ata', 'Gap’e bağla'],
                'rows' => [
                    ['name' => 'Google Data Analytics', 'meta' => 'Coursera · Sertifikalı', 'score' => '%11 dönüşüm', 'status' => 'Partner adayı', 'next' => 'Affiliate link ekle'],
                    ['name' => 'SQL freeCodeCamp', 'meta' => 'Ücretsiz · YouTube', 'score' => '%38 tamamlama', 'status' => 'Güçlü', 'next' => 'SQL gap’e sabitle'],
                    ['name' => 'Power BI mini proje', 'meta' => 'Udemy · Sertifikalı', 'score' => '%8 dönüşüm', 'status' => 'Test', 'next' => 'BI segmentine göster'],
                ],
            ],
            'settings' => [
                'title' => 'Ürün Ayarları',
                'subtitle' => 'Panel yayını, demo mod, FastAPI health ve admin görünürlük ayarları.',
                'panel_route' => 'panel.account',
                'actions' => ['Demo modu kapat/aç', 'FastAPI health kontrol et', 'Panel menü görünürlüğü ayarla'],
                'rows' => [
                    ['name' => 'FastAPI CV analizi', 'meta' => 'CvUploadController proxy bağlı', 'score' => 'Aktif', 'status' => 'Bağlı', 'next' => 'Health monitor ekle'],
                    ['name' => 'Öğrenci demo sayfaları', 'meta' => 'Job Radar, Passport, Interview, Mentor', 'score' => 'Yayında', 'status' => 'Aktif', 'next' => 'Admin yetki ekle'],
                    ['name' => 'Admin panel', 'meta' => 'Auth’suz demo', 'score' => 'Sprint 2', 'status' => 'Demo', 'next' => 'Breeze/admin guard bağla'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{route:string,label:string,icon:string}>
     */
    public static function nav(): array
    {
        return [
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['route' => 'admin.students', 'label' => 'Öğrenciler', 'icon' => 'profile'],
            ['route' => 'admin.cohorts', 'label' => 'Cohortlar', 'icon' => 'cohorts'],
            ['route' => 'admin.readiness', 'label' => 'Readiness', 'icon' => 'ladder'],
            ['route' => 'admin.skill-passport', 'label' => 'Yetenek Pasaportu', 'icon' => 'passport'],
            ['route' => 'admin.job-radar', 'label' => 'İş Radarı', 'icon' => 'radar'],
            ['route' => 'admin.applications', 'label' => 'Başvurular', 'icon' => 'applications'],
            ['route' => 'admin.interviews', 'label' => 'Mülakatlar', 'icon' => 'interview'],
            ['route' => 'admin.mentors', 'label' => 'Mentorlar', 'icon' => 'mentors'],
            ['route' => 'admin.learning', 'label' => 'Eğitimler', 'icon' => 'learning'],
            ['route' => 'admin.settings', 'label' => 'Ayarlar', 'icon' => 'settings'],
        ];
    }
}
