<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;

class RoadmapController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.roadmap', [
            'stats' => PanelDemoData::stats(),
            'roadmapTasks' => PanelDemoData::weeklyTasks(),
        ]);
    }
}
