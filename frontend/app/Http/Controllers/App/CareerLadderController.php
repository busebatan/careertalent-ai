<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;

class CareerLadderController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.career-ladder', [
            'careerLadder' => PanelDemoData::careerLadder(),
            'careerTierMeta' => PanelDemoData::careerTierMeta(),
        ]);
    }
}
