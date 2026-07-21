<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelPagesI18nTest extends TestCase
{

  protected function setUp(): void
  {
    parent::setUp();

    Http::fake([
      'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
      'http://localhost:8000/api/v1/auth/me' => Http::response(['preferred_locale' => 'en'], 200),
      'http://localhost:8000/api/v1/public/positions?limit=100&offset=0' => Http::response([
        'items' => [[
          'organization' => ['name' => 'ACME Teknoloji', 'slug' => 'acme', 'website' => null, 'logo_url' => null],
          'position' => [
            'id' => 'position-1', 'public_id' => 'ABC123', 'public_path' => '/apply/acme/backend-developer-ABC123',
            'title' => 'Backend Developer', 'department' => 'Engineering', 'level' => 'mid',
            'employment_type' => 'full_time', 'workplace_type' => 'remote', 'location' => 'İstanbul',
            'description' => 'Backend servislerini geliştir.', 'responsibilities' => 'API geliştirme',
            'must_have_skills' => ['Laravel', 'SQL'], 'preferred_skills' => ['Redis'],
            'application_deadline' => '2026-08-31T23:59:59Z', 'status' => 'published',
            'application_open' => true, 'estimated_application_minutes' => 8,
            'estimated_assessment_minutes' => null,
          ],
          'source' => null,
        ]],
        'total' => 1, 'limit' => 100, 'offset' => 0, 'has_more' => false,
      ], 200),
      'http://localhost:8000/*' => Http::response([], 200),
    ]);
  }
  /**
   * @return array<string, array{string, string, list<string>}>
   */
  public static function panelPagesProvider(): array
  {
    return [
      'dashboard-tr' => ['/panel', 'tr', ['Ana Sayfa', 'Hoş geldin', 'CV yükle', 'CV oluştur', 'Henüz CV analizi yok']],
      'dashboard-en' => ['/panel', 'en', ['Dashboard', 'Welcome', 'Upload CV', 'Build CV', 'No CV analysis yet']],
      'account-tr' => ['/panel/hesap', 'tr', ['Hesap', 'Profil bilgileri', 'LinkedIn', 'Profil ve portfolyo bağlantıları', 'Bağlantı ekle', 'Giriş bilgileri', 'CV geçmişi', 'Abonelik', 'Gizlilik']],
      'account-en' => ['/panel/hesap', 'en', ['Account', 'Profile details', 'LinkedIn', 'Profile and portfolio links', 'Add link', 'CV history']],
      'skill-passport-tr' => ['/panel/yetenek-pasaportu', 'tr', ['Yetenek Pasaportu', 'Kanıt skoru', 'Kanıt yüklemek için bir yeteneğe tıkla', 'Onaylandı', 'İnceleniyor', 'Bekleniyor', 'Kanıt eksik']],
      'skill-passport-en' => ['/panel/yetenek-pasaportu', 'en', ['Skill Passport', 'Evidence score', 'Click a skill to open the upload area below the card.', 'Approved', 'Under review', 'Awaiting evidence', 'Missing evidence']],
      'cv-builder-tr' => ['/panel/cv-merkezi', 'tr', ['CV Merkezi', 'PDF indir', 'Kaydet', 'CvOptionalSections']],
      'cv-builder-en' => ['/panel/cv-merkezi', 'en', ['CV Center', 'Download PDF', 'Save', 'CvOptionalSections']],
      'roadmap-tr' => ['/panel/kariyer-rotam', 'tr', ['Kariyer Rotam', 'Kariyer merdiveni', 'Eğitim Önerileri']],
      'roadmap-en' => ['/panel/kariyer-rotam', 'en', ['Career Route', 'Career ladder', 'Learning Resources']],
      'tasks-tr' => ['/panel/kariyer-rotam/gorevler', 'tr', ['Görevlerim', 'Görev ekle', 'Kişisel not']],
      'tasks-en' => ['/panel/kariyer-rotam/gorevler', 'en', ['My Tasks', 'Add task', 'Personal note']],
      'job-listings-tr' => ['/panel/is-ilanlari', 'tr', ['İş İlanları', 'Demo ilan', 'Junior Veri Analisti']],
      'job-listings-en' => ['/panel/is-ilanlari', 'en', ['Job Listings', 'Demo listing', 'Junior Data Analyst']],
      'job-analysis-tr' => ['/panel/ilan-analizi', 'tr', ['İlan Analizi', 'Analiz et']],
      'job-analysis-en' => ['/panel/ilan-analizi', 'en', ['Job Analysis', 'Analyze']],
      'applications-tr' => ['/panel/basvurularim', 'tr', ['Başvurularım', 'Aktif başvuru']],
      'applications-en' => ['/panel/basvurularim', 'en', ['Applications', 'Active applications']],
      'interview-tr' => ['/panel/mulakat-hazirligi', 'tr', ['Mülakat Hazırlığı', 'AI mülakatı başlat', 'Önceki soru', 'Mülakat geçmişi']],
      'interview-en' => ['/panel/mulakat-hazirligi', 'en', ['Interview Preparation', 'Start AI interview', 'Previous question', 'Interview history']],
      'chat-tr' => ['/panel/ai-yardimcisi', 'tr', ['Kariyer Asistanı', 'kişisel AI kariyer desteği']],
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

  public function test_interview_page_hides_questions_until_an_active_session_exists_and_lists_history(): void
  {
    $response = $this->withSession(['panel_locale' => 'tr'])->view('app.interview', [
      'interview' => null,
      'interviewError' => null,
      'interviewHistory' => [[
        'id' => 'history-1', 'target_role' => 'Veri Analisti', 'status' => 'completed',
        'question_count' => 3, 'answered_count' => 3, 'average_score' => 84,
      ]],
    ]);

    $response
      ->assertSee('initial: null', false)
      ->assertSee('data-interview-active', false)
      ->assertSee('x-show="!interview"', false)
      ->assertSee('data-interview-progress', false)
      ->assertSee('data-interview-previous', false)
      ->assertSee('data-interview-next', false)
      ->assertSee('data-interview-history', false)
      ->assertSee('data-interview-retry', false)
      ->assertSee('Veri Analisti', false);
  }

  public function test_interview_page_does_not_resume_questions_before_new_interview_click(): void
  {
    $this->get(route('panel.interview'))
      ->assertOk()
      ->assertSee('initial: null', false);

    Http::assertNotSent(fn ($request): bool => $request->url() === 'http://localhost:8000/api/v1/career/interviews/current');
  }

  public function test_cv_builder_bilingual_draft_json(): void
  {
    $response = $this->get('/panel/cv-merkezi');

    $response->assertStatus(200);
    $response->assertSee('Istanbul University', false);
    $response->assertSee('İstanbul Üniversitesi', false);
    $response->assertSee('Ayşe Yılmaz', false);
    $response->assertSee('renderHarvardCvPdf', false);
    $response->assertSee('downloadPdfBlob', false);
    $response->assertSee('editLang', false);
    $response->assertSee('enabledOptional', false);
    $response->assertSee('CvOptionalSections', false);
    $response->assertSee('enableOptionalSectionForBothLocales', false);
    $response->assertSee('_skipLocalesSync', false);
  }

  public function test_chat_owns_message_scroll_and_exposes_history_and_approved_cv_action_contract(): void
  {
    $response = $this->withSession(['panel_locale' => 'tr'])->get('/panel/ai-yardimcisi');

    $response->assertOk()
      ->assertDontSee('md:flex md:min-h-0 md:flex-col md:overflow-hidden', false)
      ->assertSee('data-chat-page', false)
      ->assertSee('data-chat-panel', false)
      ->assertSee('h-[calc(100dvh-15rem)]', false)
      ->assertSee('sm:h-[calc(100dvh-13rem)]', false)
      ->assertSee('data-chat-messages', false)
      ->assertSee('min-h-0 flex-1 space-y-3 overflow-y-auto', false)
      ->assertSee('data-chat-history', false)
      ->assertSee('data-chat-history-modal', false)
      ->assertSee('@click="startNewChat()"', false)
      ->assertSee('data-chat-cv-action', false)
      ->assertSee('jobStatusUrl', false)
      ->assertSee('createCvVersionUrl', false)
      ->assertSee('historyDetailUrl', false)
      ->assertSee('maxlength="30000"', false)
      ->assertSee('Onayla ve yeni CV sürümü oluştur');
  }

  public function test_locale_switch_tr_to_en(): void
  {
    $this->withMiddleware();

    $this->withSession(['auth.access_token' => 'token', 'panel_locale' => 'tr'])
      ->get('/panel/locale/en')
      ->assertRedirect()
      ->assertSessionHas('panel_locale', 'en');

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
      && $request->url() === 'http://localhost:8000/api/v1/auth/me/locale'
      && $request['preferred_locale'] === 'en');

    $this->withSession(['auth.access_token' => 'token', 'panel_locale' => 'en'])
      ->get('/panel')
      ->assertSee('Welcome');
  }

  public function test_training_suggestions_do_not_expose_price_distinctions(): void
  {
    $response = $this->withSession(['panel_locale' => 'tr'])->view(
      'app.partials.panel-learning-resources',
      [
        'mode' => 'full',
        'learningResources' => [[
          'id' => 'course-1',
          'title' => 'SQL Course',
          'provider' => 'Example Academy',
          'url' => 'https://example.com/sql',
          'price_type' => 'paid',
          'price_label' => '999 ₺',
          'price_range' => '500-2000',
          'has_certificate' => true,
          'skills' => ['SQL'],
        ]],
      ],
    );

    $response->assertSee('SQL Course', false);
    $response->assertSee('Example Academy', false);
    foreach (['Ücretsiz', 'Ücretli', 'Tüm fiyatlar', '999 ₺', 'price_type', 'price_label', 'price_range'] as $removedPricing) {
      $response->assertDontSee($removedPricing, false);
    }
  }

  public function test_student_sidebar_uses_consolidated_information_architecture(): void
  {
    $response = $this->withSession(['panel_locale' => 'tr'])->get('/panel');

    $response->assertOk();
    foreach (['Ana Sayfa', 'KARİYERİM', 'CV Merkezi', 'Kariyer Rotam', 'Görevlerim', 'Yetenek Pasaportu', 'FIRSATLAR', 'İş İlanları', 'İlan Analizi', 'Başvurularım', 'HAZIRLIK VE DESTEK', 'Mülakat Hazırlığı', 'Uzmanlardan Destek', 'HESAP', 'Hesap'] as $label) {
      $response->assertSee($label, false);
    }
    $response->assertSeeInOrder(['Ana Sayfa', 'Kariyer Asistanı', 'KARİYERİM', 'CV Merkezi', 'Kariyer Rotam', 'Görevlerim', 'Yetenek Pasaportu', 'FIRSATLAR', 'İş İlanları', 'İlan Analizi', 'Başvurularım', 'HAZIRLIK VE DESTEK', 'Mülakat Hazırlığı', 'Uzmanlardan Destek', 'HESAP', 'Hesap'], false);
    $response->assertDontSee('Kariyer Profilim', false);
    $this->assertStringNotContainsString('Hesap, Paket ve Gizlilik', $response->getContent());
    $this->assertStringContainsString('notifications: []', $response->getContent());
    $this->assertStringNotContainsString('notifications: "panel.notifications"', $response->getContent());
    $this->assertSame(1, substr_count($response->getContent(), 'Kariyer Asistanı'));
    foreach (['İş Radarı', 'Mentor Değerlendirme'] as $removedLabel) {
      $response->assertDontSee($removedLabel, false);
    }
  }

  public function test_job_listings_page_combines_demo_preview_with_real_application_path(): void
  {
    $response = $this->withSession(['panel_locale' => 'tr'])->get('/panel/is-ilanlari');

    $response->assertOk()
      ->assertSee('data-job-listings', false)
      ->assertSee('data-job-listing-demo', false)
      ->assertSee('Junior Veri Analisti', false)
      ->assertSee('Backend Developer', false)
      ->assertSee('backend-developer-ABC123', false)
      ->assertSee('Bu bir demo ilandır', false);
  }

  public function test_student_panel_loads_the_livewire_alpine_runtime(): void
  {
    $this->get('/panel')
      ->assertOk()
      ->assertSee('/livewire/livewire.js', false);
  }

  public function test_legacy_student_panel_urls_redirect_to_canonical_pages(): void
  {
    $redirects = [
      '/panel/profil' => '/panel/hesap',
      '/panel/kariyer-profilim' => '/panel/hesap',
      '/panel/cv-olustur' => '/panel/cv-merkezi',
      '/panel/yol-haritasi' => '/panel/kariyer-rotam',
      '/panel/ilan-eslestirme' => '/panel/ilan-analizi',
      '/panel/basvuru-takibi' => '/panel/basvurularim',
      '/panel/mulakat-simulasyonu' => '/panel/mulakat-hazirligi',
      '/panel/sohbet' => '/panel/ai-yardimcisi',
      '/panel/kariyer-profilim/yetenekler' => '/panel/yetenek-pasaportu',
    ];

    foreach ($redirects as $legacy => $canonical) {
      $this->get($legacy)->assertRedirect($canonical);
    }
  }
}
