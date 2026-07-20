<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CareerEngineApiTest extends TestCase
{
    public function test_cv_analysis_queue_response_preserves_analysis_id(): void
    {
        $this->withoutMiddleware();
        Http::fake([
            'http://localhost:8000/api/v1/cv/analyze' => Http::response([
                'analysis_id' => 'analysis-1', 'status' => 'queued',
            ], 202),
        ]);

        $response = $this->postJson(route('panel.cv.analyze'), [
            'cv' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
        ]);

        $response->assertStatus(202)->assertJsonPath('analysis_id', 'analysis-1')->assertJsonPath('status', 'queued');
    }

    public function test_analysis_status_is_proxied_to_current_backend_state(): void
    {
        $this->withoutMiddleware();
        Http::fake([
            'http://localhost:8000/api/v1/career/analysis/analysis-1' => Http::response([
                'id' => 'analysis-1', 'status' => 'ready', 'radar' => [], 'career_ladder' => [],
            ], 200),
        ]);

        $this->getJson(route('panel.cv.analysis-status', ['analysisId' => 'analysis-1']))
            ->assertOk()->assertJsonPath('status', 'ready');
    }

    public function test_analysis_stream_route_name_resolves_panel_path(): void
    {
        $this->assertStringContainsString(
            '/panel/cv-merkezi/analiz/analysis-1/akis',
            route('panel.cv.analysis-stream', ['analysisId' => 'analysis-1'])
        );
    }

    public function test_evidence_file_is_forwarded_as_private_multipart_upload(): void
    {
        $this->withoutMiddleware();
        Http::fake([
            'http://localhost:8000/api/v1/career/tasks/task-1/evidence/upload' => Http::response(['id' => 'e-1', 'status' => 'pending'], 201),
        ]);

        $response = $this->post(route('panel.tasks.evidence', ['taskId' => 'task-1']), [
            'kind' => 'file',
            'evidence_file' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
        ]);

        $response->assertCreated()->assertJsonPath('status', 'pending');
        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/v1/career/tasks/task-1/evidence/upload'
            && str_contains((string) ($request->header('Content-Type')[0] ?? ''), 'multipart/form-data')
            && str_contains($request->body(), 'proof.pdf'));
    }

    public function test_career_reset_scope_is_forwarded_to_authenticated_backend(): void
    {
        $this->withoutMiddleware();
        Http::fake([
            'http://localhost:8000/api/v1/career/reset' => Http::response([
                'status' => 'cleared',
                'scope' => 'all',
                'deleted' => ['analyses' => 1, 'targets' => 1, 'tasks' => 4, 'evidence' => 2],
            ], 200),
        ]);

        $this->postJson(route('panel.cv.clear'), ['scope' => 'all'])
            ->assertOk()
            ->assertJsonPath('scope', 'all')
            ->assertJsonPath('deleted.tasks', 4);

        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/v1/career/reset'
            && $request['scope'] === 'all');
    }
}
