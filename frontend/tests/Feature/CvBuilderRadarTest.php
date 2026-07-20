<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CvBuilderRadarTest extends TestCase
{
    public function test_cv_builder_shows_upload_area_without_radar_or_score_when_analysis_is_missing(): void
    {
        $response = $this->get(route('panel.cv-builder'));
        $response->assertOk()
            ->assertSee('data-cv-analysis-upload', false)
            ->assertSee('profileCvUpload(', false)
            ->assertSee('panel-upload-zone', false)
            ->assertSeeInOrder(['id="harvard-preview"', 'Özgeçmiş Sürümlerim (CV Merkezi)'], false)
            ->assertDontSee('id="yetenek-radari"', false)
            ->assertDontSee('data-skill-radar-layout', false)
            ->assertDontSee('data-cv-analysis-score', false);
    }

    public function test_cv_builder_shows_only_upload_and_score_link_after_cv_analysis(): void
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
            ->assertSee('data-cv-analysis-upload', false)
            ->assertSee('data-cv-analysis-score', false)
            ->assertSee('lg:grid-cols-[minmax(0,1fr)_auto]', false)
            ->assertSee('%80', false)
            ->assertSee(route('panel.career-ladder'), false)
            ->assertDontSee('id="yetenek-radari"', false)
            ->assertDontSee('@toggle="onRadarToggle($event)"', false)
            ->assertDontSee('data-skill-radar-layout', false)
            ->assertDontSee('Business Analyst', false)
            ->assertDontSee('Excel', false);
    }
}
