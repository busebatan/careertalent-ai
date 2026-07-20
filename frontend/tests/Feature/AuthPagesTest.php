<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    public function test_panel_login_is_a_standalone_product_auth_page(): void
    {
        $response = $this->get('/panel/login');

        $response->assertOk()
            ->assertSee('data-auth-portal="panel"', false)
            ->assertSee('action="'.route('login.submit').'"', false)
            ->assertSee('CV YÜKLENDİ')
            ->assertSee('YETENEK RADARI')
            ->assertSee('HEDEF MESLEK')
            ->assertSee('GÖREVLER')
            ->assertSee('Kurum girişi')
            ->assertSee('href="'.route('company.login').'"', false)
            ->assertDontSee('href="'.route('admin.login').'"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('aria-controls="password"', false)
            ->assertDontSee('marketing-header', false)
            ->assertDontSee('marketing-footer', false)
            ->assertDontSee('Google ile giriş')
            ->assertDontSee('LinkedIn ile giriş');
    }

    public function test_panel_register_uses_only_supported_fields_and_canonical_action(): void
    {
        $response = $this->get('/panel/register');

        $response->assertOk()
            ->assertSee('data-auth-mode="register"', false)
            ->assertSee('action="'.route('register.submit').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('minlength="8"', false)
            ->assertSee('maxlength="128"', false)
            ->assertDontSee('name="company"', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('marketing-header', false);
    }

    public function test_admin_login_is_visually_and_semantically_distinct(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk()
            ->assertSee('data-auth-portal="admin"', false)
            ->assertSee('auth-shell--admin', false)
            ->assertSee('Yönetici erişimi')
            ->assertSee('Yönetim alanına giriş')
            ->assertSee('action="'.route('admin.login.submit').'"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee(route('register'), false)
            ->assertDontSee('marketing-header', false)
            ->assertDontSee('marketing-footer', false);
    }

    public function test_company_login_is_the_public_management_entry(): void
    {
        $response = $this->get('/company/login');

        $response->assertOk()
            ->assertSee('data-auth-portal="company"', false)
            ->assertSee('Kurum erişimi')
            ->assertSee('Kurum paneline giriş')
            ->assertSee('action="'.route('company.login.submit').'"', false)
            ->assertDontSee('href="'.route('admin.login').'"', false);
    }

    public function test_panel_and_admin_forms_keep_csrf_and_accessibility_contracts(): void
    {
        foreach (['/panel/login', '/panel/register', '/company/login', '/admin/login'] as $uri) {
            $response = $this->get($uri);

            $response->assertOk()
                ->assertSee('name="_token"', false)
                ->assertSee('class="auth-skip-link"', false)
                ->assertSee('id="main-content"', false)
                ->assertSee('required', false)
                ->assertSee('focus-visible', false)
                ->assertSee('prefers-reduced-motion', false);
        }
    }

    public function test_named_auth_routes_point_to_canonical_paths(): void
    {
        $this->assertSame(url('/panel/login'), route('login'));
        $this->assertSame(url('/panel/login'), route('login.submit'));
        $this->assertSame(url('/panel/register'), route('register'));
        $this->assertSame(url('/panel/register'), route('register.submit'));
        $this->assertSame(url('/admin/login'), route('admin.login'));
        $this->assertSame(url('/admin/login'), route('admin.login.submit'));
    }
}
