<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;

class ProfileController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.profile', [
            'profile' => PanelDemoData::profile(),
        ]);
    }
}
