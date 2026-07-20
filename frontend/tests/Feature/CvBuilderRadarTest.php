<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CvBuilderRadarTest extends TestCase
{
    public function test_cv_builder_hides_demo_radar_without_analysis(): void
    {
        $response = $this->get(route('panel.cv-builder'));
        $response->assertOk()->assertDontSee('id="yetenek-radari"', false);
    }

    public function test_cv_builder_shows_api_radar_after_cv_analysis(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready', 'current_role' => 'Business Analyst', 'created_at' => '2026-07-04T00:00:00Z',
                'radar' => [['label' => 'Excel', 'score' => 80, 'target' => 70]], 'career_ladder' => [],
            ], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $response = $this->get(route('panel.cv-builder', ['locale' => 'en']));
        $response->assertOk()
            ->assertSee('id="yetenek-radari"', false)
            ->assertSee('Business Analyst', false)
            ->assertSee('%80', false)
            ->assertSee('group-open:rotate-180', false)
            ->assertSee('onRadarToggle', false)
            ->assertSee('Kariyer verilerini temizle', false)
            ->assertSee('value="analysis"', false)
            ->assertSee('value="plan"', false)
            ->assertSee('value="all"', false);
    }
}
