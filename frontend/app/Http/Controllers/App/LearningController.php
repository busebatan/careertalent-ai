<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use App\Services\PanelTargetRoleStore;

class LearningController extends PanelController
{
    public function show(CareerTalentApiClient $api)
    {
        $target = PanelTargetRoleStore::get();
        $tasks = [];
        if (is_array($target) && ! empty($target['id'])) {
            $result = $api->careerTargetTasks((string) $target['id']);
            $body = $result['body'] ?? [];
            $tasks = is_array($body) && array_is_list($body) ? $body : ($body['tasks'] ?? []);
        }

        return $this->panelView('app.learning', [
            'learningResources' => $this->resources(is_array($tasks) ? $tasks : []),
            'selectedTarget' => $target,
        ]);
    }

    /** @param list<array<string, mixed>> $tasks */
    private function resources(array $tasks): array
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
