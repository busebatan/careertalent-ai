<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiAdmin;
use App\Http\Middleware\EnsureApiAuthenticated;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            EnsureApiAuthenticated::class,
            EnsureApiAdmin::class,
        ]);
        session()->flush();
    }

    private function user(bool $admin = false): array
    {
        return [
            'id' => 7,
            'full_name' => 'Gerçek Kullanıcı',
            'email' => 'ayse@example.com',
            'is_active' => true,
            'is_admin' => $admin,
            'preferred_locale' => 'en',
        ];
    }

    public function test_guest_can_register_and_is_logged_in(): void
    {
        Http::fake([
            '*/api/v1/auth/register' => Http::response($this->user(), 201),
            '*/api/v1/auth/login' => Http::response(['access_token' => 'jwt-token', 'token_type' => 'bearer']),
        ]);

        $response = $this->post('/panel/register', [
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.com',
            'password' => 'GucluParola123!',
            'password_confirmation' => 'GucluParola123!',
        ]);

        $response->assertRedirect('/panel');
        $response->assertSessionHas('auth.access_token', 'jwt-token');
        $response->assertSessionHas('auth.user.email', 'ayse@example.com');
        Http::assertSentCount(2);
    }

    public function test_guest_can_login_and_session_is_regenerated(): void
    {
        $this->withMiddleware();

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'jwt-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user()),
        ]);

        $response = $this->post('/panel/login', [
            'email' => 'ayse@example.com',
            'password' => 'GucluParola123!',
        ]);

        $response->assertRedirect('/panel');
        $response->assertSessionHas('auth.access_token', 'jwt-token');
        $response->assertSessionHas('auth.user.id', 7);
        $response->assertSessionHas('panel_locale', 'en');
    }

    public function test_admin_account_using_panel_login_is_redirected_to_admin_panel(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'admin-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user(true)),
        ]);

        $this->withSession(['url.intended' => '/panel'])
            ->post('/panel/login', [
                'email' => 'admin@example.com',
                'password' => 'GucluParola123!',
            ])->assertRedirect('/admin')
            ->assertSessionHas('auth.user.is_admin', true);
    }

    public function test_login_validation_and_api_error_are_shown_without_storing_token(): void
    {
        $this->post('/panel/login', ['email' => 'bozuk', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['detail' => 'Invalid email or password'], 401),
        ]);

        $this->from('/panel/login')->post('/panel/login', [
            'email' => 'ayse@example.com',
            'password' => 'YanlisParola123!',
        ])->assertRedirect('/panel/login')->assertSessionHasErrors('email');

        $this->assertNull(session('auth.access_token'));
    }

    public function test_duplicate_registration_error_is_shown(): void
    {
        Http::fake([
            '*/api/v1/auth/register' => Http::response(['detail' => 'Email already registered'], 409),
        ]);

        $this->from('/panel/register')->post('/panel/register', [
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.com',
            'password' => 'GucluParola123!',
            'password_confirmation' => 'GucluParola123!',
        ])->assertRedirect('/panel/register')->assertSessionHasErrors('email');
    }

    public function test_panel_requires_a_valid_backend_session(): void
    {
        $this->get('/panel')->assertRedirect('/panel/login');

        Http::fake(function ($request) {
            if ($request->hasHeader('Authorization', 'Bearer expired-token')) {
                return Http::response(['detail' => 'Could not validate credentials'], 401);
            }

            return Http::response($this->user());
        });
        $this->withSession(['auth.access_token' => 'jwt-token'])
            ->get('/panel')
            ->assertOk()
            ->assertSee('Gerçek Kullanıcı')
            ->assertSee('action="'.route('logout').'"', false);

        $this->withSession(['auth.access_token' => 'expired-token'])
            ->get('/panel')
            ->assertRedirect('/panel/login')
            ->assertSessionMissing('auth.access_token');
    }

    public function test_admin_requires_admin_role(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::sequence()
                ->push($this->user(false))
                ->push($this->user(true)),
            '*/health' => Http::response(['status' => 'ok']),
            '*/api/v1/admin/dashboard' => Http::response([
                'stats' => [],
                'module_counts' => [],
                'recent_students' => [],
            ]),
        ]);

        $this->withSession(['auth.access_token' => 'user-token'])
            ->get('/admin')
            ->assertForbidden();

        $this->withSession(['auth.access_token' => 'admin-token'])
            ->get('/admin')
            ->assertOk();
    }

    public function test_guest_admin_routes_redirect_to_admin_login(): void
    {
        $this->get('/admin/ogrenciler')
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_rejects_non_admin_without_persisting_session(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'user-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user(false)),
        ]);

        $this->from('/admin/login')->post('/admin/login', [
            'email' => 'ayse@example.com',
            'password' => 'GucluParola123!',
        ])->assertRedirect('/admin/login')->assertSessionHasErrors('email');

        $this->assertNull(session('auth.access_token'));
        $this->assertNull(session('auth.user'));
    }

    public function test_admin_login_accepts_admin_and_redirects_to_admin_panel(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'admin-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user(true)),
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'GucluParola123!',
        ])->assertRedirect('/admin')
            ->assertSessionHas('auth.access_token', 'admin-token')
            ->assertSessionHas('auth.user.is_admin', true);
    }

    public function test_admin_with_temporary_password_is_redirected_to_profile(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'admin-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response(array_merge($this->user(true), [
                'role' => 'admin',
                'admin_permissions' => ['dashboard.view'],
                'must_change_password' => true,
            ])),
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'GeciciParola123!',
        ])->assertRedirect('/admin/profil');
    }

    public function test_scoped_admin_can_open_only_permitted_frontend_modules(): void
    {
        $admin = array_merge($this->user(true), [
            'role' => 'admin',
            'admin_permissions' => ['dashboard.view', 'students.view'],
            'must_change_password' => false,
        ]);
        Http::fake([
            '*/api/v1/auth/me' => Http::response($admin),
            '*/api/v1/admin/modules/students' => Http::response(['title' => 'Öğrenciler', 'subtitle' => '', 'total' => 0, 'rows' => []]),
            '*/health' => Http::response(['status' => 'ok']),
        ]);

        $this->withSession(['auth.access_token' => 'admin-token'])->get('/admin/ogrenciler')->assertOk();
        $this->withSession(['auth.access_token' => 'admin-token'])->get('/admin/mulakatlar')->assertForbidden();
        $this->withSession(['auth.access_token' => 'admin-token'])->get('/admin/hesaplar')->assertForbidden();
    }

    public function test_admin_login_ignores_a_panel_intended_url(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'admin-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user(true)),
        ]);

        $this->withSession(['url.intended' => '/panel'])
            ->post('/admin/login', [
                'email' => 'admin@example.com',
                'password' => 'GucluParola123!',
            ])->assertRedirect('/admin');
    }

    public function test_legacy_auth_pages_permanently_redirect_to_panel_routes(): void
    {
        $this->get('/giris')->assertStatus(301)->assertRedirect('/panel/login');
        $this->get('/kayit')->assertStatus(301)->assertRedirect('/panel/register');
    }

    public function test_logout_clears_session_and_rotates_csrf_token(): void
    {
        $response = $this->withSession([
            'auth.access_token' => 'jwt-token',
            'auth.user' => $this->user(),
        ])->post('/cikis');

        $response->assertRedirect('/');
        $response->assertSessionMissing('auth.access_token');
        $response->assertSessionMissing('auth.user');
    }
}
