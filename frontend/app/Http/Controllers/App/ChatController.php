<?php

namespace App\Http\Controllers\App;

class ChatController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.chat');
    }
}
