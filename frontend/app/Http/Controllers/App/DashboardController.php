<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use App\Services\PanelTargetRoleStore;
use App\Services\TaskReadinessCalculator;

class DashboardController extends PanelController
{
    public function index(CareerTalentApiClient $api)
    {
        $analysisResult = $api->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null)
            ? $analysisResult['body']
            : null;
        $analysisReady = is_array($analysis) && ($analysis['status'] ?? null) === 'ready';

        $target = PanelTargetRoleStore::get();
        $tasks = [];
        if (is_array($target) && ! empty($target['id'])) {
            $taskResult = $api->careerTargetTasks((string) $target['id']);
            $tasks = $this->listFromBody($taskResult['body'] ?? null);
        }

        $radar = $analysisReady ? $this->skillRadar($analysis) : [];
        if ($radar !== [] && ! empty($target['title'])) {
            $radar['target_role'] = (string) $target['title'];
        }
        $readinessStats = TaskReadinessCalculator::summary($tasks, $target, $analysisReady ? $analysis : null);
        $resources = $this->trainingResources($tasks);
        $status = $analysis['error_message'] ?? ($analysisResult['error'] ?? (($analysis['status'] ?? null) ?: 'empty'));

        return $this->panelView('app.dashboard', [
            'stats' => [
                'career' => (string) ($target['title'] ?? ($analysis['current_role'] ?? '')),
                'readiness' => $readinessStats['readiness'],
                'baseline' => $readinessStats['baseline'],
                'target_ready' => $readinessStats['target_ready'],
                'done' => $readinessStats['done'],
                'total' => $readinessStats['total'],
            ],
            'weeklyTasks' => $tasks,
            'learningResources' => $resources,
            'skillRadar' => $radar,
            'hasCvAnalysis' => $analysisReady && $radar !== [],
            'cvFileName' => '',
            'selectedTarget' => $target,
            'careerEngineStatus' => $status,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function listFromBody(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }

        $items = array_is_list($body) ? $body : ($body['tasks'] ?? []);

        return array_values(array_filter($items, 'is_array'));
    }

    /** @param array<string, mixed> $analysis */
    private function skillRadar(array $analysis): array
    {
        $skills = [];
        foreach (is_array($analysis['radar'] ?? null) ? $analysis['radar'] : [] as $item) {
            if (! is_array($item) || ! isset($item['label'])) {
                continue;
            }
            $skills[] = [
                'label' => (string) $item['label'],
                'score' => (int) ($item['score'] ?? 0),
                'target' => (int) ($item['target'] ?? 0),
            ];
        }

        if ($skills === []) {
            return [];
        }

        return [
            'skills' => $skills,
            'target_role' => (string) ($analysis['current_role'] ?? ''),
            'analyzed_at' => (string) ($analysis['created_at'] ?? ''),
            'analysis_id' => (string) ($analysis['id'] ?? ''),
            'file_name' => (string) ($analysis['file_name'] ?? 'cv'),
            'source' => (string) ($analysis['source'] ?? ''),
            'cv_document_id' => (string) ($analysis['cv_document_id'] ?? ''),
            'overall_match' => (int) round(array_sum(array_column($skills, 'score')) / count($skills)),
        ];
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
}
