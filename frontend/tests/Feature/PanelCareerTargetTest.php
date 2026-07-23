<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelCareerTargetTest extends TestCase
{
    /** @param list<array<string, mixed>> $roles */
    private function fakeCareerApi(array $roles = [], string $targetStatus = 'active', string $analysisStatus = 'ready'): void
    {
        $target = null;
        Http::fake(function (Request $request) use (&$target, $roles, $targetStatus, $analysisStatus) {
            $url = $request->url();
            if (str_ends_with($url, '/health')) {
                return Http::response(['status' => 'ok'], 200);
            }
            if (str_ends_with($url, '/api/v1/career/analysis/current')) {
                return Http::response(['status' => $analysisStatus, 'current_role' => 'Analyst', 'radar' => [], 'career_ladder' => $roles], 200);
            }
            if (str_ends_with($url, '/api/v1/career/targets') && $request->method() === 'POST') {
                $data = $request->data();
                $target = ['id' => 'target-1', 'title' => $data['title'], 'source' => $data['source'], 'status' => $targetStatus, 'plan' => []];
                return Http::response($target, 202);
            }
            if (str_ends_with($url, '/api/v1/career/targets')) {
                return Http::response($target ? [$target] : [], 200);
            }
            if (str_ends_with($url, '/api/v1/career/targets/target-1')) {
                return Http::response($target ?? ['id' => 'target-1', 'title' => 'Target', 'status' => 'queued', 'plan' => []], 200);
            }
            if (str_contains($url, '/api/v1/career/targets/target-1/tasks')) {
                if (($target['status'] ?? null) === 'queued') {
                    return Http::response([], 200);
                }
                return Http::response([
                    ['id' => 'task-1', 'target_id' => 'target-1', 'title' => 'AI gap kanıtı', 'hint' => 'AI görev ipucu', 'status' => 'pending', 'evidence_required' => true, 'evidence_types' => ['link', 'file'], 'skill_impacts' => ['Python'], 'training_suggestions' => [['catalog_id' => 'python-data', 'title' => 'Python for Everybody', 'provider' => 'Coursera', 'url' => 'https://www.py4e.com/', 'skills' => ['Python']]], 'feedback' => null],
                ], 200);
            }
            if (preg_match('#/api/v1/career/tasks/([^/]+)$#', $url) === 1 && $request->method() === 'PATCH') {
                $data = $request->data();

                return Http::response([
                    'id' => 'task-1',
                    'target_id' => 'target-1',
                    'title' => 'AI gap kanıtı',
                    'hint' => 'AI görev ipucu',
                    'note' => '',
                    'status' => $data['status'] ?? 'pending',
                    'evidence_required' => true,
                    'evidence_types' => ['link', 'file'],
                    'skill_impacts' => ['Python'],
                    'training_suggestions' => [],
                    'feedback' => null,
                ], 200);
            }
            if (str_contains($url, '/api/v1/career/personal-tasks')) {
                return Http::response([], 200);
            }
            if (str_contains($url, '/api/v1/panel/job-listings/parse')) {
                return Http::response(['url' => 'https://www.linkedin.com/jobs/view/junior-product-analyst-123', 'title' => 'Junior Product Analyst', 'required_skills' => ['SQL', 'Product Analytics']], 200);
            }
            return Http::response([], 200);
        });
    }

    public function test_selecting_ladder_role_shows_selected_state_on_roadmap(): void
    {
        $this->fakeCareerApi([
            [
                'id' => 'junior-da',
                'tier' => 'A',
                'title' => 'Junior Data Analyst',
                'readiness' => 85,
                'gap_count' => 4,
                'gaps_summary' => 'Cloud',
                'swot' => [
                    'strengths' => ['SQL'],
                    'weaknesses' => ['Cloud'],
                    'opportunities' => ['Bootcamp'],
                    'threats' => ['Competition'],
                ],
            ],
            [
                'id' => 'data-scientist',
                'tier' => 'B',
                'title' => 'Data Scientist',
                'readiness' => 40,
                'gap_count' => 3,
                'gaps_summary' => 'ML projects',
                'swot' => [
                    'strengths' => ['Statistics'],
                    'weaknesses' => ['Deep learning'],
                    'opportunities' => ['Courses'],
                    'threats' => ['Senior pool'],
                ],
            ],
        ]);

        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'data-scientist'])
            ->assertRedirect(route('panel.roadmap'));

        $response = $this->get(route('panel.roadmap'));
        $response
            ->assertOk()
            ->assertSee('panel-card-ladder-selected', false)
            ->assertSee('panel-card-ladder-collapsed', false)
            ->assertSee('panel-btn-ladder-selected', false)
            ->assertSee(__('panel.career_ladder.role_selected'), false)
            ->assertSee('data-selected-role-id="data-scientist"', false)
            ->assertSee('data-swot-toggleable="true"', false)
            ->assertSee('expandedRoles[', false)
            ->assertDontSee('careerPlanWatcher', false)
            ->assertSee('Deep learning', false);
        $response->assertSeeInOrder(['id="kariyer-merdiveni"', 'id="gorevler"'], false);
    }

    public function test_queued_target_shows_ai_plan_progress_and_watcher(): void
    {
        $roles = [[
            'id' => 'ml-engineer', 'tier' => 'C', 'title' => 'ML Engineer', 'readiness' => 35,
            'swot' => ['strengths' => ['Python'], 'weaknesses' => ['ML'], 'opportunities' => [], 'threats' => []],
        ]];
        $this->fakeCareerApi($roles, 'queued');

        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'ml-engineer'])->assertRedirect();
        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee('careerPlanWatcher', false)
            ->assertSee(__('panel.roadmap.plan_generating'), false)
            ->assertDontSee(__('panel.dashboard.tasks_empty'), false);
    }

    public function test_roadmap_shows_analysis_in_progress_while_cv_analysis_is_running(): void
    {
        $this->fakeCareerApi([], 'active', 'running');

        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee(__('panel.roadmap.analysis_in_progress'), false)
            ->assertSee('careerAnalysisWatcher', false)
            ->assertDontSee('AI kariyer merdiveni henüz hazır değil', false);
    }

    public function test_tasks_page_shows_plan_generating_for_queued_target(): void
    {
        $this->fakeCareerApi([[
            'id' => 'ml-engineer', 'tier' => 'C', 'title' => 'ML Engineer', 'readiness' => 35,
            'swot' => ['strengths' => ['Python'], 'weaknesses' => ['ML'], 'opportunities' => [], 'threats' => []],
        ]], 'queued');

        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'ml-engineer'])->assertRedirect();
        $this->get(route('panel.tasks'))
            ->assertOk()
            ->assertSee(__('panel.roadmap.plan_generating'), false)
            ->assertSee('careerPlanWatcher', false)
            ->assertDontSee(__('panel.dashboard.tasks_empty'), false);
    }

    public function test_analysis_status_proxy_returns_current_backend_state(): void
    {
        $this->fakeCareerApi([], 'active', 'running');

        $this->getJson(route('panel.roadmap.analysis-status'))
            ->assertOk()
            ->assertJsonPath('status', 'running');
    }

    public function test_plan_status_proxy_returns_current_target_state_and_task_count(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/career/targets/target-live' => Http::response(['id' => 'target-live', 'title' => 'ML Engineer', 'status' => 'active', 'plan' => []], 200),
            'http://localhost:8000/api/v1/career/targets/target-live/tasks' => Http::response([
                ['id' => 'task-ml', 'title' => 'ML portfolyosu', 'training_suggestions' => [['catalog_id' => 'python-data']]],
            ], 200),
        ]);

        $this->getJson(route('panel.roadmap.plan-status', ['targetId' => 'target-live']))
            ->assertOk()
            ->assertJsonPath('target_id', 'target-live')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('task_count', 1);
    }

    public function test_roadmap_ladder_is_active_before_target_selection(): void
    {
        $this->fakeCareerApi([
            [
                'id' => 'junior-da',
                'tier' => 'A',
                'title' => 'Junior Data Analyst',
                'readiness' => 85,
                'gap_count' => 4,
                'gaps_summary' => 'Cloud',
                'swot' => [
                    'strengths' => ['SQL'],
                    'weaknesses' => ['Cloud'],
                    'opportunities' => ['Bootcamp'],
                    'threats' => ['Competition'],
                ],
            ],
        ]);

        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee('panel-card-ladder-active', false)
            ->assertSee(__('panel.career_ladder.select_role'), false)
            ->assertSee('data-swot-default-open="true"', false)
            ->assertSee('SQL', false)
            ->assertSee('Cloud', false)
            ->assertSee('Bootcamp', false)
            ->assertSee('Competition', false)
            ->assertDontSee('data-swot-toggleable="true"', false)
            ->assertDontSee('panel-btn-ladder-selected', false);
    }

    public function test_roadmap_shows_analysis_cv_source_and_reset_controls(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready',
                'source' => 'archive_uploaded',
                'file_name' => 'Fatma_Kesici.pdf',
                'created_at' => '2026-07-20T21:17:00+00:00',
                'current_role' => 'Data Analyst',
                'radar' => [],
                'career_ladder' => [],
            ]),
            'http://localhost:8000/api/v1/career/targets' => Http::response([
                [
                    'id' => 'target-product-manager',
                    'title' => 'Ürün Yöneticisi',
                    'status' => 'active',
                    'plan' => [],
                ],
            ]),
            'http://localhost:8000/api/v1/career/targets/target-product-manager/tasks' => Http::response([]),
            'http://localhost:8000/*' => Http::response([]),
        ]);

        $this->get(route('panel.roadmap'))
            ->assertOk()
            ->assertSee('data-roadmap-selected-target', false)
            ->assertSee('data-roadmap-analysis-source', false)
            ->assertSee('panel-card mb-4 border-sky-500/30 bg-sky-500/10 p-4', false)
            ->assertSee('Ürün Yöneticisi')
            ->assertSee('Fatma_Kesici.pdf')
            ->assertSee('20.07.2026 21:17')
            ->assertSee('data-roadmap-clear', false)
            ->assertSee('data-roadmap-analysis-actions', false)
            ->assertSee('class="panel-sky-card-link"', false)
            ->assertSeeInOrder(['data-roadmap-selected-target', 'Ürün Yöneticisi', 'data-roadmap-analysis-source', 'Fatma_Kesici.pdf', '20.07.2026 21:17', 'data-roadmap-clear'], false)
            ->assertDontSee('data-roadmap-analysis-cv', false)
            ->assertDontSee('panel-card mb-4 border-emerald-500/30 bg-emerald-500/10 p-4', false)
            ->assertDontSee('inline-flex shrink-0 items-center justify-center rounded-xl border border-red-500/30', false)
            ->assertSee('careerDataReset(', false)
            ->assertSee((string) \Illuminate\Support\Js::from([
                'clearUrl' => route('panel.cv.clear'),
                'errorMessage' => __('panel.skill_radar.reset_failed'),
            ]), false)
            ->assertSee('value="analysis"', false)
            ->assertSee('value="plan"', false)
            ->assertSee('value="all"', false);
    }

    public function test_selecting_another_role_moves_the_fixed_open_state(): void
    {
        $roles = [
            ['id' => 'role-a', 'tier' => 'A', 'title' => 'Role A', 'readiness' => 80, 'swot' => ['strengths' => ['A1'], 'weaknesses' => [], 'opportunities' => [], 'threats' => []]],
            ['id' => 'role-b', 'tier' => 'B', 'title' => 'Role B', 'readiness' => 60, 'swot' => ['strengths' => ['B1'], 'weaknesses' => [], 'opportunities' => [], 'threats' => []]],
        ];
        $this->fakeCareerApi($roles);

        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'role-a'])->assertRedirect();
        $this->get(route('panel.roadmap'))->assertSee('data-selected-role-id="role-a"', false);
        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'role-b'])->assertRedirect();
        $this->get(route('panel.roadmap'))
            ->assertSee('data-selected-role-id="role-b"', false)
            ->assertDontSee('data-selected-role-id="role-a"', false);
    }

    public function test_tasks_page_shows_baseline_readiness_when_no_tasks_are_completed(): void
    {
        $this->fakeCareerApi([
            [
                'id' => 'cto',
                'tier' => 'C',
                'title' => 'Chief Technology Officer (CTO)',
                'readiness' => 20,
                'swot' => ['strengths' => ['Leadership'], 'weaknesses' => ['Cloud'], 'opportunities' => [], 'threats' => []],
            ],
        ]);

        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'cto'])
            ->assertRedirect(route('panel.roadmap'));

        $this->get(route('panel.tasks'))
            ->assertOk()
            ->assertSee('id="ozet"', false)
            ->assertSee(__('panel.dashboard.readiness_hybrid_hint', ['baseline' => 20]), false)
            ->assertSee('x-text="\'%\' + readiness"', false)
            ->assertSee(', 20)', false);
    }

    public function test_tasks_page_allows_ai_task_checkbox_without_evidence_form(): void
    {
        $this->fakeCareerApi([[
            'id' => 'cfo', 'tier' => 'C', 'title' => 'Chief Financial Officer', 'readiness' => 40,
            'swot' => ['strengths' => ['Finance'], 'weaknesses' => [], 'opportunities' => [], 'threats' => []],
        ]]);

        $this->post(route('panel.career-ladder.select'), ['mode' => 'role', 'role_id' => 'cfo'])
            ->assertRedirect(route('panel.roadmap'));

        $this->get(route('panel.tasks'))
            ->assertOk()
            ->assertSee('statusUpdate', false)
            ->assertDontSee(':disabled="task.source === \'ai\'"', false)
            ->assertDontSee('submitEvidence(task)', false);
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
