<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CvHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_account_shows_current_cv_and_downloadable_history(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/career/profile' => Http::response(['full_name' => 'User', 'email' => 'user@example.com', 'social_links' => []]),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready', 'source' => 'archive_uploaded', 'file_name' => 'old.pdf',
            ]),
            'http://localhost:8000/api/v1/cv/documents' => Http::response([
                ['id' => 'current-1', 'kind' => 'uploaded', 'display_name' => 'current.pdf', 'is_current' => true, 'created_at' => '2026-07-13T20:00:00+00:00'],
                ['id' => 'generated-1', 'kind' => 'generated', 'display_name' => 'Trendyol CV.pdf', 'is_current' => false, 'created_at' => '2026-07-13T21:30:00+00:00'],
                ['id' => 'upload-1', 'kind' => 'uploaded', 'display_name' => 'old.pdf', 'is_current' => false, 'created_at' => '2026-07-12T10:00:00+00:00'],
            ]),
            'http://localhost:8000/*' => Http::response([]),
        ]);

        $response = $this->get('/panel/hesap#cv-yukle');

        $response->assertOk()
            ->assertSee('current.pdf')->assertSee('Trendyol CV.pdf')->assertSee('old.pdf')
            ->assertSee('data-cv-history-analysis-ready', false)
            ->assertSee('data-initial-history-analysis-ready="true"', false)
            ->assertSee('Kariyer rotasına git')
            ->assertSee('href="'.route('panel.roadmap').'"', false)
            ->assertSeeInOrder(['data-cv-history-analysis-ready', '<ul class="mt-5'], false)
            ->assertDontSee('@drop.prevent="onDrop($event)"', false)
            ->assertDontSee('panel-upload-zone', false)
            ->assertDontSee('Tekrar indir')
            ->assertSee('Aç ve düzenle')->assertSee('Aktif analiz yap')
            ->assertSee('AI ile kutuları doldur')
            ->assertSee('Oluşturucuda aç')
            ->assertSee('data-cv-delete-dialog', false)
            ->assertSee('border-t border-slate-200 pt-5', false)
            ->assertSee('@click="deleteDialogOpen = true"', false)
            ->assertSee(__('panel.profile.cv_delete_title'))
            ->assertSee(__('panel.profile.cv_delete_action'))
            ->assertDontSee('return confirm(', false)
            ->assertSee('13.07.2026 21:30');

        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertCount(1, $xpath->query('//*[@id="cv-yukle"]//a[@href="'.route('panel.roadmap').'"]'));
    }

    public function test_cv_tab_selection_updates_hash_and_is_restored_after_reload(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/career/profile' => Http::response(['full_name' => 'User', 'email' => 'user@example.com', 'social_links' => []]),
            'http://localhost:8000/api/v1/cv/documents' => Http::response([]),
            'http://localhost:8000/*' => Http::response([]),
        ]);

        $this->get('/panel/hesap#cv-yukle')->assertOk()
            ->assertSee("window.location.hash === '#cv-yukle'", false)
            ->assertSee("selectTab('cv')", false)
            ->assertSee("history.replaceState(null, '', '#cv-yukle')", false);
    }

    public function test_archiving_current_cv_redirects_back_to_cv_tab_on_success_and_failure(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/current-1/archive' => Http::response(['id' => 'current-1'], 200),
        ]);

        $this->post('/panel/hesap/cv-gecmisi/current-1/arsivle')
            ->assertRedirect('/panel/hesap#cv-yukle')
            ->assertSessionHas('cv_status');

        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/current-2/archive' => Http::response(['detail' => 'failed'], 502),
        ]);

        $this->post('/panel/hesap/cv-gecmisi/current-2/arsivle')
            ->assertRedirect('/panel/hesap#cv-yukle')
            ->assertSessionHasErrors('cv');
    }

    public function test_deleting_history_cv_redirects_back_to_cv_tab_on_success_and_failure(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/history-1' => Http::response([], 204),
        ]);

        $this->delete('/panel/hesap/cv-gecmisi/history-1')
            ->assertRedirect('/panel/hesap#cv-yukle')
            ->assertSessionHas('cv_status');

        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/history-2' => Http::response(['detail' => 'failed'], 502),
        ]);

        $this->delete('/panel/hesap/cv-gecmisi/history-2')
            ->assertRedirect('/panel/hesap#cv-yukle')
            ->assertSessionHasErrors('cv');
    }

    public function test_generated_pdf_is_archived_before_laravel_returns_success(): void
    {
        Http::fake(['http://localhost:8000/api/v1/cv/documents/generated' => Http::response(['id' => 'generated-1', 'display_name' => 'İlan CV.pdf'], 201)]);
        $pdf = UploadedFile::fake()->createWithContent('İlan CV.pdf', "%PDF-1.4\n%%EOF");

        $this->post('/panel/cv-merkezi/pdf-arsivle', [
            'pdf' => $pdf, 'display_name' => 'İlan CV.pdf', 'language' => 'tr',
            'builder_data' => json_encode(['tr' => [], 'en' => []]),
        ])->assertCreated()->assertJsonPath('id', 'generated-1');

        Http::assertSent(function ($request): bool {
            $parts = collect($request->data());

            return $request->url() === 'http://localhost:8000/api/v1/cv/documents/generated'
                && $parts->contains(fn ($part) => ($part['name'] ?? null) === 'display_name' && ($part['contents'] ?? null) === 'İlan CV.pdf')
                && $parts->contains(fn ($part) => ($part['name'] ?? null) === 'language' && ($part['contents'] ?? null) === 'tr');
        });
    }

    public function test_history_download_and_builder_restore_are_account_scoped_proxies(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/generated-1/download' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Trendyol-CV.pdf"']),
            'http://localhost:8000/api/v1/cv/documents/generated-1' => Http::response(['id' => 'generated-1', 'kind' => 'generated', 'builder_data' => ['tr' => ['personal' => ['full_name' => 'Restore User'], 'education' => [], 'experience' => [], 'skills' => [], 'projects' => [], 'certificates' => [], 'enabledOptional' => [], 'optional' => []], 'en' => ['personal' => ['full_name' => 'Restore User'], 'education' => [], 'experience' => [], 'skills' => [], 'projects' => [], 'certificates' => [], 'enabledOptional' => [], 'optional' => []]]]),
            'http://localhost:8000/*' => Http::response([]),
        ]);

        $this->get('/panel/hesap/cv-gecmisi/generated-1/indir')->assertOk()->assertHeader('content-type', 'application/pdf')->assertContent('%PDF-1.4');
        $this->get('/panel/cv-merkezi?cvDocument=generated-1')->assertOk()->assertSee('Restore User')->assertSee('restoredFromHistory', false);
    }

    public function test_uploaded_ai_draft_opens_unsaved_builder_fields_with_missing_data_notice(): void
    {
        $locale = static fn (string $name): array => [
            'personal' => ['full_name' => $name, 'email' => 'ali@example.com', 'phone' => '', 'location' => 'İstanbul', 'linkedin' => '', 'summary' => 'Analist'],
            'education' => [], 'experience' => [], 'skills' => [['id' => 'skill-1', 'category' => 'Teknik', 'items' => 'SQL']],
            'projects' => [], 'certificates' => [], 'enabledOptional' => [], 'optional' => [],
        ];
        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/upload-1' => Http::response([
                'id' => 'upload-1', 'kind' => 'uploaded', 'builder_draft_status' => 'ready',
                'builder_data' => [
                    'tr' => $locale('Ali Aday'), 'en' => $locale('Ali Candidate'),
                    '_meta' => [
                        'source_file_name' => 'Ali_Aday.pdf',
                        'missing_fields' => ['tr' => ['personal.phone', 'education'], 'en' => ['personal.phone', 'education']],
                    ],
                ],
            ]),
            'http://localhost:8000/*' => Http::response([]),
        ]);

        $this->get('/panel/cv-merkezi?cvDocument=upload-1')
            ->assertOk()
            ->assertSee('Ali Aday')
            ->assertSee('AI taslağı kaynak CV’den hazırlandı')
            ->assertSee('Kaynak CV: Ali_Aday.pdf')
            ->assertSee('Telefon')
            ->assertSee('Eğitim')
            ->assertSee('2 alan boş bırakıldı')
            ->assertSee('if (!this.restoredFromHistory && !this._versionsInitialized)', false)
            ->assertSee('!hasUnsavedChanges', false)
            ->assertSee('markBuilderClean()', false)
            ->assertSee('data-cv-builder-import-dismiss', false)
            ->assertSee('builderImportNoticeOpen = false', false)
            ->assertSee(__('panel.cv_builder.import_notice_close'), false)
            ->assertSee('data-cv-builder-import-notice', false);
    }

    public function test_uploaded_cv_builder_draft_status_and_queue_are_account_scoped_proxies(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && $request->url() === 'http://localhost:8000/api/v1/cv/documents/upload-1') {
                return Http::response(['id' => 'upload-1', 'builder_draft_status' => 'ready']);
            }
            if ($request->method() === 'POST' && $request->url() === 'http://localhost:8000/api/v1/cv/documents/upload-1/builder-draft') {
                return Http::response(['id' => 'upload-1', 'builder_draft_status' => 'queued'], 202);
            }
            return Http::response([], 404);
        });

        $this->get('/panel/cv-merkezi/belgeler/upload-1/taslak')
            ->assertOk()
            ->assertJsonPath('builder_draft_status', 'ready');
        $this->post('/panel/cv-merkezi/belgeler/upload-1/taslak')
            ->assertStatus(202)
            ->assertJsonPath('builder_draft_status', 'queued');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/cv/documents/upload-1/builder-draft');
    }

    public function test_opening_uploaded_builder_draft_persists_it_before_redirecting(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/upload-1/builder-activate' => Http::response([
                'document_id' => 'upload-1',
                'main_version_id' => 'version-tr',
                'versions' => [
                    ['id' => 'version-tr', 'language' => 'tr', 'is_main' => true],
                    ['id' => 'version-en', 'language' => 'en', 'is_main' => false],
                ],
            ]),
        ]);

        $this->post('/panel/cv-merkezi/belgeler/upload-1/taslak/ac', ['language' => 'tr'])
            ->assertRedirect('/panel/cv-merkezi?cvDocument=upload-1');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/cv/documents/upload-1/builder-activate'
            && $request['language'] === 'tr');
    }

    public function test_ai_created_cv_version_opens_in_builder_without_replacing_main_cv(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/cv/versions' => Http::response([[
                'id' => 'version-ai', 'version_name' => 'Data Analyst için CV', 'language' => 'tr', 'is_main' => false,
                'payload' => ['personal' => ['full_name' => 'AI Draft User', 'summary' => 'İlana özel özet'], 'education' => [], 'experience' => [], 'skills' => [], 'projects' => [], 'certificates' => [], 'enabledOptional' => [], 'optional' => []],
            ]]),
            'http://localhost:8000/*' => Http::response([]),
        ]);

        $this->get('/panel/cv-merkezi?cvVersion=version-ai')
            ->assertOk()
            ->assertSee('AI Draft User')
            ->assertSee('İlana özel özet')
            ->assertSee('restoredFromHistory', false);
    }

    public function test_history_document_can_start_a_fresh_ai_analysis(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/cv/documents/generated-1/analyze' => Http::response([
                'analysis_id' => 'analysis-123',
                'status' => 'queued',
            ], 202),
        ]);

        $this->post('/panel/hesap/cv-gecmisi/generated-1/analiz')
            ->assertStatus(202)
            ->assertJsonPath('analysis_id', 'analysis-123')
            ->assertJsonPath('status', 'queued');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/cv/documents/generated-1/analyze');
    }

    public function test_dashboard_radar_displays_full_analysis_lineage(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'id' => 'analysis-123', 'status' => 'ready', 'current_role' => 'Veri Analisti',
                'profile' => [], 'skills' => [], 'career_ladder' => [],
                'radar' => [['label' => 'SQL', 'score' => 72, 'target' => 80]],
                'file_name' => 'Trendyol Veri Analisti CV.pdf', 'source' => 'archive_generated',
                'cv_document_id' => 'generated-1', 'created_at' => '2026-07-13T22:56:42+00:00',
            ]),
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
        ]);

        $this->get('/panel')->assertOk()
            ->assertSee('CV: Trendyol Veri Analisti CV.pdf')
            ->assertSee('Kaynak: CV geçmişi · oluşturulan CV')
            ->assertSee('Analiz: 13.07.2026 22:56')
            ->assertDontSee('Analiz ID: analysis-123');
    }
}
