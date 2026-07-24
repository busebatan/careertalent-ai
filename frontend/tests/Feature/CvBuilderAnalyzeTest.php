<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CvBuilderAnalyzeTest extends TestCase
{
    public function test_contact_only_builder_does_not_create_fake_ai_analysis(): void
    {
        $this->withoutMiddleware();
        Http::fake();

        $response = $this->post(route('panel.cv.analyze-builder'), [
            'pdf' => UploadedFile::fake()->createWithContent('Contact Only CV.pdf', "%PDF-1.4\n%%EOF"),
            'display_name' => 'Contact Only CV.pdf',
            'language' => 'tr',
            'locales' => json_encode(['tr' => [
                'personal' => ['full_name' => 'Contact Only', 'email' => 'contact@example.com', 'phone' => '05551234567', 'location' => 'İstanbul', 'summary' => ''],
                'experience' => [], 'education' => [], 'skills' => [], 'projects' => [], 'certificates' => [],
            ]], JSON_UNESCAPED_UNICODE),
        ]);

        $response->assertUnprocessable()->assertJsonPath('message', __('panel.cv_builder.analyze_too_short'));
        Http::assertNothingSent();
    }

    public function test_builder_save_archives_and_queues_generated_cv_as_one_request(): void
    {
        $this->withoutMiddleware();

        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/generated/activate' => Http::response([
                'status' => 'queued', 'analysis_id' => 'analysis-builder-1',
                'file_name' => 'Test Candidate CV.pdf', 'cv_document_id' => 'generated-1',
            ], 202),
        ]);

        $response = $this->post(route('panel.cv.analyze-builder'), [
            'pdf' => UploadedFile::fake()->createWithContent('Test Candidate CV.pdf', "%PDF-1.4\n%%EOF"),
            'display_name' => 'Test Candidate CV.pdf',
            'language' => 'tr',
            'locales' => json_encode($this->careerDraft(), JSON_UNESCAPED_UNICODE),
            'document_id' => 'generated-1',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('analysis_id', 'analysis-builder-1')
            ->assertJsonPath('file_name', 'Test Candidate CV.pdf');

        $this->assertNull(session('cv_analysis'));
        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/v1/cv/documents/generated/activate'
             && $request->method() === 'POST'
             && str_contains((string) $request->body(), 'generated-1'));
    }

    public function test_builder_draft_save_persists_both_languages_without_starting_analysis(): void
    {
        $this->withoutMiddleware();

        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/generated/draft' => Http::response([
                'document' => ['id' => 'draft-document-1', 'display_name' => 'Test Candidate CV.pdf'],
                'main_version_id' => 'version-tr',
                'versions' => [
                    ['id' => 'version-tr', 'language' => 'tr', 'is_main' => true],
                    ['id' => 'version-en', 'language' => 'en', 'is_main' => false],
                ],
            ], 200),
        ]);

        $response = $this->post(route('panel.cv.builder-draft.save'), [
            'pdf' => UploadedFile::fake()->createWithContent('Test Candidate CV.pdf', "%PDF-1.4\n%%EOF"),
            'display_name' => 'Test Candidate CV.pdf',
            'language' => 'tr',
            'active_version_id' => 'version-tr',
            'locales' => json_encode([
                ...$this->careerDraft(),
                'en' => [
                    'personal' => ['full_name' => 'Test Candidate'],
                    'experience' => [],
                    'education' => [],
                    'skills' => [],
                    'projects' => [],
                    'certificates' => [],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $response->assertOk()
            ->assertJsonPath('document.id', 'draft-document-1')
            ->assertJsonPath('main_version_id', 'version-tr');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/v1/cv/documents/generated/draft'
            && $request->method() === 'POST'
            && str_contains((string) $request->body(), 'Test Candidate')
            && str_contains((string) $request->body(), 'version-tr'));
    }

    /** @return array<string, array<string, mixed>> */
    private function careerDraft(): array
    {
        return [
            'tr' => [
                'personal' => [
                    'full_name' => 'Test Candidate',
                    'email' => 'candidate@example.test',
                    'summary' => 'Backend uygulamaları geliştiren ve SQL veri modelleme deneyimi olan aday.',
                ],
                'experience' => [[
                    'organization' => 'Test Organization',
                    'title' => 'Backend Developer',
                    'bullets' => ['Laravel ile API geliştirdi ve SQL sorgularını optimize etti.'],
                ]],
                'education' => [],
                'skills' => [['category' => 'Teknik', 'items' => 'Laravel, SQL']],
                'projects' => [],
                'certificates' => [],
            ],
        ];
    }
}
