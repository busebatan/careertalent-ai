<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\PanelTargetRoleStore;
use App\Services\CareerTalentApiClient;
use Illuminate\Support\Facades\Lang;

class CvBuilderController extends PanelController
{
    public function show(CareerTalentApiClient $api)
    {
        $analysisResult = $api->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];
        $hasCvAnalysis = ($analysis['status'] ?? null) === 'ready';
        $acceptedEvidence = [];
        $target = PanelTargetRoleStore::get();
        if (is_array($target) && ! empty($target['id'])) {
            $tasks = app(CareerTalentApiClient::class)->careerTargetTasks((string) $target['id']);
            $taskItems = is_array($tasks['body'] ?? null) && array_is_list($tasks['body'])
                ? $tasks['body']
                : ($tasks['body']['tasks'] ?? []);
            foreach ($taskItems as $task) {
                if (($task['status'] ?? null) === 'completed') {
                    $acceptedEvidence[] = [
                        'title' => (string) ($task['title'] ?? ''),
                        'skill_impacts' => is_array($task['skill_impacts'] ?? null) ? $task['skill_impacts'] : [],
                        'training_suggestions' => is_array($task['training_suggestions'] ?? null) ? $task['training_suggestions'] : [],
                    ];
                }
            }
        }

        return $this->panelView('app.cv-builder', [
            'cvDraft' => PanelDemoData::cvDraft(),
            'cvLabels' => $this->cvLabelsForJs(),
            'skillRadar' => $this->skillRadar($analysis),
            'hasCvAnalysis' => $hasCvAnalysis,
            'cvFileName' => '',
            'acceptedEvidence' => $acceptedEvidence,
        ]);
    }

    private function skillRadar(array $analysis): array
    {
        $skills = array_values(array_filter(array_map(static function ($item): ?array {
            if (! is_array($item) || ! isset($item['label'])) {
                return null;
            }
            return ['label' => (string) $item['label'], 'score' => (int) ($item['score'] ?? 0), 'target' => (int) ($item['target'] ?? 0)];
        }, is_array($analysis['radar'] ?? null) ? $analysis['radar'] : [])));
        if ($skills === []) {
            return [];
        }
        return [
            'skills' => $skills,
            'target_role' => (string) ($analysis['current_role'] ?? ''),
            'analyzed_at' => (string) ($analysis['created_at'] ?? ''),
            'overall_match' => (int) round(array_sum(array_column($skills, 'score')) / count($skills)),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cvLabelsForJs(): array
    {
        $labels = [];

        foreach (['tr', 'en'] as $locale) {
            $labels[$locale] = Lang::get('panel.cv_builder', [], $locale);
        }

        return $labels;
    }
}
