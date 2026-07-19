<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizationVanityLoginTest extends TestCase
{
    private function profile(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Büşe Kurum',
            'slug' => 'buse-kurum',
            'website' => 'https://buse.example.com',
            'description' => 'Doğru yeteneği doğru ekiple buluşturur.',
            'logo_url' => 'https://cdn.example.com/buse-logo.svg',
        ], $overrides);
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

    public function test_vanity_url_renders_the_organization_branded_login(): void
    {
        Http::fake([
            '*/api/v1/company/organizations/buse-kurum' => Http::response($this->profile()),
        ]);

        $this->get('/buse-kurum')
            ->assertOk()
            ->assertSee('Büşe Kurum')
            ->assertSee('Doğru yeteneği doğru ekiple buluşturur.')
            ->assertSee('https://cdn.example.com/buse-logo.svg', false)
            ->assertSee('action="'.route('company.organization.login.submit', 'buse-kurum').'"', false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/api/v1/company/organizations/buse-kurum'));
    }

    public function test_vanity_login_selects_the_membership_matching_the_url(): void
    {
        Http::fake([
            '*/api/v1/company/organizations/buse-kurum' => Http::response($this->profile()),
            '*/api/v1/auth/login' => Http::response(['access_token' => 'company-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [
                $this->membership('org-other', 'other-company'),
                $this->membership('org-buse', 'buse-kurum'),
            ]]),
        ]);

        $this->post('/buse-kurum', [
            'email' => 'owner@buse.example.com',
            'password' => 'password',
        ])->assertRedirect('/company')
            ->assertSessionHas('company_auth.access_token', 'company-token')
            ->assertSessionHas('company.organization_id', 'org-buse');
    }

    public function test_vanity_login_rejects_an_account_outside_the_url_organization(): void
    {
        Http::fake([
            '*/api/v1/company/organizations/buse-kurum' => Http::response($this->profile()),
            '*/api/v1/auth/login' => Http::response(['access_token' => 'company-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [
                $this->membership('org-other', 'other-company'),
            ]]),
        ]);

        $this->from('/buse-kurum')->post('/buse-kurum', [
            'email' => 'owner@buse.example.com',
            'password' => 'password',
        ])->assertRedirect('/buse-kurum')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('company_auth.access_token')
            ->assertSessionMissing('company.organization_id');
    }

    public function test_unknown_vanity_url_is_not_a_generic_login_and_existing_routes_still_win(): void
    {
        Http::fake([
            '*/api/v1/company/organizations/bilinmeyen' => Http::response([], 404),
        ]);

        $this->get('/bilinmeyen')->assertNotFound();
        $this->get('/company/login')->assertOk()->assertSee('COMPANY');
        $this->get('/faq')->assertOk();
        $this->get('/up')->assertOk();
    }
}
