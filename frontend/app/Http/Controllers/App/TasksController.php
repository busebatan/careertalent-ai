<?php

namespace App\Http\Controllers\App;

use App\Services\PanelTargetRoleStore;
use App\Services\CareerTalentApiClient;
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

        return $this->panelView('app.tasks', [
            'weeklyTasks' => is_array($tasks) ? $tasks : [],
            'stats' => ['career' => (string) ($target['title'] ?? ''), 'readiness' => (int) ($target['readiness'] ?? 0)],
            'selectedTarget' => $target,
            'careerEngineError' => $taskError,
        ]);
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
