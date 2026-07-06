<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Http\Controllers\Controller;

abstract class PanelController extends Controller
{
    protected function panelView(string $view, array $data = [])
    {
        return view($view, array_merge($data, [
            'apiHealth' => [
                'ok' => true,
                'status' => null,
                'body' => ['mode' => 'demo'],
                'error' => null,
            ],
            'apiUrl' => 'demo://panel',
            'panelUser' => PanelDemoData::panelUser(),
        ]));
    }
}
