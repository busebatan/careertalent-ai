<?php

namespace Tests\Feature;

use App\Data\PanelDemoData;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CvBuilderAnalyzeTest extends TestCase
{
    public function test_contact_only_builder_does_not_create_fake_ai_analysis(): void
    {
        $this->withoutMiddleware();
        Http::fake();

        $response = $this->postJson(route('panel.cv.analyze-builder'), [
            'locales' => ['tr' => [
                'personal' => ['full_name' => 'Contact Only', 'email' => 'contact@example.com', 'phone' => '05551234567', 'location' => 'İstanbul', 'summary' => ''],
                'experience' => [], 'education' => [], 'skills' => [], 'projects' => [], 'certificates' => [],
            ]],
            'locale' => 'tr',
        ]);

        $response->assertUnprocessable()->assertJsonPath('message', __('panel.cv_builder.analyze_too_short'));
        Http::assertNothingSent();
    }

    public function test_builder_analyze_route_proxies_to_api_without_local_analysis_state(): void
    {
        $this->withoutMiddleware();

        Http::fake([
            'http://localhost:8000/api/v1/cv/analyze-text' => Http::response([
                'status' => 'ready',
                'file_name' => 'ayse-yilmaz-builder.json',
                'skill_radar' => [
                    'overall_match' => 74,
                    'analyzed_at' => '4 Jul 2026',
                    'target_role' => 'Veri Analisti',
                    'skills' => [
                        ['label' => 'SQL', 'score' => 82, 'target' => 70],
                    ],
                ],
                'career_ladder' => [
                    ['id' => 'data-analyst', 'title' => 'Veri Analisti', 'readiness' => 74],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('panel.cv.analyze-builder'), [
            'locales' => PanelDemoData::cvDraft(),
            'locale' => 'tr',
        ]);

        $response->assertOk()
            ->assertJsonPath('file_name', 'ayse-yilmaz-builder.json')
            ->assertJsonPath('skill_radar.target_role', 'Veri Analisti');

        $this->assertNull(session('cv_analysis'));
    }
}
