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

        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee('Veri Analisti', false)
            ->assertSee('Python kanıtı oluştur', false)
            ->assertSee('Power BI kanıtı oluştur', false);

        $this->get(route('panel.tasks'))
            ->assertOk()
            ->assertSee('Veri Analisti', false)
            ->assertSee('Python kanıtı oluştur', false);
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
            ->assertSee('İlan hedefi: Junior Product Analyst 123', false)
            ->assertSee('İlan gereksinimlerini çıkar', false)
            ->assertSee('İlanı aç', false);
    }
}
