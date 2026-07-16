<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardCvRadarTest extends TestCase
{
    public function test_dashboard_shows_empty_state_without_cv_analysis(): void
    {
        $response = $this->get(route('panel.dashboard'));
        $response->assertOk()
            ->assertSee('data-dashboard-cv-empty', false)
            ->assertSee(__('panel.skill_radar.empty_title'), false)
            ->assertSee('href="'.route('panel.account').'#cv-yukle"', false)
            ->assertSee('href="'.route('panel.cv-builder').'"', false)
            ->assertDontSee('data-dashboard-cv-actions', false)
            ->assertDontSee('id="yetenek-radari"', false);
    }

    public function test_dashboard_shows_api_radar_after_cv_analysis(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready', 'current_role' => 'İş Analisti', 'created_at' => '2026-07-04T00:00:00Z',
                'radar' => [['label' => 'Excel', 'score' => 82, 'target' => 70], ['label' => 'İletişim', 'score' => 90, 'target' => 90]], 'career_ladder' => [['title' => 'İş Analisti', 'readiness' => 68]],
            ], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $response = $this->get(route('panel.dashboard'));
        $response->assertOk()
            ->assertSee('id="yetenek-radari"', false)
            ->assertSee('data-dashboard-cv-empty', false)
            ->assertDontSee('data-dashboard-cv-actions', false)
            ->assertSee('İş Analisti', false)
            ->assertSee('%86', false)
            ->assertSee(__('panel.skill_radar.from_cv_analysis'), false);
    }
}
