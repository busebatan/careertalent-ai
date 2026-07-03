<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelPagesI18nTest extends TestCase
{
  /**
   * @return array<string, array{string, string, list<string>}>
   */
  public static function panelPagesProvider(): array
  {
    return [
      'dashboard-tr' => ['/panel', 'tr', ['Gösterge Paneli', 'Hoş geldin', 'Henüz CV analizi yok', 'Bu haftanın görevleri', 'Tümünü gör']],
      'dashboard-en' => ['/panel', 'en', ['Dashboard', 'Welcome', 'No CV analysis yet', 'Learning resources', 'View all']],
      'roadmap-tr' => ['/panel/yol-haritasi', 'tr', ['Yol Haritası', 'Gap analizine göre', 'Görevlerime git', 'SQL modülü 1']],
      'roadmap-en' => ['/panel/yol-haritasi', 'en', ['Roadmap', 'gap analysis', 'Go to my tasks', 'SQL modülü 1']],
      'learning-tr' => ['/panel/egitim-onerileri', 'tr', ['Eğitim Önerileri', 'Gap analizine göre', 'Google Data Analytics', 'Tümü']],
      'learning-en' => ['/panel/egitim-onerileri', 'en', ['Learning Resources', 'gap analysis', 'Google Data Analytics', 'All']],
      'tasks-tr' => ['/panel/gorevlerim', 'tr', ['Görevlerim', 'Not ekle', 'Görev ekle', 'SQL modülü 1', 'kişisel not']],
      'tasks-en' => ['/panel/gorevlerim', 'en', ['Tasks', 'Add note', 'Add task', 'SQL modülü 1', 'personal notes']],
      'chat-tr' => ['/panel/sohbet', 'tr', ['Sohbet', 'Sohbet yakında', 'Kariyer asistanın']],
      'chat-en' => ['/panel/sohbet', 'en', ['Chat', 'Chat coming soon', 'career assistant']],
      'career-ladder-tr' => ['/panel/kariyer-merdiveni', 'tr', ['Kariyer merdiveni', 'Junior Veri Analisti', 'SWOT göster', 'BI Analisti']],
      'career-ladder-en' => ['/panel/kariyer-merdiveni', 'en', ['Career ladder', 'Junior Veri Analisti', 'Show SWOT', 'BI Analisti']],
      'profile-tr' => ['/panel/profil', 'tr', ['Profil', 'Profil bilgileri', 'Giriş bilgileri', 'Şifre değiştir', 'AI ile düzenle', 'CV yükle']],
      'profile-en' => ['/panel/profil', 'en', ['Profile', 'Profile details', 'Login settings', 'Change password', 'Edit with AI', 'Upload CV']],
      'cv-builder-tr' => ['/panel/cv-olustur', 'tr', ['CV Oluştur', 'PDF indirildi', 'PDF indir', 'Kaydet', 'animate-spin', 'İstanbul Üniversitesi', 'İsteğe bağlı bölüm ekle', 'CvOptionalSections', 'data-optional-section']],
      'cv-builder-en' => ['/panel/cv-olustur', 'en', ['Build CV', 'PDF downloaded', 'Download PDF', 'Save', 'animate-spin', 'Istanbul University', 'Add optional section', 'CvOptionalSections', 'data-optional-section']],
    ];
  }

  #[DataProvider('panelPagesProvider')]
  public function test_panel_sayfasi_locale_ile_acilir(string $path, string $locale, array $mustSee): void
  {
    $response = $this->withSession(['panel_locale' => $locale])->get($path);

    $response->assertStatus(200);
    $response->assertSee('CareerTalent AI', false);

    foreach ($mustSee as $text) {
      $response->assertSee($text, false);
    }
  }

  public function test_cv_builder_bilingual_draft_json(): void
  {
    $response = $this->get('/panel/cv-olustur');

    $response->assertStatus(200);
    $response->assertSee('Istanbul University', false);
    $response->assertSee('İstanbul Üniversitesi', false);
    $response->assertSee('exportHarvardCvPdf', false);
    $response->assertSee('editLang', false);
    $response->assertSee('enabledOptional', false);
    $response->assertSee('CvOptionalSections', false);
    $response->assertSee('enableOptionalSectionForBothLocales', false);
    $response->assertSee('_skipLocalesSync', false);
  }

  public function test_locale_switch_tr_to_en(): void
  {
    $this->withSession(['panel_locale' => 'tr'])
      ->get('/panel/locale/en')
      ->assertRedirect()
      ->assertSessionHas('panel_locale', 'en');

    $this->withSession(['panel_locale' => 'en'])
      ->get('/panel')
      ->assertSee('Welcome');
  }
}
