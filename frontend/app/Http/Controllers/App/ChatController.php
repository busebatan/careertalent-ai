<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;

class ChatController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.chat', [
            'assistant' => PanelDemoData::chatAssistant(),
        ]);
    }
}
