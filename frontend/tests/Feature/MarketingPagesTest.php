<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
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
        $response->assertDontSee('Panele Git');
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
        $this->get('/faq')->assertStatus(200)->assertSee('Sıkça Sorulan Sorular');
    }

    public function test_iletisim_sayfasi_acilir(): void
    {
        $this->get('/iletisim')->assertStatus(200)->assertSee('İletişim');
    }

    public function test_giris_sayfasi_acilir(): void
    {
        $this->get('/giris')
            ->assertStatus(200)
            ->assertSee('Giriş Yap')
            ->assertSee('Demo paneline git');
    }

    public function test_kayit_sayfasi_acilir(): void
    {
        $this->get('/kayit')
            ->assertStatus(200)
            ->assertSee('Kayıt Ol')
            ->assertSee('Hesap Oluştur');
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
        $response->assertSee('Gösterge Paneli');
        $response->assertSee('Hoş geldin');
        $response->assertSee('Henüz CV analizi yok');
        $response->assertDontSee('CV ve profil');
        $response->assertSee('Eğitim önerileri');
        $response->assertSee('Bu haftanın görevleri');
        $response->assertSee('Google Data Analytics');
        $response->assertSee('CV oluştur');
        $response->assertSee('API bağlı', false);
    }

    public function test_panel_kariyer_merdiveni_sayfasi_acilir(): void
    {
        $response = $this->get('/panel/kariyer-merdiveni');

        $response->assertStatus(200);
        $response->assertSee('Kariyer merdiveni');
        $response->assertSee('Junior Veri Analisti');
        $response->assertSee('A — Hazır');
        $response->assertSee('BI Analisti');
        $response->assertSee('SWOT göster');
    }

    public function test_panel_ingilizce_locale(): void
    {
        $response = $this->withSession(['panel_locale' => 'en'])->get('/panel');

        $response->assertStatus(200);
        $response->assertSee('Welcome');
        $response->assertSee('Skill radar analysis');
        $response->assertSee('Learning resources');
        $response->assertSee('Build CV');
        $response->assertSee('API connected', false);
    }

    public function test_panel_kariyer_merdiveni_ingilizce(): void
    {
        $response = $this->withSession(['panel_locale' => 'en'])->get('/panel/kariyer-merdiveni');

        $response->assertStatus(200);
        $response->assertSee('Career ladder');
        $response->assertSee('Show SWOT');
    }

    public function test_panel_locale_switch_route(): void
    {
        $this->get('/panel/locale/en')
            ->assertRedirect()
            ->assertSessionHas('panel_locale', 'en');
    }

    public function test_panel_profil_sayfasi_acilir(): void
    {
        $response = $this->get('/panel/profil');

        $response->assertStatus(200);
        $response->assertSee('Profil bilgileri');
        $response->assertSee('Giriş bilgileri');
        $response->assertSee('CV yükle');
        $response->assertSee('Şifre değiştir');
        $response->assertSee('AI ile düzenle');
    }

    public function test_panel_cv_olustur_sayfasi_acilir(): void
    {
        $response = $this->get('/panel/cv-olustur');

        $response->assertStatus(200);
        $response->assertSee('CV Oluştur');
        $response->assertSee('CV içerik dili');
        $response->assertSee('PDF hangi dilde indirilsin?');
        $response->assertSee('İstanbul Üniversitesi');
        $response->assertSee('Istanbul University');
        $response->assertSee('Ayşe Yılmaz');
    }

    public function test_panel_ilan_eslestirme_sayfasi_acilir(): void
    {
        $response = $this->get('/panel/ilan-eslestirme');

        $response->assertStatus(200);
        $response->assertSee('İlan Eşleştirme');
        $response->assertSee('İlan linki ekle');
        $response->assertSee('Junior Veri Analisti');
        $response->assertSee('Data Analyst (Remote)');
        $response->assertSee('Analiz et');
    }

    public function test_panel_ilan_eslestirme_analiz_endpoint(): void
    {
        $response = $this->postJson('/panel/ilan-eslestirme/analiz', [
            'url' => 'https://www.kariyer.net/is-ilani/bi-analisti-perakende',
        ]);

        $response->assertOk();
        $response->assertJsonPath('job.title', 'BI Analisti');
        $response->assertJsonPath('job.source', 'kariyer.net');
    }

    public function test_panel_ilan_eslestirme_ingilizce(): void
    {
        $response = $this->withSession(['panel_locale' => 'en'])->get('/panel/ilan-eslestirme');

        $response->assertStatus(200);
        $response->assertSee('Job Matching');
        $response->assertSee('Analyze');
    }
}
