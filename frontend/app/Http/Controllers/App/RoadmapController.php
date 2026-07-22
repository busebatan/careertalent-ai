<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use App\Services\PanelTargetRoleStore;
use App\Services\TaskReadinessCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class RoadmapController extends PanelController
{
    public function planStatus(string $targetId, CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->careerTarget($targetId);
        if (! ($result['ok'] ?? false) || ! is_array($result['body'] ?? null)) {
            return response()->json(['message' => $result['error'] ?? 'Plan durumu alınamadı.'], $result['status'] ?? 502);
        }
        $target = $result['body'];
        $tasks = [];
        if (in_array($target['status'] ?? null, ['active', 'ready'], true)) {
            $tasks = $this->listFromBody($api->careerTargetTasks($targetId)['body'] ?? null);
        }

        return response()->json([
            'target_id' => $target['id'] ?? $targetId,
            'status' => $target['status'] ?? 'queued',
            'task_count' => count($tasks),
            'message' => $target['plan']['message'] ?? null,
        ]);
    }

    public function analysisStatus(CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->currentCareerAnalysis();
        if (! ($result['ok'] ?? false) || ! is_array($result['body'] ?? null)) {
            return response()->json([
                'message' => $result['error'] ?? __('panel.roadmap.analysis_status_error'),
            ], $result['status'] ?? 502);
        }

        $analysis = $result['body'];

        return response()->json([
            'status' => $analysis['status'] ?? 'queued',
            'message' => $analysis['error_message'] ?? null,
        ]);
    }

    public function show(CareerTalentApiClient $api)
    {
        $analysisResult = $api->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];
        $ladder = $this->normalizeLadder(is_array($analysis['career_ladder'] ?? null) ? $analysis['career_ladder'] : []);
        $target = PanelTargetRoleStore::get();
        $tasks = [];
        if (is_array($target) && ! empty($target['id'])) {
            $taskResult = $api->careerTargetTasks((string) $target['id']);
            $tasks = $this->listFromBody($taskResult['body'] ?? null);
        }

        $readinessStats = TaskReadinessCalculator::summary($tasks, $target, $analysis);
        $analysisStatus = (string) ($analysis['status'] ?? '');
        $isAnalysisPending = in_array($analysisStatus, ['queued', 'running'], true);
        $isPlanPending = is_array($target) && in_array($target['status'] ?? null, ['queued', 'running'], true);
        $analysisCv = null;
        if ($analysisStatus === 'ready' && ! empty($analysis['file_name'])) {
            try {
                $analyzedAt = ! empty($analysis['created_at']) ? Carbon::parse($analysis['created_at'])->format('d.m.Y H:i') : null;
            } catch (\Throwable) {
                $analyzedAt = null;
            }
            $analysisCv = ['name' => (string) $analysis['file_name'], 'analyzed_at' => $analyzedAt];
        }

        return $this->panelView('app.roadmap', [
            'stats' => [
                'career' => (string) ($target['title'] ?? ($analysis['current_role'] ?? '')),
                'readiness' => $readinessStats['readiness'],
                'baseline' => $readinessStats['baseline'],
                'target_ready' => $readinessStats['target_ready'],
                'done' => $readinessStats['done'],
                'total' => $readinessStats['total'],
            ],
            'roadmapTasks' => $tasks,
            'selectedTarget' => $target,
            'careerLadder' => $ladder,
            'careerTierMeta' => $this->tierMeta(),
            'fromApi' => $ladder !== [],
            'learningResources' => $this->trainingResources($tasks),
            'careerEngineError' => $analysis['error_message'] ?? ($analysisResult['error'] ?? null),
            'analysisStatus' => $analysisStatus,
            'isAnalysisPending' => $isAnalysisPending,
            'isPlanPending' => $isPlanPending,
            'analysisCv' => $analysisCv,
        ]);
    }

    private function listFromBody(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }
        $items = array_is_list($body) ? $body : ($body['tasks'] ?? []);

        return array_values(array_filter($items, 'is_array'));
    }

    /** @param list<array<string, mixed>> $roles */
    private function normalizeLadder(array $roles): array
    {
        return array_map(static function (array $role): array {
            $rawTier = strtoupper((string) ($role['tier'] ?? 'B'));
            $swot = is_array($role['swot'] ?? null) ? $role['swot'] : [];
            $weaknesses = is_array($swot['weaknesses'] ?? null) ? $swot['weaknesses'] : [];

            return [
                ...$role,
                'id' => (string) ($role['id'] ?? \Illuminate\Support\Str::slug((string) ($role['title'] ?? 'role'))),
                'tier' => ['A' => 'ready', 'B' => 'near', 'C' => 'reachable'][$rawTier] ?? 'near',
                'tier_label' => $role['tier_label'] ?? $rawTier,
                'gap_count' => (int) ($role['gap_count'] ?? count($weaknesses)),
                'gaps_summary' => (string) ($role['gaps_summary'] ?? implode(', ', $weaknesses)),
                'weeks_estimate' => $role['weeks_estimate'] ?? null,
                'swot' => [
                    'strengths' => is_array($swot['strengths'] ?? null) ? $swot['strengths'] : [],
                    'weaknesses' => $weaknesses,
                    'opportunities' => is_array($swot['opportunities'] ?? null) ? $swot['opportunities'] : [],
                    'threats' => is_array($swot['threats'] ?? null) ? $swot['threats'] : [],
                ],
            ];
        }, array_values(array_filter($roles, 'is_array')));
    }

    /** @param list<array<string, mixed>> $tasks */
    private function trainingResources(array $tasks): array
    {
        $resources = [];
        foreach ($tasks as $task) {
            foreach (is_array($task['training_suggestions'] ?? null) ? $task['training_suggestions'] : [] as $resource) {
                if (! is_array($resource) || empty($resource['catalog_id'])) {
                    continue;
                }
                $resources[(string) $resource['catalog_id']] = [
                    'id' => (string) $resource['catalog_id'],
                    'title' => (string) ($resource['title'] ?? $resource['catalog_id']),
                    'provider' => (string) ($resource['provider'] ?? ''),
                    'url' => (string) ($resource['url'] ?? '#'),
                    'price_type' => (string) ($resource['price_type'] ?? 'free'),
                    'price_label' => (string) ($resource['price_label'] ?? ''),
                    'price_range' => (string) ($resource['price_range'] ?? '0-500'),
                    'has_certificate' => (bool) ($resource['has_certificate'] ?? false),
                    'skills' => is_array($resource['skills'] ?? null) ? $resource['skills'] : [],
                ];
            }
        }
        return array_values($resources);
    }

    private function tierMeta(): array
    {
        $english = app()->getLocale() === 'en';
        return [
            'ready' => ['heading' => $english ? 'A · Ready now' : 'A · Şimdi hazır', 'hint' => $english ? 'Strongest fit' : 'En güçlü uyum'],
            'near' => ['heading' => $english ? 'B · Near target' : 'B · Yakın hedef', 'hint' => $english ? 'Close gaps first' : 'Önce boşlukları kapat'],
            'reachable' => ['heading' => $english ? 'C · Peak target' : 'C · Zirve hedef', 'hint' => $english ? 'Reachable upper level' : 'Ulaşılabilecek üst seviye'],
        ];
    }
}
