<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends PanelController
{
    public function show(CareerTalentApiClient $api)
    {
        $result = $api->careerChat();
        $historyResult = $api->careerChatThreads();
        $analysisResult = $api->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];

        return $this->panelView('app.chat', [
            'messages' => ($result['ok'] ?? false) && is_array($result['body'] ?? null) ? $result['body'] : [],
            'chatError' => ($result['ok'] ?? false) ? null : $result['error'],
            'chatThreads' => ($historyResult['ok'] ?? false) && is_array($historyResult['body']['items'] ?? null)
                ? $historyResult['body']['items']
                : [],
            'chatHistoryHasMore' => (bool) (($historyResult['body']['has_more'] ?? false) && ($historyResult['ok'] ?? false)),
            'chatHistoryError' => ($historyResult['ok'] ?? false) ? null : $historyResult['error'],
            'activeCvName' => (string) ($analysis['file_name'] ?? ''),
        ]);
    }

    public function send(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'min:2', 'max:30000']]);
        return $this->apiJson($api->sendCareerChat($validated['message']));
    }

    public function createCvVersion(Request $request, string $jobId, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'suggestion_ids' => ['required', 'array', 'min:1', 'max:20'],
            'suggestion_ids.*' => ['string', 'max:36'],
            'source_cv_version_id' => ['nullable', 'string', 'max:36'],
        ]);

        return $this->apiJson($api->createCareerChatCvVersion(
            $jobId,
            array_values($validated['suggestion_ids']),
            $validated['source_cv_version_id'] ?? null,
        ));
    }

    public function startNew(CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiJson($api->startNewCareerChat());
    }

    public function history(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        return $this->apiJson($api->careerChatThreads(
            (int) ($validated['limit'] ?? 20),
            (int) ($validated['offset'] ?? 0),
        ));
    }

    public function historyDetail(string $threadId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiJson($api->careerChatThread($threadId));
    }

    private function apiJson(array $result): JsonResponse
    {
        return ($result['ok'] ?? false)
            ? response()->json($result['body'] ?? [], $result['status'] ?: 200)
            : response()->json(['message' => $result['error'] ?? 'AI servisine ulaşılamadı'], $result['status'] ?? 502);
    }
}
