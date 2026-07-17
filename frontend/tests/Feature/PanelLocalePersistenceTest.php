<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiAuthenticated;
use App\Services\CareerTalentApiClient;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelLocalePersistenceTest extends TestCase
{
    public function test_database_preference_controls_the_current_panel_response(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/auth/me' => Http::response([
                'id' => 7,
                'full_name' => 'Locale User',
                'email' => 'locale@example.com',
                'is_active' => true,
                'is_admin' => false,
                'preferred_locale' => 'en',
            ], 200),
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);
        $request = Request::create('/panel', 'GET');
        $session = $this->app['session']->driver();
        $session->put('auth.access_token', 'token');
        $session->put('panel_locale', 'tr');
        $request->setLaravelSession($session);

        $response = (new EnsureApiAuthenticated($this->app->make(CareerTalentApiClient::class)))
            ->handle($request, fn () => response()->json(['locale' => app()->getLocale()]));

        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/api/v1/auth/me'));
        $this->assertSame('en', $request->session()->get('panel_locale'));
        $this->assertSame('en', json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['locale']);
    }

    public function test_locale_switch_keeps_current_language_when_database_update_fails(): void
    {
        Http::fake(fn () => Http::response(['detail' => 'unavailable'], 503));

        $this->withSession(['auth.access_token' => 'token', 'panel_locale' => 'tr'])
            ->from('/panel')
            ->get('/panel/locale/en')
            ->assertRedirect('/panel')
            ->assertSessionHas('panel_locale', 'tr')
            ->assertSessionHas('panel_error');
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function localizedCareerContentProvider(): array
    {
        return [
            'english panel' => ['en', 'Data Engineer', 'Build a data pipeline', 'Use Python and SQL', 'Strengths (S)'],
            'turkish panel' => ['tr', 'Veri Mühendisi', 'Bir veri hattı geliştir', 'Python ve SQL kullan', 'Güçlü (S)'],
        ];
    }

    #[DataProvider('localizedCareerContentProvider')]
    public function test_panel_renders_career_content_in_its_language_but_keeps_course_titles_original(
        string $locale,
        string $targetTitle,
        string $taskTitle,
        string $taskHint,
        string $swotHeading,
    ): void {
        $this->fakeLocalizedCareerContent($locale);
        app()->setLocale($locale);
        $this->withSession(['panel_locale' => $locale])
            ->get('/panel/kariyer-rotam')
            ->assertOk()
            ->assertSee($locale === 'en' ? 'Career Route' : 'Kariyer Rotam', false)
            ->assertSee($targetTitle, false)
            ->assertSee($taskTitle, false)
            ->assertSee($taskHint, false)
            ->assertSee($swotHeading, false)
            ->assertSee('AWS Data Engineering Professional Certificate', false);
    }

    private function fakeLocalizedCareerContent(string $locale): void
    {
        $english = $locale === 'en';
        $targetTitle = $english ? 'Data Engineer' : 'Veri Mühendisi';
        $taskTitle = $english ? 'Build a data pipeline' : 'Bir veri hattı geliştir';
        $taskHint = $english ? 'Use Python and SQL' : 'Python ve SQL kullan';

        Http::fake(function (HttpRequest $request) use ($locale, $targetTitle, $taskTitle, $taskHint) {
            $url = $request->url();
            if (str_ends_with($url, '/health')) {
                return Http::response(['status' => 'ok'], 200);
            }
            if (str_ends_with($url, '/api/v1/career/analysis/current')) {
                $english = $locale === 'en';

                return Http::response([
                    'status' => 'ready',
                    'current_role' => $targetTitle,
                    'radar' => [],
                    'career_ladder' => [[
                        'tier' => 'B',
                        'title' => $targetTitle,
                        'readiness' => 60,
                        'swot' => [
                            'strengths' => [$english ? 'Strong SQL' : 'Güçlü SQL'],
                            'weaknesses' => [$english ? 'Pipeline experience' : 'Veri hattı deneyimi'],
                            'opportunities' => [$english ? 'Cloud projects' : 'Bulut projeleri'],
                            'threats' => [$english ? 'Strong competition' : 'Yoğun rekabet'],
                        ],
                    ]],
                    'locale' => $locale,
                ], 200);
            }
            if (str_ends_with($url, '/api/v1/career/targets')) {
                return Http::response([[
                    'id' => 'target-1',
                    'title' => $targetTitle,
                    'status' => 'active',
                    'plan' => [],
                    'locale' => $locale,
                ]], 200);
            }
            if (str_ends_with($url, '/api/v1/career/targets/target-1/tasks')) {
                return Http::response([[
                    'id' => 'task-1',
                    'title' => $taskTitle,
                    'hint' => $taskHint,
                    'status' => 'pending',
                    'training_suggestions' => [[
                        'catalog_id' => 'course-1',
                        'title' => 'AWS Data Engineering Professional Certificate',
                        'provider' => 'Coursera',
                        'url' => 'https://example.com/course',
                    ]],
                    'locale' => $locale,
                ]], 200);
            }

            return Http::response([], 200);
        });
    }
}
