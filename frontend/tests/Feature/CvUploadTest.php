<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CvUploadTest extends TestCase
{
    public function test_cv_analyze_route_proxies_to_api_without_local_analysis_state(): void
    {
        $this->withoutMiddleware();

        Http::fake([
            'http://localhost:8000/api/v1/cv/analyze' => Http::response([
                'status' => 'ready',
                'skill_radar' => [
                    'overall_match' => 71,
                    'analyzed_at' => '4 Jul 2026',
                    'target_role' => 'Veri Analisti',
                    'skills' => [
                        ['label' => 'SQL', 'score' => 80, 'target' => 90],
                    ],
                ],
                'career_ladder' => [
                    [
                        'id' => 'data-analyst',
                        'tier' => 'near',
                        'tier_label' => 'B — Yakın',
                        'title' => 'Veri Analisti',
                        'readiness' => 61,
                        'gap_count' => 2,
                        'gaps_summary' => 'Python',
                        'weeks_estimate' => '4–8 hafta',
                        'swot' => [
                            'strengths' => ['SQL'],
                            'weaknesses' => ['Python'],
                            'opportunities' => ['Bootcamp'],
                            'threats' => ['Rekabet'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $pdf = UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf');

        $response = $this->post(route('panel.cv.analyze'), ['cv' => $pdf]);

        $response->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('career_ladder.0.title', 'Veri Analisti');

        $this->assertNull(session('cv_analysis'));
    }
    public function test_uploaded_cv_career_ladder_page_uses_cv_swot(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready',
                'career_ladder' => [[
                    'id' => 'data-analyst',
                    'tier' => 'B',
                    'tier_label' => 'B — Yakın',
                    'title' => 'Veri Analisti',
                    'readiness' => 64,
                    'gap_count' => 1,
                    'gaps_summary' => 'Python',
                    'weeks_estimate' => '4–8 hafta',
                    'swot_source' => 'cv_skills',
                    'swot' => [
                        'strengths' => ['SQL'],
                        'weaknesses' => ['Python'],
                        'opportunities' => ['CVdeki SQL güçlü yönünü Python eksiğiyle tamamlayınca Veri Analisti için uyum artar'],
                        'threats' => ['CVde Python kanıtı eksik kalırsa kısa listeye girme riski artar'],
                    ],
                ]],
            ], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $response = $this->get(route('panel.roadmap'));

        $response->assertOk()
            ->assertSee('CVdeki SQL güçlü yönünü Python eksiğiyle tamamlayınca', false)
            ->assertSee('CVde Python kanıtı eksik kalırsa', false)
            ->assertDontSee('Yoğun aday rekabeti', false);
    }

}
