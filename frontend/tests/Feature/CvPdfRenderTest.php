<?php

namespace Tests\Feature;

use App\Services\HarvardCvPdfRenderer;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class CvPdfRenderTest extends TestCase
{
    public function test_candidate_can_request_a_selectable_pdf_without_starting_chrome_in_the_feature_test(): void
    {
        $this->mock(HarvardCvPdfRenderer::class, function ($mock): void {
            $mock->shouldReceive('download')
                ->once()
                ->withArgs(function (array $locales, string $language): bool {
                    return $language === 'en'
                        && ($locales['en']['personal']['full_name'] ?? null) === 'Ada Candidate';
                })
                ->andReturn(response('%PDF-1.7\nselectable CV text', 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="ada-candidate.pdf"',
                ]));
        });

        $response = $this->postJson(route('panel.cv.pdf'), [
            'language' => 'en',
            'locales' => [
                'en' => [
                    'personal' => ['full_name' => 'Ada Candidate'],
                    'experience' => [],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_request_rejects_unknown_language_before_rendering(): void
    {
        $this->mock(HarvardCvPdfRenderer::class, function ($mock): void {
            $mock->shouldNotReceive('download');
        });

        $response = $this->postJson(route('panel.cv.pdf'), [
            'language' => 'de',
            'locales' => ['de' => ['personal' => ['full_name' => 'Ada Candidate']]],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotEmpty($response->json('errors.language.0'));
    }

    public function test_pdf_request_requires_the_selected_locale_payload_before_rendering(): void
    {
        $this->mock(HarvardCvPdfRenderer::class, function ($mock): void {
            $mock->shouldNotReceive('download');
        });

        $response = $this->postJson(route('panel.cv.pdf'), [
            'language' => 'tr',
            'locales' => ['en' => ['personal' => ['full_name' => 'Ada Candidate']]],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('locales.tr', $response->json('errors'));
    }

    public function test_renderer_uses_chrome_a4_margins_without_a_browser_in_the_test(): void
    {
        $pdf = Pdf::fake();

        app(HarvardCvPdfRenderer::class)->download([
            'tr' => [
                'personal' => ['full_name' => 'Ada Candidate', 'summary' => '<script>unsafe</script>'],
                'experience' => [],
                'enabledOptional' => ['courses'],
                'optional' => [
                    'courses' => [[
                        'name' => 'Chrome PDF Eğitimi',
                        'institution' => 'CareerTalent',
                        'date' => '2026',
                        'description' => 'A4 sayfa kırımı',
                    ]],
                ],
            ],
        ], 'tr');

        $pdf->assertRespondedWithPdf(function ($builder): bool {
            return config('laravel-pdf.driver') === 'chrome'
                && $builder->viewName === 'pdf.harvard-cv'
                && $builder->format === 'a4'
                && $builder->margins === ['top' => 12.0, 'right' => 12.0, 'bottom' => 12.0, 'left' => 12.0, 'unit' => 'mm']
                && str_contains($builder->getHtml(), '&lt;script&gt;unsafe&lt;/script&gt;')
                && str_contains($builder->getHtml(), 'Kurslar ve eğitimler')
                && str_contains($builder->getHtml(), 'Chrome PDF Eğitimi');
        });
    }

    public function test_pdf_template_has_page_break_guards_for_headings_and_entries(): void
    {
        $html = view('pdf.harvard-cv', [
            'language' => 'tr',
            'cv' => $this->cv(),
        ])->render();

        $this->assertStringContainsString('break-after: avoid', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringContainsString('Ada Candidate', $html);
    }

    /** @return array<string, mixed> */
    private function cv(): array
    {
        return [
            'personal' => [
                'full_name' => 'Ada Candidate',
                'email' => 'ada@example.test',
                'phone' => '',
                'location' => '',
                'linkedin' => '',
                'summary' => '',
            ],
            'education' => [],
            'experience' => [],
            'skills' => [],
            'projects' => [],
            'certificates' => [],
        ];
    }
}
