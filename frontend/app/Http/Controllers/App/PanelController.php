<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;

abstract class PanelController extends Controller
{
    protected function panelView(string $view, array $data = [])
    {
        $api = app(CareerTalentApiClient::class);

        return view($view, array_merge($data, [
            'apiHealth' => $api->health(),
            'apiUrl' => $api->baseUrl(),
            'panelUser' => [
                'name' => session('auth.user.full_name', __('panel.account.user_fallback')),
                'avatar_url' => null,
            ],
        ]));
    }

    /**
     * FastAPI panel kaynağı. Fallback yalnız API erişilemezse sayfayı ayakta tutar.
     *
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    protected function panelApiData(string $endpoint, array $fallback = []): array
    {
        $result = app(CareerTalentApiClient::class)->panel($endpoint);

        if ($result['ok'] && is_array($result['body'])) {
            return array_merge($fallback, $result['body'], ['_api_source' => 'fastapi']);
        }

        return array_merge($fallback, [
            '_api_source' => 'fallback',
            '_api_error' => $result['error'] ?? 'API bağlantısı kurulamadı',
        ]);
    }
}
