<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelPagesTest extends TestCase
{
    private const BYPASS_MIDDLEWARE = [
        \App\Http\Middleware\EnsureApiAuthenticated::class,
        \App\Http\Middleware\EnsureApiAdmin::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(self::BYPASS_MIDDLEWARE);
    }

    private function superAdminSession(): array
    {
        return [
            'auth.access_token' => 'admin-token',
            'auth.user' => [
                'id' => 27,
                'full_name' => 'Süper Yönetici',
                'email' => 'root@example.com',
                'is_admin' => true,
                'is_active' => true,
                'role' => 'super_admin',
                'admin_permissions' => [],
                'must_change_password' => false,
            ],
        ];
    }

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
            ->assertDontSee('Yönetim alanı', false)
            ->assertDontSee('Öğrenci panelinden ayrı yönetim yüzeyi', false)
            ->assertSee('admin-sidebar', false)
            ->assertSee('toggleTheme()', false)
            ->assertSee('/admin/ogrenciler', false)
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
        Http::fake([
            'http://localhost:8000/api/v1/auth/me/locale' => Http::response(['preferred_locale' => 'en']),
        ]);

        $this->withSession([...$this->superAdminSession(), 'panel_locale' => 'tr'])
            ->get('/admin/locale/en')
            ->assertRedirect()
            ->assertSessionHas('panel_locale', 'en');

        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'http://localhost:8000/api/v1/auth/me/locale'
            && $request['preferred_locale'] === 'en'
        );
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

    public function test_sidebar_is_grouped_and_ends_with_profile_then_logout(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/dashboard' => Http::response(['stats' => [], 'module_counts' => [], 'recent_students' => []]),
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
        ]);

        $response = $this->withSession($this->superAdminSession())->get('/admin');
        $response->assertOk()
            ->assertSee('GENEL')
            ->assertSee('VERİ YÖNETİMİ')
            ->assertSee('ÖĞRENCİ YÖNETİMİ')
            ->assertSee('KARİYER OPERASYONLARI')
            ->assertSee('Admin Hesapları')
            ->assertSee('data-admin-profile', false)
            ->assertSee('data-admin-logout', false)
            ->assertSee('Süper Yönetici')
            ->assertSee('root@example.com');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'data-admin-logout'), strpos($html, 'data-admin-profile'));
    }

    public function test_admin_profile_and_account_management_pages_render_real_contracts(): void
    {
        $profile = $this->superAdminSession()['auth.user'];
        Http::fake([
            'http://localhost:8000/api/v1/admin/profile' => Http::response($profile),
            'http://localhost:8000/api/v1/admin/accounts' => Http::response([
                'permission_keys' => ['dashboard.view', 'students.view'],
                'accounts' => [[...$profile, 'created_at' => '2026-07-16T00:00:00Z']],
            ]),
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
        ]);

        $this->withSession($this->superAdminSession())->get('/admin/profil')
            ->assertOk()
            ->assertSee('Admin Profili')
            ->assertSee('action="'.route('admin.profile.update').'"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="new_password"', false);

        $this->withSession($this->superAdminSession())->get('/admin/hesaplar')
            ->assertOk()
            ->assertSee('Yeni admin oluştur')
            ->assertSee('action="'.route('admin.accounts.store').'"', false)
            ->assertSee('name="permissions[]"', false)
            ->assertSee('Öğrencileri görüntüle');
    }

    public function test_profile_and_admin_account_forms_forward_validated_payloads(): void
    {
        $profile = $this->superAdminSession()['auth.user'];
        Http::fake([
            'http://localhost:8000/api/v1/admin/profile' => Http::response([...$profile, 'full_name' => 'Yeni Yönetici']),
            'http://localhost:8000/api/v1/admin/accounts' => Http::response([...$profile, 'id' => 31, 'role' => 'admin', 'must_change_password' => true], 201),
            'http://localhost:8000/api/v1/admin/accounts/31' => Http::response([...$profile, 'id' => 31, 'role' => 'admin', 'is_active' => false]),
        ]);

        $this->withSession($this->superAdminSession())->patch('/admin/profil', [
            'full_name' => 'Yeni Yönetici',
            'email' => 'root@example.com',
            'current_password' => 'MevcutParola123!',
            'new_password' => 'YeniParola123!',
            'new_password_confirmation' => 'YeniParola123!',
        ])->assertRedirect('/admin/login');

        $this->withSession($this->superAdminSession())->post('/admin/hesaplar', [
            'full_name' => 'Operasyon Admini',
            'email' => 'ops@example.com',
            'temporary_password' => 'GeciciParola123!',
            'temporary_password_confirmation' => 'GeciciParola123!',
            'permissions' => ['dashboard.view', 'students.view'],
        ])->assertRedirect('/admin/hesaplar');

        $this->withSession($this->superAdminSession())->patch('/admin/hesaplar/31', [
            'full_name' => 'Operasyon Admini',
            'email' => 'ops@example.com',
            'is_active' => '0',
            'temporary_password' => '',
            'permissions' => ['dashboard.view', 'applications.view'],
        ])->assertRedirect('/admin/hesaplar');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && $request->url() === 'http://localhost:8000/api/v1/admin/profile'
            && $request['new_password'] === 'YeniParola123!');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/admin/accounts'
            && $request['permissions'] === ['dashboard.view', 'students.view']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && $request->url() === 'http://localhost:8000/api/v1/admin/accounts/31'
            && $request['is_active'] === false);
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
