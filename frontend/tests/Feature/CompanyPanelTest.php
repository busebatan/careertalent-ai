<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\EnsureApiCandidate;
use App\Http\Middleware\EnsureApiCompany;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            EnsureApiAuthenticated::class,
            EnsureApiCandidate::class,
            EnsureApiCompany::class,
        ]);
    }

    private function user(): array
    {
        return ['id' => 44, 'full_name' => 'Acme Owner', 'email' => 'owner@acme.example.com', 'is_active' => true, 'is_admin' => false, 'role' => 'company', 'preferred_locale' => 'tr'];
    }

    private function membership(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => 'org-1', 'organization_name' => 'Acme Teknoloji', 'organization_slug' => 'acme',
            'organization_type' => 'employer', 'organization_status' => 'active', 'plan_code' => 'pilot',
            'billing_email' => 'billing@acme.example.com', 'website' => 'https://acme.example.com',
            'role' => 'owner', 'permissions' => ['dashboard.view', 'organization.update', 'members.view', 'members.invite', 'members.manage'],
        ], $overrides);
    }

    public function test_company_login_accepts_only_company_account_with_membership(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'company-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
        ]);

        $this->post('/company/login', ['email' => 'owner@acme.example.com', 'password' => 'password'])
            ->assertRedirect('/company')
            ->assertSessionHas('company.organization_id', 'org-1');
    }

    public function test_company_dashboard_team_and_profile_render_real_api_contracts(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
            '*/api/v1/company/dashboard' => Http::response(['organization' => $this->membership(), 'members_total' => 2, 'members_active' => 1, 'invitations_pending' => 1]),
            '*/api/v1/company/members' => Http::response(['members' => [[
                'membership_id' => 'm1', 'user_id' => 44, 'full_name' => 'Acme Owner', 'email' => 'owner@acme.example.com', 'role' => 'owner', 'status' => 'active', 'created_at' => '2026-07-19T10:00:00Z',
            ]], 'pending_invitations' => []]),
        ]);
        $session = ['auth.access_token' => 'company-token', 'auth.user' => $this->user()];

        $this->withSession($session)->get('/company')->assertOk()->assertSee('Acme Teknoloji')->assertSee('2');
        $this->withSession($session)->get('/company/ekip')->assertOk()->assertSee('Acme Owner')->assertSee('Ekip ve Yetkiler');
        $this->withSession($session)->get('/company/profil')->assertOk()->assertSee('billing@acme.example.com');
    }

    public function test_company_panel_uses_its_own_teal_emerald_visual_identity(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $views = implode("\n", array_map(
            static fn (string $path): string => file_get_contents($path),
            [
                resource_path('views/company/layouts/app.blade.php'),
                resource_path('views/company/dashboard.blade.php'),
                resource_path('views/company/team.blade.php'),
                resource_path('views/company/profile.blade.php'),
            ],
        ));

        $this->assertStringContainsString('--company-accent: #0f766e', $css);
        $this->assertStringContainsString('--company-brand: #10b981', $css);
        $this->assertStringContainsString('--company-accent-ink-dark: #5eead4', $css);
        $this->assertStringContainsString('--admin-accent: #ffbd72', $css);
        $this->assertStringContainsString('company-shell', $views);
        $this->assertStringContainsString('company-btn-primary', $views);
        $this->assertStringContainsString('panel-nav-link-active', $views);
        $this->assertStringNotContainsString('admin-shell', $views);
        $this->assertStringNotContainsString('admin-btn-', $views);
    }

    public function test_candidate_cannot_open_company_panel(): void
    {
        Http::fake(['*/api/v1/auth/me' => Http::response(array_merge($this->user(), ['role' => 'student']))]);
        $this->withSession(['auth.access_token' => 'candidate-token'])->get('/company')->assertForbidden();
    }

    public function test_company_account_cannot_open_candidate_panel(): void
    {
        Http::fake(['*/api/v1/auth/me' => Http::response($this->user())]);
        $this->withSession(['auth.access_token' => 'company-token'])->get('/panel')->assertForbidden();
    }

    public function test_company_can_persist_portal_language(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
            '*/api/v1/auth/me/locale' => Http::response(array_merge($this->user(), ['preferred_locale' => 'en'])),
        ]);

        $this->withSession(['auth.access_token' => 'company-token'])
            ->from('/company')
            ->get('/company/locale/en')
            ->assertRedirect('/company')
            ->assertSessionHas('panel_locale', 'en');
    }
}
