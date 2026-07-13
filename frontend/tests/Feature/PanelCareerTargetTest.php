<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelCareerTargetTest extends TestCase
{
    /** @param list<array<string, mixed>> $roles */
    private function fakeCareerApi(array $roles = []): void
    {
        $target = null;
        Http::fake(function (Request $request) use (&$target, $roles) {
            $url = $request->url();
            if (str_ends_with($url, '/health')) {
                return Http::response(['status' => 'ok'], 200);
            }
            if (str_ends_with($url, '/api/v1/career/analysis/current')) {
                return Http::response(['status' => 'ready', 'current_role' => 'Analyst', 'radar' => [], 'career_ladder' => $roles], 200);
            }
            if (str_ends_with($url, '/api/v1/career/targets') && $request->method() === 'POST') {
                $data = $request->data();
                $target = ['id' => 'target-1', 'title' => $data['title'], 'source' => $data['source'], 'status' => 'active', 'plan' => []];
                return Http::response($target, 202);
            }
            if (str_ends_with($url, '/api/v1/career/targets')) {
                return Http::response($target ? [$target] : [], 200);
            }
            if (str_contains($url, '/api/v1/career/targets/target-1/tasks')) {
                return Http::response([
                    ['id' => 'task-1', 'target_id' => 'target-1', 'title' => 'AI gap kanıtı', 'hint' => 'AI görev ipucu', 'status' => 'pending', 'evidence_required' => true, 'evidence_types' => ['link', 'file'], 'skill_impacts' => ['Python'], 'training_suggestions' => [['catalog_id' => 'python-data', 'title' => 'Python for Everybody', 'provider' => 'Coursera', 'url' => 'https://www.py4e.com/', 'skills' => ['Python']]], 'feedback' => null],
                ], 200);
            }
            if (str_contains($url, '/api/v1/panel/job-listings/parse')) {
                return Http::response(['url' => 'https://www.linkedin.com/jobs/view/junior-product-analyst-123', 'title' => 'Junior Product Analyst', 'required_skills' => ['SQL', 'Product Analytics']], 200);
            }
            return Http::response([], 200);
        });
    }

    public function test_selecting_ladder_role_redirects_to_role_based_roadmap_and_tasks(): void
    {
        $this->fakeCareerApi([[
            'id' => 'data-analyst', 'tier' => 'B', 'title' => 'Veri Analisti', 'readiness' => 64,
            'swot' => ['strengths' => ['SQL'], 'weaknesses' => ['Python'], 'opportunities' => [], 'threats' => []],
        ]]);

        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'data-analyst'])
            ->assertRedirect(route('panel.roadmap'));
        $this->get(route('panel.roadmap'))->assertOk()->assertSee('Veri Analisti', false)->assertSee('AI gap kanıtı', false)->assertSee('Python for Everybody', false);
    }

    public function test_custom_role_name_is_persisted_to_backend_and_rendered(): void
    {
        $this->fakeCareerApi();
        $this->post(route('panel.career-ladder.select'), ['mode' => 'custom', 'target_role' => 'Product Manager'])
            ->assertRedirect(route('panel.roadmap'));
        $this->get(route('panel.roadmap'))->assertOk()->assertSee('Product Manager', false)->assertSee('AI gap kanıtı', false);
    }

    public function test_job_url_is_parsed_and_persisted_to_backend(): void
    {
        $this->fakeCareerApi();
        $this->post(route('panel.career-ladder.select'), ['mode' => 'job_url', 'job_url' => 'https://www.linkedin.com/jobs/view/junior-product-analyst-123'])
            ->assertRedirect(route('panel.roadmap'));
        $this->get(route('panel.roadmap'))->assertOk()->assertSee('Junior Product Analyst', false);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/v1/panel/job-listings/parse'));
    }

    public function test_learning_resources_are_read_from_ai_task_training_suggestions(): void
    {
        $this->fakeCareerApi();
        $this->post(route('panel.career-ladder.select'), ['mode' => 'custom', 'target_role' => 'Data Analyst'])->assertRedirect();
        $this->get(route('panel.roadmap'))->assertOk()->assertSee('Python for Everybody', false)->assertSee('Coursera', false);
    }
}
