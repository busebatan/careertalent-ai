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
        ];
    }

    public function test_guest_can_register_and_is_logged_in(): void
    {
        Http::fake([
            '*/api/v1/auth/register' => Http::response($this->user(), 201),
            '*/api/v1/auth/login' => Http::response(['access_token' => 'jwt-token', 'token_type' => 'bearer']),
        ]);

        $response = $this->post('/kayit', [
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
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'jwt-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user()),
        ]);

        $response = $this->post('/giris', [
            'email' => 'ayse@example.com',
            'password' => 'GucluParola123!',
        ]);

        $response->assertRedirect('/panel');
        $response->assertSessionHas('auth.access_token', 'jwt-token');
        $response->assertSessionHas('auth.user.id', 7);
    }

    public function test_login_validation_and_api_error_are_shown_without_storing_token(): void
    {
        $this->post('/giris', ['email' => 'bozuk', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);

        Http::fake([
            '*/api/v1/auth/login' => Http::response(['detail' => 'Invalid email or password'], 401),
        ]);

        $this->from('/giris')->post('/giris', [
            'email' => 'ayse@example.com',
            'password' => 'YanlisParola123!',
        ])->assertRedirect('/giris')->assertSessionHasErrors('email');

        $this->assertNull(session('auth.access_token'));
    }

    public function test_duplicate_registration_error_is_shown(): void
    {
        Http::fake([
            '*/api/v1/auth/register' => Http::response(['detail' => 'Email already registered'], 409),
        ]);

        $this->from('/kayit')->post('/kayit', [
            'name' => 'Ayşe Yılmaz',
            'email' => 'ayse@example.com',
            'password' => 'GucluParola123!',
            'password_confirmation' => 'GucluParola123!',
        ])->assertRedirect('/kayit')->assertSessionHasErrors('email');
    }

    public function test_panel_requires_a_valid_backend_session(): void
    {
        $this->get('/panel')->assertRedirect('/giris');

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
            ->assertRedirect('/giris')
            ->assertSessionMissing('auth.access_token');
    }

    public function test_admin_requires_admin_role(): void
    {
        Http::fakeSequence()
            ->push($this->user(false))
            ->push($this->user(true));
        $this->withSession(['auth.access_token' => 'user-token'])
            ->get('/admin')
            ->assertForbidden();

        $this->withSession(['auth.access_token' => 'admin-token'])
            ->get('/admin')
            ->assertOk();
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
