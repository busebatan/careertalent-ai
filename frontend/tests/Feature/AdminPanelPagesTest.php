<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelPagesTest extends TestCase
{
    public function test_dashboard_renders_real_api_counts_and_excludes_admin_from_students(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/dashboard' => Http::response([
                'stats' => [
                    ['label' => 'Aktif öğrenci', 'value' => 2, 'detail' => 'Admin hesapları hariç'],
                    ['label' => 'Mevcut CV', 'value' => 1, 'detail' => 'Aktif CV kaydı'],
                    ['label' => 'Hazır analiz', 'value' => 1, 'detail' => 'Analizi tamamlanan CV'],
                    ['label' => 'Aktif başvuru', 'value' => 0, 'detail' => 'Reddedilenler hariç'],
                ],
                'module_counts' => [
                    'students' => 2,
                    'readiness' => 1,
                    'skill-passport' => 0,
                    'job-radar' => 0,
                    'applications' => 0,
                    'interviews' => 0,
                ],
                'recent_students' => [[
                    'name' => 'Gerçek Öğrenci',
                    'email' => 'ogrenci@example.com',
                    'registered_at' => '2026-07-14T00:00:00+00:00',
                ]],
            ]),
        ]);

        $response = $this->withSession(['auth.access_token' => 'admin-token'])->get('/admin');

        $response
            ->assertOk()
            ->assertSee('Yönetim Özeti')
            ->assertSee('Aktif öğrenci')
            ->assertSee('Admin hesapları hariç')
            ->assertSee('Gerçek Öğrenci')
            ->assertSee('2 kayıt')
            ->assertDontSee('428')
            ->assertDontSee('Cohortlar')
            ->assertDontSee('Demo kararları')
            ->assertDontSee('Demo aksiyon');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://localhost:8000/api/v1/admin/dashboard'
            && $request->hasHeader('Authorization'));
    }

    #[DataProvider('realModuleProvider')]
    public function test_real_modules_render_api_rows(string $route, string $endpoint, string $title): void
    {
        Http::fake([
            "http://localhost:8000/api/v1/admin/modules/{$endpoint}" => Http::response([
                'title' => $title,
                'subtitle' => 'Yalnız gerçek backend kayıtları.',
                'total' => 1,
                'rows' => [[
                    'name' => 'Gerçek kayıt',
                    'meta' => 'Gerçek öğrenci',
                    'score' => '1 ölçüm',
                    'status' => 'ready',
                    'next' => 'Gerçek kayıt detayı',
                ]],
            ]),
        ]);

        $response = $this->withSession(['auth.access_token' => 'admin-token'])->get($route);

        $response
            ->assertOk()
            ->assertSee($title)
            ->assertSee('1 kayıt')
            ->assertSee('Gerçek kayıt')
            ->assertSee('Gerçek öğrenci')
            ->assertSee('Gerçek kayıt detayı')
            ->assertDontSee('Demo aksiyon')
            ->assertDontSee('Sprint 3');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function realModuleProvider(): array
    {
        return [
            'students' => ['/admin/ogrenciler', 'students', 'Öğrenciler'],
            'readiness' => ['/admin/readiness', 'readiness', 'Readiness Analizi'],
            'skill passport' => ['/admin/yetenek-pasaportu', 'skill-passport', 'Yetenek Pasaportu'],
            'job radar' => ['/admin/is-radari', 'job-radar', 'İş Radarı'],
            'applications' => ['/admin/basvurular', 'applications', 'Başvurular'],
            'interviews' => ['/admin/mulakatlar', 'interviews', 'Mülakatlar'],
        ];
    }

    public function test_admin_locale_switch_route(): void
    {
        $this->withSession(['auth.access_token' => 'admin-token', 'panel_locale' => 'tr'])
            ->get('/admin/locale/en')
            ->assertRedirect()
            ->assertSessionHas('panel_locale', 'en');
    }

    public function test_admin_dashboard_renders_english_shell(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/dashboard' => Http::response([
                'stats' => [],
                'module_counts' => [],
                'recent_students' => [],
            ]),
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
        ]);

        $response = $this->withSession(['auth.access_token' => 'admin-token', 'panel_locale' => 'en'])->get('/admin');

        $response
            ->assertOk()
            ->assertSee('Admin Overview', false)
            ->assertSee('Student panel', false)
            ->assertSee('Notifications', false)
            ->assertSee('data-lucide="bell"', false)
            ->assertSee('/admin/locale/tr', false);
    }

    public function test_dashboard_shows_error_instead_of_demo_fallback_when_api_is_unavailable(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/dashboard' => Http::response(['detail' => 'unavailable'], 503),
        ]);

        $response = $this->withSession(['auth.access_token' => 'admin-token'])->get('/admin');

        $response
            ->assertOk()
            ->assertSee('Yönetim verisi alınamadı: unavailable')
            ->assertDontSee('428')
            ->assertDontSee('Cohort sağlığı')
            ->assertDontSee('Ayşe Yılmaz');
    }

    #[DataProvider('unsupportedModuleRouteProvider')]
    public function test_modules_without_real_sources_are_not_routable(string $route): void
    {
        $this->get($route)->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedModuleRouteProvider(): array
    {
        return [
            'cohorts' => ['/admin/cohortlar'],
            'mentors' => ['/admin/mentorlar'],
            'learning' => ['/admin/egitimler'],
            'settings' => ['/admin/ayarlar'],
        ];
    }
}
