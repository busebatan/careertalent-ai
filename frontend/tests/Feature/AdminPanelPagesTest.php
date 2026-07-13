<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelPagesTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>}> 
     */
    public static function adminPagesProvider(): array
    {
        return [
            'dashboard' => ['/admin', ['Admin Dashboard', 'Cohort sağlığı', 'Admin → Panel bağlantıları', 'Yetenek Pasaportu']],
            'students' => ['/admin/ogrenciler', ['Öğrenciler', 'Ayşe Yılmaz', 'İlgili öğrenci paneli']],
            'cohorts' => ['/admin/cohortlar', ['Cohortlar', 'YZTA Grup 92', 'Haftalık rapor indir']],
            'readiness' => ['/admin/readiness', ['Readiness Analitiği', 'Junior Veri Analisti', 'Rol eşiği düzenle']],
            'skill-passport' => ['/admin/yetenek-pasaportu', ['Yetenek Pasaportu Onay Kuyruğu', 'SQL kanıtı', 'Kanıtı onayla']],
            'job-radar' => ['/admin/is-radari', ['İş Radarı Yönetimi', 'Trendyol', 'İlan kaynağı ekle']],
            'applications' => ['/admin/basvurular', ['Başvuru Funnelı', 'Kaydedildi', 'Takip maili öner']],
            'interviews' => ['/admin/mulakatlar', ['Mülakat Simülasyonu Yönetimi', 'SQL case', 'Soru ekle']],
            'mentors' => ['/admin/mentorlar', ['Mentor Marketplace', 'CV hızlı kontrol', 'Mentor slotu aç']],
            'learning' => ['/admin/egitimler', ['Eğitim &amp; Affiliate Yönetimi', 'Google Data Analytics', 'Kaynak ekle']],
            'settings' => ['/admin/ayarlar', ['Ürün Ayarları', 'FastAPI CV analizi', 'Demo modu kapat/aç']],
        ];
    }

    #[DataProvider('adminPagesProvider')]
    public function test_admin_demo_sayfalari_acilir(string $path, array $mustSee): void
    {
        $response = $this->get($path);

        $response->assertStatus(200);
        $response->assertSee('CareerTalent Admin', false);
        $response->assertSee('/admin → /panel bağlantılı yönetim yüzeyi', false);

        foreach ($mustSee as $text) {
            $response->assertSee($text, false);
        }
    }

    public function test_admin_dashboard_panel_linklerini_gosterir(): void
    {
        $response = $this->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('/panel/ilan-analizi/radar', false);
        $response->assertSee('/panel/yetenek-pasaportu', false);
        $response->assertSee('/panel/uzmanlardan-destek', false);
    }
}
