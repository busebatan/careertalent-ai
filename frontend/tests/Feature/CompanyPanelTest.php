<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\EnsureApiAdmin;
use App\Http\Middleware\EnsureApiCandidate;
use App\Http\Middleware\EnsureApiCompany;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            EnsureApiAuthenticated::class,
            EnsureApiAdmin::class,
            EnsureApiCandidate::class,
            EnsureApiCompany::class,
        ]);
    }

    private function user(array $overrides = []): array
    {
        return array_merge(['id' => 44, 'full_name' => 'Acme Owner', 'email' => 'owner@acme.example.com', 'is_active' => true, 'is_admin' => false, 'role' => 'company', 'preferred_locale' => 'tr'], $overrides);
    }

    private function membership(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => 'org-1', 'organization_name' => 'Acme Teknoloji', 'organization_slug' => 'acme',
            'organization_type' => 'employer', 'organization_status' => 'active', 'plan_code' => 'pilot',
            'billing_email' => 'billing@acme.example.com', 'website' => 'https://acme.example.com',
            'role' => 'owner', 'permissions' => [
                'dashboard.view', 'positions.view', 'positions.write', 'positions.delete',
                'applications.view', 'applications.write', 'assessments.view', 'assessments.write',
                'scorecards.view', 'scorecards.submit', 'organization.update', 'members.view',
                'members.invite', 'members.manage',
            ],
        ], $overrides);
    }

    private function dashboardPayload(): array
    {
        return [
            'organization' => $this->membership(),
            'as_of' => '2026-07-20T16:00:00Z',
            'period' => ['key' => '30d', 'from' => '2026-06-20T16:00:00Z', 'to' => '2026-07-20T16:00:00Z'],
            'indicators' => [
                'active_positions' => 3, 'new_applications' => 12, 'assessment_pending' => 8,
                'technical_review_pending' => 4, 'shortlisted' => 5,
                'assessment_usage' => ['used' => 42, 'quota' => null],
            ],
            'tasks' => [[
                'type' => 'new_applications', 'priority' => 60, 'count' => 12,
                'position' => ['id' => 'position-1', 'title' => 'Backend Developer'],
                'target' => '/acme/pozisyonlar/position-1/adaylar?queue=new',
            ]],
            'summary' => [
                'application_to_assessment_rate' => 0.64,
                'assessment_to_interview_rate' => 0.31,
                'average_shortlist_hours' => 52.5,
                'largest_loss_stage' => ['stage' => 'technical_review', 'count' => 7],
            ],
            'members_total' => 2, 'members_active' => 1, 'invitations_pending' => 1,
        ];
    }

    public function test_company_login_accepts_only_company_account_with_membership(): void
    {
        Http::fake([
            '*/api/v1/auth/login' => Http::response(['access_token' => 'company-token', 'token_type' => 'bearer']),
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
        ]);

        $this->withSession(['auth.access_token' => 'admin-token', 'url.intended' => url('/panel')])
            ->post('/company/login', ['email' => 'owner@acme.example.com', 'password' => 'password'])
            ->assertRedirect('/acme')
            ->assertSessionHas('company_auth.access_token', 'company-token')
            ->assertSessionHas('auth.access_token', 'admin-token')
            ->assertSessionHas('company.organization_id', 'org-1')
            ->assertSessionMissing('url.intended');
    }

    public function test_company_dashboard_team_and_profile_render_real_api_contracts(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
            '*/api/v1/company/dashboard' => Http::response(['organization' => $this->membership(), 'members_total' => 2, 'members_active' => 1, 'invitations_pending' => 1]),
            '*/api/v1/company/members' => Http::response([
                'permission_keys' => ['dashboard.view', 'organization.update', 'members.view', 'members.invite', 'members.manage'],
                'members' => [[
                    'membership_id' => 'm1', 'user_id' => 44, 'full_name' => 'Acme Owner', 'email' => 'owner@acme.example.com', 'role' => 'owner',
                    'permissions' => ['dashboard.view', 'organization.update', 'members.view', 'members.invite', 'members.manage'],
                    'status' => 'active', 'created_at' => '2026-07-19T10:00:00Z',
                ]],
                'pending_invitations' => [],
            ]),
        ]);
        $session = ['company_auth.access_token' => 'company-token', 'company_auth.user' => $this->user()];

        $this->withSession($session)->get('/acme')
            ->assertOk()
            ->assertSee('Acme Teknoloji')
            ->assertSee('2')
            ->assertSee('data-workspace-shell="company"', false)
            ->assertSee('id="company-sidebar"', false)
            ->assertSee('workspace-header', false)
            ->assertSee('data-lucide="bell"', false)
            ->assertSee('data-lucide="languages"', false)
            ->assertSee('data-lucide="layout-dashboard"', false)
            ->assertDontSee('▦', false)
            ->assertDontSee('♙', false)
            ->assertDontSee('⚙', false)
            ->assertDontSee('◐', false);
        $this->withSession($session)->get('/acme/ekip')
            ->assertOk()
            ->assertSee('Acme Owner')
            ->assertSee('Ekip ve Yetkiler')
            ->assertSee('data-company-invite-form', false)
            ->assertSee('Kurum profilini güncelleme');
        $this->withSession($session)->get('/acme/profil')
            ->assertOk()
            ->assertSee('billing@acme.example.com')
            ->assertSee('action="'.route('company.logout').'"', false);
    }

    public function test_recruiting_dashboard_renders_operational_metrics_tasks_and_target_links(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
            '*/api/v1/company/dashboard*' => Http::response($this->dashboardPayload()),
        ]);

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme?period=30d')
            ->assertOk()
            ->assertSee('Aktif pozisyon')
            ->assertSee('Yeni başvuru')
            ->assertSee('Teknik ekip incelemesi bekleyen aday')
            ->assertSee('Bu ay kullanılan değerlendirme hakkı')
            ->assertSee('Backend Developer ilanında 12 yeni aday incelenmeyi bekliyor.')
            ->assertSee('/acme/pozisyonlar/position-1/adaylar?queue=new', false)
            ->assertSee('%64')
            ->assertSee('2,2 gün');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/company/dashboard?period=30d')
            && $request->hasHeader('X-Organization-ID', 'org-1'));
    }

    public function test_company_position_crud_and_candidate_lists_forward_tenant_contract(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) {
                return Http::response($this->user());
            }
            if (str_ends_with($request->url(), '/api/v1/company/context')) {
                return Http::response(['memberships' => [$this->membership()]]);
            }
            if (str_contains($request->url(), '/api/v1/company/positions')) {
                return Http::response($request->method() === 'GET' ? ['items' => []] : ['id' => 'position-1'], $request->method() === 'POST' ? 201 : 200);
            }
            if (str_contains($request->url(), '/api/v1/company/applications')) {
                return Http::response(['items' => []]);
            }
            if (str_contains($request->url(), '/api/v1/company/assessments')) {
                return Http::response(['usage' => ['used' => 0, 'quota' => null], 'items' => []]);
            }

            return Http::response(['status' => 'ok']);
        });
        $session = ['company_auth.access_token' => 'company-token'];

        $this->withSession($session)->get('/acme/pozisyonlar')->assertOk()->assertSee('Yeni pozisyon oluştur');
        $this->withSession($session)->post('/acme/pozisyonlar', [
            'title' => 'Backend Developer', 'status' => 'open',
        ])->assertRedirect('/acme/pozisyonlar');
        $this->withSession($session)->patch('/acme/pozisyonlar/position-1', [
            'title' => 'Senior Backend Developer', 'status' => 'paused',
        ])->assertRedirect('/acme/pozisyonlar');
        $this->withSession($session)->delete('/acme/pozisyonlar/position-1')->assertRedirect('/acme/pozisyonlar');
        $this->withSession($session)->get('/acme/adaylar?queue=new')->assertOk()->assertSee('Henüz aday bulunmuyor');
        $this->withSession($session)->get('/acme/degerlendirmeler')->assertOk()->assertSee('Bu ay kullanılan hak');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/company/positions')
            && $request->hasHeader('X-Organization-ID', 'org-1'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/company/applications?queue=new')
            && $request->hasHeader('X-Organization-ID', 'org-1'));
    }

    public function test_company_panel_uses_shared_admin_shell_without_changing_its_visual_identity(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $views = implode("\n", array_map(
            static fn (string $path): string => file_get_contents($path),
            [
                resource_path('views/company/layouts/app.blade.php'),
                resource_path('views/company/dashboard.blade.php'),
                resource_path('views/company/team.blade.php'),
                resource_path('views/company/profile.blade.php'),
                resource_path('views/workspace/partials/header.blade.php'),
                resource_path('views/workspace/partials/sidebar-nav.blade.php'),
            ],
        ));

        $this->assertStringContainsString('--company-accent: #0f766e', $css);
        $this->assertStringContainsString('--company-accent-hover: #115e59', $css);
        $this->assertStringContainsString('--company-brand: #10b981', $css);
        $this->assertStringContainsString('--company-accent-ink-dark: #5eead4', $css);
        $this->assertStringContainsString('--admin-accent: #ffbd72', $css);
        $this->assertStringContainsString('company-shell', $views);
        $this->assertStringContainsString("@include('workspace.partials.header'", $views);
        $this->assertStringContainsString("@include('workspace.partials.sidebar-nav'", $views);
        $this->assertStringContainsString('company-btn-primary', $views);
        $this->assertStringContainsString('panel-nav-link-active', $views);
        $this->assertStringNotContainsString('admin-shell', $views);
        $this->assertStringNotContainsString('admin-btn-', $views);
    }

    public function test_candidate_cannot_open_company_panel(): void
    {
        Http::fake(['*/api/v1/auth/me' => Http::response(array_merge($this->user(), ['role' => 'student']))]);
        $this->withSession(['auth.access_token' => 'candidate-token'])
            ->get('/acme')
            ->assertRedirect(route('company.login'))
            ->assertSessionHas('auth.access_token', 'candidate-token');
    }

    public function test_admin_without_company_session_is_redirected_instead_of_forbidden(): void
    {
        Http::fake(['*/api/v1/auth/me' => Http::response($this->user(['is_admin' => true, 'role' => 'super_admin']))]);

        $this->withSession(['auth.access_token' => 'admin-token'])
            ->get('/acme')
            ->assertRedirect(route('company.login'))
            ->assertSessionHas('auth.access_token', 'admin-token');
    }

    public function test_company_account_cannot_open_candidate_panel(): void
    {
        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/panel')
            ->assertRedirect(route('login'))
            ->assertSessionHas('company_auth.access_token', 'company-token');
    }

    public function test_company_can_persist_portal_language(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
            '*/api/v1/auth/me/locale' => Http::response(array_merge($this->user(), ['preferred_locale' => 'en'])),
        ]);

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->from('/acme')
            ->get('/acme/locale/en')
            ->assertRedirect('/acme')
            ->assertSessionHas('panel_locale', 'en')
            ->assertSessionHas('company_auth.user.preferred_locale', 'en');
    }

    public function test_company_navigation_and_routes_follow_persisted_permissions(): void
    {
        $dashboardOnly = $this->membership(['role' => 'viewer', 'permissions' => ['dashboard.view']]);
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$dashboardOnly]]),
            '*/api/v1/company/dashboard' => Http::response([
                'organization' => $dashboardOnly, 'members_total' => 1, 'members_active' => 1, 'invitations_pending' => 0,
            ]),
        ]);

        $session = ['company_auth.access_token' => 'company-token'];
        $this->withSession($session)->get('/acme')
            ->assertOk()
            ->assertDontSee('/acme/ekip', false)
            ->assertDontSee('/acme/profil', false)
            ->assertDontSee('data-company-profile', false);
        $this->withSession($session)->get('/acme/ekip')->assertForbidden();
        $this->withSession($session)->get('/acme/profil')->assertForbidden();
    }

    public function test_members_view_permission_is_read_only_in_team_ui(): void
    {
        $readOnly = $this->membership([
            'role' => 'viewer',
            'permissions' => ['dashboard.view', 'members.view'],
        ]);
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$readOnly]]),
            '*/api/v1/company/members' => Http::response([
                'permission_keys' => ['dashboard.view', 'organization.update', 'members.view', 'members.invite', 'members.manage'],
                'members' => [[
                    'membership_id' => 'm2', 'user_id' => 45, 'full_name' => 'Read Only Member', 'email' => 'viewer@acme.example.com',
                    'role' => 'viewer', 'permissions' => ['dashboard.view', 'members.view'], 'status' => 'active', 'created_at' => '2026-07-19T10:00:00Z',
                ]],
                'pending_invitations' => [],
            ]),
        ]);

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/ekip')
            ->assertOk()
            ->assertSee('Read Only Member')
            ->assertDontSee('data-company-invite-form', false)
            ->assertDontSee('data-company-member-form', false);
    }

    public function test_company_permission_forms_forward_selected_permissions_and_tenant_header(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) {
                return Http::response($this->user());
            }
            if (str_ends_with($request->url(), '/api/v1/company/context')) {
                return Http::response(['memberships' => [$this->membership()]]);
            }
            if (str_ends_with($request->url(), '/api/v1/company/invitations')) {
                return Http::response(['token' => 'invite-token'], 201);
            }
            if (str_ends_with($request->url(), '/api/v1/company/members/m2')) {
                return Http::response(['membership_id' => 'm2']);
            }

            return Http::response(['status' => 'ok']);
        });
        $session = ['company_auth.access_token' => 'company-token'];

        $this->withSession($session)->from('/acme/ekip')->post('/acme/ekip/davet', [
            'email' => 'recruiter@acme.example.com',
            'role' => 'recruiter',
            'permissions' => ['dashboard.view', 'members.view', 'members.invite'],
        ])->assertRedirect('/acme/ekip');

        $this->withSession($session)->from('/acme/ekip')->patch('/acme/ekip/m2', [
            'role' => 'hiring_manager',
            'status' => 'active',
            'permissions' => ['dashboard.view', 'members.view'],
        ])->assertRedirect('/acme/ekip');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/company/invitations')
            && $request->hasHeader('X-Organization-ID', 'org-1')
            && $request['permissions'] === ['dashboard.view', 'members.view', 'members.invite']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/api/v1/company/members/m2')
            && $request->hasHeader('X-Organization-ID', 'org-1')
            && $request['permissions'] === ['dashboard.view', 'members.view']);
    }

    public function test_company_switch_redirects_to_the_selected_organization_slug(): void
    {
        $memberships = [
            $this->membership(),
            $this->membership(['organization_id' => 'org-2', 'organization_name' => 'Beta', 'organization_slug' => 'beta']),
        ];
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => $memberships]),
        ]);

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->post('/acme/kurum-degistir/org-2')
            ->assertRedirect('/beta')
            ->assertSessionHas('company.organization_id', 'org-2');
    }

    public function test_legacy_company_session_is_migrated_without_relogin(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response($this->user()),
            '*/api/v1/company/context' => Http::response(['memberships' => [$this->membership()]]),
            '*/api/v1/company/dashboard' => Http::response(['organization' => $this->membership(), 'members_total' => 1, 'members_active' => 1, 'invitations_pending' => 0]),
        ]);

        $this->withSession(['auth.access_token' => 'legacy-company-token', 'auth.user' => $this->user()])
            ->get('/acme')
            ->assertOk()
            ->assertSessionHas('company_auth.access_token', 'legacy-company-token')
            ->assertSessionMissing('auth.access_token');
    }

    public function test_admin_and_company_sessions_use_their_own_tokens_in_the_same_browser(): void
    {
        $admin = $this->user([
            'id' => 1,
            'full_name' => 'Platform Admin',
            'email' => 'admin@example.com',
            'is_admin' => true,
            'role' => 'super_admin',
            'admin_permissions' => [],
            'must_change_password' => false,
        ]);
        Http::fake(function ($request) use ($admin) {
            if (str_ends_with($request->url(), '/api/v1/auth/login')) {
                return Http::response(['access_token' => 'company-token', 'token_type' => 'bearer']);
            }
            if (str_ends_with($request->url(), '/api/v1/auth/me')) {
                return Http::response($request->hasHeader('Authorization', 'Bearer company-token') ? $this->user() : $admin);
            }
            if (str_ends_with($request->url(), '/api/v1/company/context')) {
                return Http::response(['memberships' => [$this->membership()]]);
            }
            if (str_ends_with($request->url(), '/api/v1/company/dashboard')) {
                return Http::response(['organization' => $this->membership(), 'members_total' => 1, 'members_active' => 1, 'invitations_pending' => 0]);
            }
            if (str_ends_with($request->url(), '/api/v1/admin/dashboard')) {
                return Http::response(['stats' => [], 'module_counts' => [], 'recent_students' => []]);
            }

            return Http::response(['status' => 'ok']);
        });

        $this->withSession(['auth.access_token' => 'admin-token', 'auth.user' => $admin])
            ->post('/company/login', ['email' => 'owner@acme.example.com', 'password' => 'password'])
            ->assertRedirect('/acme')
            ->assertSessionHas('auth.access_token', 'admin-token')
            ->assertSessionHas('company_auth.access_token', 'company-token');

        $this->get('/acme')->assertOk()->assertSee('data-workspace-shell="company"', false);
        $this->get('/admin')->assertOk()->assertSee('data-workspace-shell="admin"', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/company/dashboard?period=30d')
            && $request->hasHeader('Authorization', 'Bearer company-token'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/admin/dashboard')
            && $request->hasHeader('Authorization', 'Bearer admin-token'));
    }

    public function test_company_logout_preserves_admin_session(): void
    {
        $this->withSession([
            'auth.access_token' => 'admin-token',
            'company_auth.access_token' => 'company-token',
            'company.organization_id' => 'org-1',
        ])->post('/company/cikis')
            ->assertRedirect(route('company.login'))
            ->assertSessionHas('auth.access_token', 'admin-token')
            ->assertSessionMissing('company_auth.access_token')
            ->assertSessionMissing('company.organization_id');
    }
}
