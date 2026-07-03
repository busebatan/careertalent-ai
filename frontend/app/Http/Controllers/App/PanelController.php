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
        ]));
    }
}
