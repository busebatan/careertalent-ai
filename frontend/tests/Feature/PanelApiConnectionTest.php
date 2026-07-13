<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelApiConnectionTest extends TestCase
{
    public function test_dashboard_uses_fastapi_panel_payload(): void
    {
        $task = ['id' => 'api-task-1', 'target_id' => 'target-1', 'title' => 'API görevini tamamla', 'hint' => 'FastAPI kariyer verisi', 'status' => 'pending', 'evidence_required' => true, 'evidence_types' => ['link'], 'skill_impacts' => ['API'], 'training_suggestions' => [['catalog_id' => 'api-course', 'title' => 'API Kaynak Kursu', 'provider' => 'FastAPI Academy', 'url' => 'https://example.com/api-course', 'skills' => ['API']]], 'feedback' => null];
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response(['status' => 'ready', 'current_role' => 'API Panel Analisti', 'created_at' => '2026-07-04T00:00:00Z', 'radar' => [], 'career_ladder' => [['title' => 'API Panel Analisti', 'readiness' => 91]]], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([['id' => 'target-1', 'title' => 'API Panel Analisti', 'source' => 'custom', 'status' => 'active']], 200),
            'http://localhost:8000/api/v1/career/targets/target-1/tasks' => Http::response([$task], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $response = $this->get('/panel');

        $response->assertOk();
        $response->assertSee('API Panel Analisti', false);
        $response->assertSee('API görevini tamamla', false);
        $response->assertSee('API Kaynak Kursu', false);
        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/v1/career/analysis/current');
    }

    public function test_panel_feature_pages_use_fastapi_payloads(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response(['status' => 'ready', 'current_role' => 'API', 'radar' => [['label' => 'API Kanıt Yeteneği', 'score' => 84, 'target' => 90]], 'career_ladder' => []], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([], 200),
            'http://localhost:8000/api/v1/panel/chat' => Http::response([
                'assistant' => [
                    'prompts' => [[
                        'q' => 'API asistan mesajı',
                        'a' => 'API hızlı cevap',
                    ]],
                ],
            ], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $this->get('/panel/yetenek-pasaportu')->assertOk()->assertSee('API Kanıt Yeteneği', false);
        $this->get('/panel/ai-yardimcisi')->assertOk()->assertSee('API asistan mesajı', false);

        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/v1/panel/chat');
    }

    public function test_job_match_analyze_posts_to_fastapi(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/career/jobs/analyze' => Http::response([
                    'id' => 'api-job-analysis',
                    'status' => 'queued',
                    'title' => 'API İş Fırsatları',
                    'company' => 'FastAPI HR',
                    'source' => 'api.example',
                    'source_url' => 'https://api.example/jobs/1',
                    'match_score' => 93,
                    'matched_skills' => ['SQL'],
                    'missing_skills' => [],
                    'recommendation' => 'apply',
                    'analyzed_at' => '2026-07-07T00:00:00+00:00',
            ], 200),
        ]);

        $response = $this->postJson('/panel/ilan-analizi/analiz', [
            'source_url' => 'https://api.example/jobs/1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('title', 'API İş Fırsatları');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/career/jobs/analyze');
    }

    public function test_job_match_status_save_apply_and_delete_proxy_to_career_api(): void
    {
        $job = ['id' => 'job-1', 'status' => 'ready', 'saved' => true, 'cv_suggestions' => []];
        Http::fake([
            'http://localhost:8000/api/v1/career/jobs/job-1' => Http::response($job, 200),
            'http://localhost:8000/api/v1/career/jobs/job-1/save' => Http::response($job, 200),
            'http://localhost:8000/api/v1/career/jobs/job-1/apply' => Http::response([...$job, 'apply_status' => 'queued'], 202),
        ]);

        $this->getJson('/panel/ilan-analizi/job-1/durum')->assertOk()->assertJsonPath('id', 'job-1');
        $this->postJson('/panel/ilan-analizi/job-1/kaydet')->assertOk()->assertJsonPath('saved', true);
        $this->postJson('/panel/ilan-analizi/job-1/uygula', ['suggestion_ids' => ['suggestion-1']])->assertStatus(202)->assertJsonPath('apply_status', 'queued');
        $this->deleteJson('/panel/ilan-analizi/job-1')->assertOk();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8000/api/v1/career/jobs/job-1');
    }
}
