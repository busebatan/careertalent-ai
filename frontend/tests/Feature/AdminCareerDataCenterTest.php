<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminCareerDataCenterTest extends TestCase
{
    public function test_career_data_center_uses_current_admin_shell_and_only_api_records(): void
    {
        Http::fake($this->catalogFakes());

        $response = $this->withSession(['auth.access_token' => 'admin-token'])->get('/admin/kariyer-veri-merkezi?tab=roles');

        $response
            ->assertOk()
            ->assertSee('Kariyer Veri Merkezi')
            ->assertSee('admin-sidebar', false)
            ->assertSee('Veri Analisti')
            ->assertSee('SQL')
            ->assertSee('Bağlı yetenekler')
            ->assertDontSee('Demo kararları')
            ->assertDontSee('Ayşe Yılmaz');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://localhost:8000/api/v1/admin/career-data/roles');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://localhost:8000/api/v1/admin/career-data/skills');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://localhost:8000/api/v1/admin/career-data/requirements');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://localhost:8000/api/v1/admin/career-data/sources');
    }

    public function test_career_data_create_proxies_only_validated_role_payload(): void
    {
        Http::fake($this->catalogFakes());

        $response = $this->withSession(['auth.access_token' => 'admin-token'])->post('/admin/kariyer-veri-merkezi/roles', [
            'slug' => 'finansal-analist',
            'title' => 'Financial Analyst',
            'description' => 'Bütçe ve tahmin hazırlar.',
            'weeks_template' => 12,
            'unexpected' => 'must-not-reach-api',
        ]);

        $response->assertRedirect('/admin/kariyer-veri-merkezi?tab=roles');
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'http://localhost:8000/api/v1/admin/career-data/roles'
                && $request['slug'] === 'finansal-analist'
                && $request['weeks_template'] === 12
                && ! isset($request['unexpected']);
        });
    }

    public function test_career_data_never_falls_back_to_demo_when_one_source_is_unavailable(): void
    {
        $fakes = $this->catalogFakes();
        $fakes['http://localhost:8000/api/v1/admin/career-data/skills'] = Http::response(['detail' => 'catalog-down'], 503);
        Http::fake($fakes);

        $response = $this->withSession(['auth.access_token' => 'admin-token'])->get('/admin/kariyer-veri-merkezi?tab=skills');

        $response
            ->assertOk()
            ->assertSee('Yönetim verisi alınamadı: catalog-down')
            ->assertDontSee('Veri Analisti')
            ->assertDontSee('Demo aksiyon');
    }

    public function test_career_data_rejects_unknown_resource_before_calling_backend(): void
    {
        Http::fake();

        $this->withSession(['auth.access_token' => 'admin-token'])
            ->post('/admin/kariyer-veri-merkezi/unknown', [])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_view_only_career_data_admin_does_not_receive_write_or_delete_forms(): void
    {
        Http::fake($this->catalogFakes());

        $response = $this->withSession([
            'auth.access_token' => 'admin-token',
            'auth.user' => [
                'id' => 9, 'full_name' => 'Veri İzleyici', 'email' => 'viewer@example.com',
                'is_admin' => true, 'role' => 'admin', 'admin_permissions' => ['career_data.view'],
            ],
        ])->get('/admin/kariyer-veri-merkezi?tab=roles');

        $response->assertOk()->assertSee('Veri Analisti')
            ->assertDontSee('action="'.route('admin.career-data.store', 'roles').'"', false)
            ->assertDontSee('action="'.route('admin.career-data.update', ['resource' => 'roles', 'record' => 7]).'"', false)
            ->assertDontSee('action="'.route('admin.career-data.destroy', ['resource' => 'roles', 'record' => 7]).'"', false);
    }

    /**
     * @return array<string, \Illuminate\Http\Client\Response>
     */
    private function catalogFakes(): array
    {
        return [
            'http://localhost:8000/api/v1/admin/career-data/roles' => Http::response([[
                'id' => 7,
                'slug' => 'veri-analisti',
                'title' => 'Veri Analisti',
                'description' => 'Gerçek katalog kaydı.',
                'weeks_template' => 12,
                'required_skills' => ['SQL'],
                'requirement_count' => 1,
            ]]),
            'http://localhost:8000/api/v1/admin/career-data/skills' => Http::response([[
                'id' => 8,
                'slug' => 'sql',
                'name' => 'SQL',
                'skill_type' => 'technical',
                'description' => 'Gerçek katalog yeteneği.',
                'is_active' => true,
                'requirement_count' => 1,
            ]]),
            'http://localhost:8000/api/v1/admin/career-data/requirements' => Http::response([[
                'id' => 9,
                'career_role_id' => 7,
                'career_role_title' => 'Veri Analisti',
                'career_skill_id' => 8,
                'career_skill_name' => 'SQL',
                'data_source_id' => 10,
                'data_source_name' => 'Bootcamp rol kataloğu',
                'requirement_type' => 'required',
                'expected_level' => 'intermediate',
                'weight' => 100,
                'notes' => null,
            ]]),
            'http://localhost:8000/api/v1/admin/career-data/sources' => Http::response([[
                'id' => 10,
                'slug' => 'bootcamp-role-catalog',
                'name' => 'Bootcamp rol kataloğu',
                'source_type' => 'manual',
                'url' => null,
                'reference_uri' => 'data/roles/bootcamp_roles.json',
                'version' => '2026-07-14',
                'checksum_sha256' => str_repeat('a', 64),
                'license' => null,
                'description' => null,
                'status' => 'active',
                'last_verified_at' => null,
                'requirement_count' => 1,
            ]]),
        ];
    }
}
