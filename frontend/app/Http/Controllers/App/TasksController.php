<?php

namespace App\Http\Controllers\App;

use App\Services\PanelTargetRoleStore;
use App\Services\CareerTalentApiClient;
use App\Services\TaskReadinessCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TasksController extends PanelController
{
    public function show()
    {
        $target = PanelTargetRoleStore::get();
        $tasks = [];
        $taskError = null;
        if (is_array($target) && ! empty($target['id'])) {
            $result = app(CareerTalentApiClient::class)->careerTargetTasks((string) $target['id']);
            $tasks = $result['body']['tasks'] ?? ($result['body'] ?? []);
            $taskError = ($result['ok'] ?? false) ? null : ($result['error'] ?? 'Görev API’si kullanılamıyor.');
        }
        $personalResult = app(CareerTalentApiClient::class)->personalTasks(is_array($target) ? ($target['id'] ?? null) : null);
        $personalTasks = ($personalResult['ok'] ?? false) && is_array($personalResult['body'] ?? null) ? $personalResult['body'] : [];
        $weeklyTasks = is_array($tasks) ? $tasks : [];
        $allTasks = array_merge($weeklyTasks, $personalTasks);
        $analysisResult = app(CareerTalentApiClient::class)->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : null;
        $readinessStats = TaskReadinessCalculator::summary($allTasks, $target, $analysis);
        $isPlanPending = is_array($target) && in_array($target['status'] ?? null, ['queued', 'running'], true);

        return $this->panelView('app.tasks', [
            'weeklyTasks' => $weeklyTasks,
            'personalTasks' => $personalTasks,
            'stats' => [
                'career' => (string) ($target['title'] ?? ''),
                'readiness' => $readinessStats['readiness'],
                'baseline' => $readinessStats['baseline'],
                'target_ready' => $readinessStats['target_ready'],
                'done' => $readinessStats['done'],
                'total' => $readinessStats['total'],
            ],
            'selectedTarget' => $target,
            'taskStorageKey' => PanelTargetRoleStore::storageKey(),
            'careerEngineError' => $taskError,
            'isPlanPending' => $isPlanPending,
        ]);
    }

    public function createPersonal(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate(['title' => ['required', 'string', 'min:2', 'max:240'], 'target_id' => ['nullable', 'string', 'max:36']]);
        return $this->apiJson($api->createPersonalTask($validated));
    }

    public function updatePersonal(Request $request, string $taskId, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate(['title' => ['nullable', 'string', 'min:2', 'max:240'], 'note' => ['nullable', 'string', 'max:4000'], 'completed' => ['nullable', 'boolean']]);
        return $this->apiJson($api->updatePersonalTask($taskId, $validated));
    }

    public function updateNote(Request $request, string $taskId, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:4000']]);
        return $this->apiJson($api->updateCareerTaskNote($taskId, $validated['note'] ?? null));
    }

    public function updateStatus(Request $request, string $taskId, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:pending,completed']]);
        return $this->apiJson($api->updateCareerTaskStatus($taskId, $validated['status']));
    }

    public function deletePersonal(string $taskId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiJson($api->deletePersonalTask($taskId));
    }

    private function apiJson(array $result): JsonResponse
    {
        return ($result['ok'] ?? false) ? response()->json($result['body'] ?? [], $result['status'] ?: 200) : response()->json(['message' => $result['error'] ?? 'İşlem tamamlanamadı'], $result['status'] ?? 502);
    }

    public function submitEvidence(Request $request, string $taskId, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:link,file'],
            'url' => ['nullable', 'url', 'max:2048'],
            'evidence_file' => ['nullable', 'file', 'max:10240'],
        ]);
        if ($validated['kind'] === 'file') {
            $file = $request->file('evidence_file');
            abort_unless($file, 422, 'Private kanıt dosyası gerekli.');
            $result = $api->submitCareerEvidenceFile($taskId, $file);
        } else {
            $result = $api->submitCareerEvidence($taskId, ['kind' => 'link', 'url' => $validated['url'] ?? null]);
        }
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['error'] ?? 'Kanıt gönderilemedi'], $result['status'] ?? 502);
        }
        return response()->json($result['body'] ?? [], $result['status'] ?: 200);
    }

    public function status(string $taskId, CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->careerTask($taskId);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['error'] ?? 'Görev durumu alınamadı'], $result['status'] ?? 502);
        }
        if (is_array($result['body'] ?? null)) {
            return response()->json($result['body']);
        }
        abort(404);
    }
}
