<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelCareerTargetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/panel/target' => Http::response(['target' => null], 200),
            'http://localhost:8000/api/v1/panel/job-listings/parse' => Http::response([
                'url' => 'https://www.linkedin.com/jobs/view/junior-product-analyst-123',
                'title' => 'Junior Product Analyst',
                'company' => 'LinkedIn',
                'source' => 'linkedin.com',
                'role_id' => 'job-linkedin-junior-product-analyst',
                'required_skills' => ['SQL', 'Product Analytics'],
                'parsed_from' => 'url',
            ], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);
    }

    public function test_selecting_ladder_role_redirects_to_role_based_roadmap_and_tasks(): void
    {
        session([
            'cv_analysis' => [
                'career_ladder' => [[
                    'id' => 'data-analyst',
                    'tier' => 'near',
                    'tier_label' => 'B — Yakın',
                    'title' => 'Veri Analisti',
                    'readiness' => 64,
                    'gap_count' => 2,
                    'gaps_summary' => 'Python, Power BI',
                    'weeks_estimate' => '4–8 hafta',
                    'swot' => [
                        'strengths' => ['SQL'],
                        'weaknesses' => ['Python', 'Power BI'],
                        'opportunities' => ['Python tamamlanınca uyum artar'],
                        'threats' => ['Python kanıtı eksik'],
                    ],
                ]],
            ],
        ]);

        $this->post(route('panel.career-ladder.select'), [
            'mode' => 'role',
            'role_id' => 'data-analyst',
        ])->assertRedirect(route('panel.roadmap'));

        $this->assertSame('Veri Analisti', session('panel_target_role.title'));
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'http://localhost:8000/api/v1/panel/target'
            && $request['title'] === 'Veri Analisti');

        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee('Veri Analisti', false)
            ->assertSee('Python mini proje üret', false)
            ->assertSee('Power BI dashboard taslağı hazırla', false);

        $this->get(route('panel.tasks'))
            ->assertOk()
            ->assertSee('Veri Analisti', false)
            ->assertSee('Python mini proje üret', false);
    }

    public function test_custom_role_name_shapes_roadmap(): void
    {
        $this->post(route('panel.career-ladder.select'), [
            'mode' => 'custom',
            'target_role' => 'Product Manager',
        ])->assertRedirect(route('panel.roadmap'));

        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee('Product Manager', false)
            ->assertSee('Product Manager rol gereksinimlerini araştır', false)
            ->assertSee('Product Manager için gap listesini netleştir', false);
    }

    public function test_job_url_shapes_roadmap(): void
    {
        $this->post(route('panel.career-ladder.select'), [
            'mode' => 'job_url',
            'job_url' => 'https://www.linkedin.com/jobs/view/junior-product-analyst-123',
        ])->assertRedirect(route('panel.roadmap'));

        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee('İlan hedefi: Junior Product Analyst', false)
            ->assertSee('İlan gereksinimlerini çıkar', false)
            ->assertSee('SQL case pratiği yap', false)
            ->assertSee('Product analytics funnel case çöz', false)
            ->assertSee('İlanı aç', false);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/panel/job-listings/parse');
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'http://localhost:8000/api/v1/panel/target'
            && $request['source'] === 'job_url'
            && $request['title'] === 'İlan hedefi: Junior Product Analyst');
    }

    public function test_learning_resources_are_specific_to_selected_role(): void
    {
        session([
            'panel_target_role' => [
                'source' => 'job_url',
                'role_id' => 'job-product-analyst',
                'title' => 'İlan hedefi: Product Analyst',
                'readiness' => 30,
                'gap_count' => 2,
                'gaps_summary' => 'SQL, Product Analytics',
                'weeks_estimate' => '2–4 hafta',
                'required_skills' => ['SQL', 'Product Analytics'],
            ],
        ]);

        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/panel/target' => Http::response(['target' => session('panel_target_role')], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $this->get(route('panel.learning'))
            ->assertOk()
            ->assertSee('İlan hedefi: Product Analyst', false)
            ->assertSee('SQLBolt Interactive SQL', false)
            ->assertSee('Product Analytics Micro-Course', false);
    }

}
