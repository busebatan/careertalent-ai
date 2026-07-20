<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/panel/ilan-eslestirme/analyze' => Http::response([], 404),
            'http://localhost:8000/api/v1/career/jobs/analyze' => Http::response([
                    'id' => 'api-kariyer-bi-analisti',
                    'status' => 'queued',
                    'title' => 'BI Analisti',
                    'company' => 'Perakende AI',
                    'source' => 'kariyer.net',
                    'source_url' => 'https://www.kariyer.net/is-ilani/bi-analisti-perakende',
                    'match_score' => 82,
                    'matched_skills' => ['SQL'],
                    'missing_skills' => ['Power BI'],
                    'recommendation' => 'prepare',
                    'analyzed_at' => '2026-07-07T00:00:00+00:00',
            ], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);
    }
    public function test_ana_sayfa_acilir(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('CareerTalent AI');
        $response->assertSee('Gelecek kaygını');
        $response->assertSee('Anasayfa');
        $response->assertSee('Meslekler');
        $response->assertSee('Fiyatlandırma');
        $response->assertSee('Galeri');
        $response->assertSee('Bilgi');
        $response->assertSee('İletişim');
        $response->assertSee('Kayıt Ol');
        $response->assertSee('Giriş', false);
        $response->assertSee('Öncelikli eksik');
        $response->assertSee('SQL · Tableau · Python');
        $response->assertSee('Kariyer rotanı görünür kıl');
        $response->assertSee('CV’den işe uzanan tek rota');
        $response->assertSee('data-career-trajectory', false);
        $response->assertSee('data-reveal', false);
        $response->assertSee('data-lucide="arrow-right"', false);
        $response->assertSee('data-lucide="file-text"', false);
        $response->assertSee('data-lucide="menu"', false);
        $response->assertSee('İçeriğe geç');
        $response->assertDontSee('Panele Git');
    }

    public function test_marketing_tasarimi_eski_yesil_paleti_kullanir(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--marketing-green: #00c98d', $css);
        $this->assertStringNotContainsString('marketing-violet', $css);
        $this->assertStringNotContainsString('#7c6cff', $css);
        $this->assertStringNotContainsString('#a79cff', $css);
    }

    public function test_marketing_ikonlari_lucide_paketini_kullanir(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('lucide', $package['dependencies']);
    }

    public function test_meslekler_sayfasi_placeholder(): void
    {
        $this->get('/meslekler')
            ->assertStatus(200)
            ->assertSee('Meslekler')
            ->assertSee('Ana meslek')
            ->assertSee('Mevcut mesleğiniz')
            ->assertSee('Hedeflenen meslek')
            ->assertSee('İleri')
            ->assertSee('Bilgisayar Mühendisi')
            ->assertSee('Veri Analisti')
            ->assertSee('Sonuçları göster');
    }

    public function test_fiyatlandirma_sayfasi_placeholder(): void
    {
        $this->get('/fiyatlandirma')
            ->assertStatus(200)
            ->assertSee('Fiyatlandırma')
            ->assertSee('İçerik yakında eklenecek.');
    }

    public function test_galeri_sayfasi_placeholder(): void
    {
        $this->get('/galeri')
            ->assertStatus(200)
            ->assertSee('Galeri')
            ->assertSee('İçerik yakında eklenecek.');
    }

    public function test_faq_sayfasi_acilir(): void
    {
        $this->get('/faq')->assertStatus(200)->assertSee('Aklınıza takılan bir şey mi var?');
    }

    public function test_iletisim_sayfasi_acilir(): void
    {
        $this->get('/iletisim')->assertStatus(200)->assertSee('İletişim');
    }

    public function test_giris_sayfasi_panel_girisine_yonlenir(): void
    {
        $this->get('/giris')
            ->assertStatus(301)
            ->assertRedirect('/panel/login');
    }

    public function test_kayit_sayfasi_panel_kaydina_yonlenir(): void
    {
        $this->get('/kayit')
            ->assertStatus(301)
            ->assertRedirect('/panel/register');
    }

    public function test_nasil_calisir_sayfasi_acilir(): void
    {
        $this->get('/nasil-calisir')->assertStatus(200)->assertSee('Nasıl Çalışır?');
    }

    public function test_bootcamp_sayfasi_acilir(): void
    {
        $this->get('/bootcamp')->assertStatus(200)->assertSee('Bootcamp');
    }

    public function test_marketing_ingilizce_locale(): void
    {
        $response = $this->withSession(['marketing_locale' => 'en'])->get('/');

        $response->assertStatus(200);
        $response->assertSee('Sign up free');
        $response->assertSee('How it works');
        $response->assertSee('Log in', false);
        $response->assertSee('Make your career route visible');
        $response->assertSee('One route from CV to opportunity');
    }

    public function test_marketing_locale_switch_route(): void
    {
        $this->get('/locale/en')
            ->assertRedirect()
            ->assertSessionHas('marketing_locale', 'en');
    }

    public function test_ozellikler_sayfasi_acilir(): void
    {
        $this->get('/ozellikler')->assertStatus(200)->assertSee('Özellikler');
    }

    public function test_panel_ozet_sayfasi_acilir(): void
    {
        $response = $this->get('/panel');

        $response->assertStatus(200);
        $response->assertSee('Ana Sayfa');
        $response->assertSee('Hoş geldin');
        $response->assertSee('Henüz CV analizi yok');
        $response->assertDontSee('CV ve profil');
        $response->assertSee('Eğitim önerileri');
        $response->assertSee('Görevlerim');
        $response->assertDontSee('Google Data Analytics');
        $response->assertSee('CV oluştur');
        $response->assertSee('API bağlı', false);
        $response->assertSee('data-lucide="layout-dashboard"', false);
        $response->assertSee('data-lucide="bell"', false);
    }

    public function test_admin_in_student_panel_has_sidebar_return_link(): void
    {
        $response = $this->withSession([
            'auth.user' => [
                'full_name' => 'Yönetici Kullanıcı',
                'is_admin' => true,
            ],
        ])->get('/panel');

        $response->assertOk()
            ->assertSee('data-admin-return', false)
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSee('Admin Panele Dön');

        $this->withSession([
            'auth.user' => [
                'full_name' => 'Öğrenci Kullanıcı',
                'is_admin' => false,
            ],
        ])->get('/panel')
            ->assertOk()
            ->assertDontSee('data-admin-return', false);
    }

    public function test_panel_kariyer_merdiveni_sayfasi_acilir(): void
    {
        $response = $this->get('/panel/kariyer-rotam');

        $response->assertStatus(200);
        $response->assertSee('Kariyer merdiveni');
        $response->assertSee('AI kariyer merdiveni henüz hazır değil');
    }

    public function test_panel_ingilizce_locale(): void
    {
        $response = $this->withSession(['panel_locale' => 'en'])->get('/panel');

        $response->assertStatus(200);
        $response->assertSee('Welcome');
        $response->assertSee('No CV analysis yet');
        $response->assertSee('Learning resources');
        $response->assertSee('CV Center');
        $response->assertSee('API connected', false);
    }

    public function test_panel_kariyer_merdiveni_ingilizce(): void
    {
        $response = $this->withSession(['panel_locale' => 'en'])->get('/panel/kariyer-rotam');

        $response->assertStatus(200);
        $response->assertSee('Career ladder');
        $response->assertSee('AI career ladder is not ready.');
    }

    public function test_panel_locale_switch_route(): void
    {
        $this->get('/panel/locale/en')
            ->assertRedirect()
            ->assertSessionHas('panel_locale', 'en');
    }

    public function test_panel_profil_url_hesaba_yonlendirir(): void
    {
        $this->get('/panel/kariyer-profilim')
            ->assertRedirect('/panel/hesap');

        $response = $this->get('/panel/hesap');

        $response->assertStatus(200);
        $response->assertSee('Profil bilgileri');
        $response->assertSee('Giriş bilgileri');
        $response->assertSee('CV yükle');
        $response->assertSee('Şifre değiştir');
        $response->assertDontSee('AI ile düzenle');
    }

    public function test_panel_cv_olustur_sayfasi_acilir(): void
    {
        $response = $this->get('/panel/cv-merkezi');

        $response->assertStatus(200);
        $response->assertSee('CV Merkezi');
        $response->assertSee('CV içerik dili');
        $response->assertSee('PDF hangi dilde indirilsin?');
        $response->assertDontSee('İstanbul Üniversitesi');
        $response->assertDontSee('Istanbul University');
        $response->assertDontSee('Ayşe Yılmaz');
        $response->assertSee('enabledOptional', false);
    }

    public function test_panel_ilan_eslestirme_sayfasi_acilir(): void
    {
        $response = $this->get('/panel/ilan-analizi');

        $response->assertStatus(200);
        $response->assertSee('İş Fırsatları');
        $response->assertSee('İş ilanını analiz et');
        $response->assertSee('CV için öneriler');
        $response->assertSee('Analiz et');
    }

    public function test_panel_ilan_eslestirme_analiz_endpoint(): void
    {
        $response = $this->postJson('/panel/ilan-analizi/analiz', [
            'source_url' => 'https://www.kariyer.net/is-ilani/bi-analisti-perakende',
        ]);

        $response->assertOk();
        $response->assertJsonPath('title', 'BI Analisti');
        $response->assertJsonPath('source', 'kariyer.net');
    }

    public function test_panel_ilan_eslestirme_ingilizce(): void
    {
        $response = $this->withSession(['panel_locale' => 'en'])->get('/panel/ilan-analizi');

        $response->assertStatus(200);
        $response->assertSee('Job Opportunities');
        $response->assertSee('Analyze');
    }
}
