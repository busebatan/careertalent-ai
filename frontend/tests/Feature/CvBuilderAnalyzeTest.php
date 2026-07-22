<?php

namespace Tests\Feature;

use App\Data\PanelDemoData;
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
                'file_name' => 'Ayşe Yılmaz CV.pdf', 'cv_document_id' => 'generated-1',
            ], 202),
        ]);

        $response = $this->post(route('panel.cv.analyze-builder'), [
            'pdf' => UploadedFile::fake()->createWithContent('Ayşe Yılmaz CV.pdf', "%PDF-1.4\n%%EOF"),
            'display_name' => 'Ayşe Yılmaz CV.pdf',
            'language' => 'tr',
            'locales' => json_encode(PanelDemoData::cvDraft(), JSON_UNESCAPED_UNICODE),
        ]);

        $response->assertAccepted()
            ->assertJsonPath('analysis_id', 'analysis-builder-1')
            ->assertJsonPath('file_name', 'Ayşe Yılmaz CV.pdf');

        $this->assertNull(session('cv_analysis'));
        Http::assertSent(fn ($request) => $request->url() === 'http://localhost:8000/api/v1/cv/documents/generated/activate'
            && $request->method() === 'POST');
    }
}
