<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use App\Services\PanelTargetRoleStore;

class RoadmapController extends PanelController
{
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

        $readiness = $this->readiness($analysis, $target);
        return $this->panelView('app.roadmap', [
            'stats' => [
                'career' => (string) ($target['title'] ?? ($analysis['current_role'] ?? '')),
                'readiness' => $readiness,
            ],
            'roadmapTasks' => $tasks,
            'selectedTarget' => $target,
            'careerLadder' => $ladder,
            'careerTierMeta' => $this->tierMeta(),
            'fromApi' => $ladder !== [],
            'learningResources' => $this->trainingResources($tasks),
            'careerEngineError' => $analysis['error_message'] ?? ($analysisResult['error'] ?? null),
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

    private function readiness(array $analysis, ?array $target): int
    {
        if ($target && isset($target['readiness'])) {
            return (int) $target['readiness'];
        }
        $roles = is_array($analysis['career_ladder'] ?? null) ? $analysis['career_ladder'] : [];
        if ($target && ! empty($target['title'])) {
            foreach ($roles as $role) {
                if (is_array($role) && ($role['title'] ?? null) === $target['title']) {
                    return (int) ($role['readiness'] ?? 0);
                }
            }
        }
        $scores = array_values(array_filter(array_map(static fn ($role) => is_array($role) ? (int) ($role['readiness'] ?? 0) : null, $roles), static fn ($score) => $score !== null));

        return $scores === [] ? 0 : max($scores);
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
