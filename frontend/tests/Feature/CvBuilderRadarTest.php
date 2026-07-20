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
            ->assertSee('data-preview-language-selector', false)
            ->assertSee('x-show="mode === \'preview\'"', false)
            ->assertSee('profileCvUpload(', false)
            ->assertSee('panel-upload-zone', false)
            ->assertSeeInOrder(['id="harvard-preview"', 'Özgeçmiş Sürümlerim'], false)
            ->assertDontSee('id="yetenek-radari"', false)
            ->assertDontSee('data-skill-radar-layout', false)
            ->assertDontSee('data-cv-analysis-score', false);
    }

    public function test_builder_actions_and_active_cv_status_are_aligned_above_the_two_columns(): void
    {
        $response = $this->get(route('panel.cv-builder'));

        $response->assertOk()
            ->assertSee('data-cv-builder-status', false)
            ->assertSee('data-cv-preview-toolbar', false)
            ->assertSeeInOrder(['data-cv-builder-status', 'grid gap-8 lg:grid-cols-2'], false)
            ->assertSeeInOrder(['data-cv-preview-toolbar', 'id="harvard-preview"'], false)
            ->assertSee("form.append('pdf', blob, filename)", false)
            ->assertSee("form.append('locales', JSON.stringify(this.locales))", false)
            ->assertSee('resumePendingAnalysis()', false)
            ->assertDontSee('data-cv-header-actions', false)
            ->assertDontSee('data-cv-form-save', false);
    }

    public function test_generated_current_cv_is_rendered_as_the_active_analysis_source(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready', 'file_name' => 'Buse Batan CV.pdf',
                'radar' => [['label' => 'SQL', 'score' => 72, 'target' => 70]],
            ], 200),
            'http://localhost:8000/api/v1/cv/documents' => Http::response([
                ['id' => 'generated-current', 'kind' => 'generated', 'display_name' => 'Buse Batan CV.pdf', 'is_current' => true, 'created_at' => '2026-07-20T22:40:00+00:00'],
            ], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $this->get(route('panel.cv-builder'))
            ->assertOk()
            ->assertSee('data-cv-builder-status', false)
            ->assertDontSee('data-cv-current-file', false)
            ->assertSee('Buse Batan CV.pdf', false)
            ->assertSee('%72', false)
            ->assertSeeInOrder(['data-cv-analysis-upload', 'data-cv-builder-status', 'grid gap-8 lg:grid-cols-2'], false)
            ->assertSeeInOrder([__('panel.skill_radar.view_ladder'), __('panel.skill_radar.clear_cv')], false);
    }

    public function test_cv_builder_shows_only_upload_and_score_link_after_cv_analysis(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready', 'current_role' => 'Business Analyst', 'created_at' => '2026-07-04T00:00:00Z',
                'radar' => [['label' => 'Excel', 'score' => 80, 'target' => 70]], 'career_ladder' => [],
            ], 200),
            'http://localhost:8000/api/v1/cv/documents' => Http::response([
                ['id' => 'current-1', 'kind' => 'uploaded', 'display_name' => 'Fatma_Kesici.pdf', 'is_current' => true, 'created_at' => '2026-07-20T21:17:00+00:00'],
            ], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $response = $this->get(route('panel.cv-builder', ['locale' => 'en']));
        $response->assertOk()
            ->assertSee('data-cv-analysis-upload', false)
            ->assertSee('data-cv-current-file', false)
            ->assertSee('data-cv-analysis-score', false)
            ->assertSee('lg:grid-cols-[minmax(0,1fr)_auto]', false)
            ->assertSee('%80', false)
            ->assertSee(route('panel.career-ladder'), false)
            ->assertSee(__('panel.profile.cv_file_title'), false)
            ->assertSee(__('panel.cv_builder.upload_desc'), false)
            ->assertSee('Fatma_Kesici.pdf', false)
            ->assertSee(__('panel.profile.last_upload', ['date' => '20.07.2026 21:17']), false)
            ->assertSee('@click.stop="resetOpen = true"', false)
            ->assertSee('value="analysis"', false)
            ->assertSee('value="plan"', false)
            ->assertSee('value="all"', false)
            ->assertDontSee(__('panel.profile.cv_go_roadmap'), false)
            ->assertDontSee(__('panel.profile.remove'), false)
            ->assertDontSee('id="yetenek-radari"', false)
            ->assertDontSee('@toggle="onRadarToggle($event)"', false)
            ->assertDontSee('data-skill-radar-layout', false);
    }
}
