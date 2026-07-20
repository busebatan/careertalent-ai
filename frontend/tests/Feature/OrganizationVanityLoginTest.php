<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\EnsureApiCompany;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizationVanityLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            EnsureApiAuthenticated::class,
            EnsureApiCompany::class,
        ]);
    }

    private function user(): array
    {
        return [
            'id' => 44,
            'full_name' => 'Büşe Owner',
            'email' => 'owner@buse.example.com',
            'is_active' => true,
            'is_admin' => false,
            'role' => 'company',
            'preferred_locale' => 'tr',
        ];
    }

    private function membership(string $id, string $slug): array
    {
        return [
            'organization_id' => $id,
            'organization_name' => ucfirst($slug),
            'organization_slug' => $slug,
            'organization_type' => 'employer',
            'organization_status' => 'active',
            'plan_code' => 'enterprise',
            'billing_email' => 'billing@example.com',
            'website' => 'https://example.com',
            'role' => 'owner',
            'permissions' => ['dashboard.view'],
        ];
    }

    public function test_company_login_is_canonical_and_company_entry_is_session_aware(): void
    {
        $this->get('/company/login')
            ->assertOk()
            ->assertSee('action="'.route('company.login.submit').'"', false);
        $this->get('/company')->assertRedirect('/company/login');

        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [
                $this->membership('org-buse', 'buse-kurum'),
            ]]),
        ]);
        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/company')
            ->assertRedirect('/buse-kurum');
    }

    public function test_guest_slug_redirects_to_company_login_and_login_returns_to_that_organization(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'company-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [
                $this->membership('org-other', 'other-company'),
                $this->membership('org-buse', 'buse-kurum'),
            ]]),
        ]);

        $this->get('/buse-kurum')
            ->assertRedirect('/company/login')
            ->assertSessionHas('url.intended', url('/buse-kurum'));

        $this->post('/company/login', [
            'email' => 'owner@buse.example.com',
            'password' => 'password',
        ])->assertRedirect('/buse-kurum')
            ->assertSessionHas('company_auth.access_token', 'company-token')
            ->assertSessionHas('company.organization_id', 'org-buse')
            ->assertSessionMissing('url.intended');
    }

    public function test_authenticated_slug_is_the_company_dashboard_for_that_membership(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [
                $this->membership('org-buse', 'buse-kurum'),
            ]]),
            '*/api/v1/company/dashboard' => Http::response([
                'organization' => $this->membership('org-buse', 'buse-kurum'),
                'members_total' => 3,
                'members_active' => 2,
                'invitations_pending' => 1,
            ]),
        ]);

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/buse-kurum')
            ->assertOk()
            ->assertSee('data-workspace-shell="company"', false)
            ->assertSessionHas('company.organization_id', 'org-buse');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/company/dashboard?period=30d')
            && $request->hasHeader('X-Organization-ID', 'org-buse'));
    }

    public function test_company_user_cannot_open_another_organization_slug(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [
                $this->membership('org-other', 'other-company'),
            ]]),
        ]);

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/buse-kurum')
            ->assertNotFound();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v1/company/dashboard'));
    }

    public function test_slug_no_longer_accepts_login_posts_and_static_routes_still_win(): void
    {
        $this->post('/buse-kurum', [
            'email' => 'owner@buse.example.com',
            'password' => 'password',
        ])->assertStatus(405);

        $this->get('/faq')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/company/login')->assertOk();
    }
}
