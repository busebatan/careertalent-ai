<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOrganizationsTest extends TestCase
{
    private function adminSession(): array
    {
        return [
            'auth.access_token' => 'admin-token',
            'auth.user' => [
                'id' => 27,
                'full_name' => 'Süper Yönetici',
                'email' => 'root@example.com',
                'is_admin' => true,
                'role' => 'super_admin',
                'admin_permissions' => [],
            ],
        ];
    }

    private function organization(array $overrides = []): array
    {
        return array_merge([
            'id' => 'org-1',
            'name' => 'Acme Teknoloji',
            'slug' => 'acme-teknoloji',
            'organization_type' => 'employer',
            'size_band' => 'smb',
            'status' => 'onboarding',
            'plan_code' => 'pilot',
            'billing_email' => 'billing@acme.example.com',
            'website' => 'https://acme.test',
            'description' => 'Teknoloji ekipleri için sürdürülebilir işe alım.',
            'logo_url' => 'https://cdn.acme.test/logo.svg',
            'members_count' => 0,
            'created_at' => '2026-07-19T10:00:00+00:00',
            'updated_at' => '2026-07-19T10:00:00+00:00',
        ], $overrides);
    }

    public function test_organizations_page_renders_live_api_records_and_create_contract(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/organizations' => Http::response([
                'total' => 1,
                'organizations' => [$this->organization()],
            ]),
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
        ]);

        $this->withSession($this->adminSession())
            ->get('/admin/kurumlar')
            ->assertOk()
            ->assertSee('Kurumlar')
            ->assertSee('Acme Teknoloji')
            ->assertSee('billing@acme.example.com')
            ->assertSee('admin-data-table', false)
            ->assertSee('openDrawer(organization)', false)
            ->assertSee('detailUrlTemplate', false)
            ->assertSee('__ID__', false)
            ->assertSee('action="'.route('admin.organizations.store').'"', false)
            ->assertDontSee('data-admin-organization=', false);
    }

    public function test_organization_forms_forward_validated_create_and_update_payloads(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/organizations' => Http::response($this->organization(), 201),
            'http://localhost:8000/api/v1/admin/organizations/org-1' => Http::response(
                $this->organization(['status' => 'active', 'plan_code' => 'growth'])
            ),
            'http://localhost:8000/api/v1/admin/organizations/org-1/owner-invitations' => Http::response([
                'token' => 'owner-token', 'email' => 'owner@acme.example.com', 'role' => 'owner',
            ], 201),
        ]);

        $this->withSession($this->adminSession())->post('/admin/kurumlar', [
            'name' => 'Acme Teknoloji',
            'organization_type' => 'employer',
            'size_band' => 'smb',
            'status' => 'onboarding',
            'plan_code' => 'pilot',
            'billing_email' => 'billing@acme.example.com',
            'website' => 'https://acme.test',
            'description' => 'Teknoloji ekipleri için sürdürülebilir işe alım.',
            'logo_url' => 'https://cdn.acme.test/logo.svg',
            'owner_email' => 'owner@acme.example.com',
        ])->assertRedirect('/admin/kurumlar');

        $this->withSession($this->adminSession())->patch('/admin/kurumlar/org-1', [
            'name' => 'Acme Teknoloji',
            'slug' => 'acme-teknoloji',
            'organization_type' => 'employer',
            'size_band' => 'mid_market',
            'status' => 'active',
            'plan_code' => 'growth',
            'billing_email' => 'billing@acme.example.com',
            'website' => 'https://acme.test',
            'description' => 'Yeni kurum açıklaması',
            'logo_url' => 'https://cdn.acme.test/new-logo.svg',
        ])->assertRedirect('/admin/kurumlar');

        $this->withSession($this->adminSession())->delete('/admin/kurumlar/org-1')
            ->assertRedirect('/admin/kurumlar');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/admin/organizations'
            && ! isset($request['slug'])
            && $request['organization_type'] === 'employer'
            && $request['plan_code'] === 'pilot'
            && $request['description'] === 'Teknoloji ekipleri için sürdürülebilir işe alım.'
        );
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && $request->url() === 'http://localhost:8000/api/v1/admin/organizations/org-1'
            && $request['status'] === 'active'
            && $request['size_band'] === 'mid_market'
            && $request['slug'] === 'acme-teknoloji'
            && $request['logo_url'] === 'https://cdn.acme.test/new-logo.svg'
        );
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8000/api/v1/admin/organizations/org-1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/admin/organizations/org-1/owner-invitations'
            && $request['email'] === 'owner@acme.example.com'
        );

        $this->withSession($this->adminSession())->post('/admin/kurumlar/org-1/sahip-daveti', [
            'owner_email' => 'second-owner@acme.example.com',
        ])->assertRedirect('/admin/kurumlar')->assertSessionHas('company_invite_url');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/admin/organizations/org-1/owner-invitations'
            && $request['email'] === 'second-owner@acme.example.com'
        );
    }
}
