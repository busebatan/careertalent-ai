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

        return $this->panelView('app.chat', [
            'messages' => ($result['ok'] ?? false) && is_array($result['body'] ?? null) ? $result['body'] : [],
            'chatError' => ($result['ok'] ?? false) ? null : $result['error'],
        ]);
    }

    public function send(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'min:2', 'max:4000']]);
        return $this->apiJson($api->sendCareerChat($validated['message']));
    }

    public function clear(CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiJson($api->clearCareerChat());
    }

    private function apiJson(array $result): JsonResponse
    {
        return ($result['ok'] ?? false)
            ? response()->json($result['body'] ?? [], $result['status'] ?: 200)
            : response()->json(['message' => $result['error'] ?? 'AI servisine ulaşılamadı'], $result['status'] ?? 502);
    }
}
