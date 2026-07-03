<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;

class LearningController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.learning', [
            'learningResources' => PanelDemoData::learningResources(),
        ]);
    }
}
