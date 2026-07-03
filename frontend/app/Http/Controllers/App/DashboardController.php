<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Data\PanelSkillRadarData;

class DashboardController extends PanelController
{
    public function index()
    {
        return $this->panelView('app.dashboard', [
            'stats' => PanelDemoData::stats(),
            'weeklyTasks' => PanelDemoData::weeklyTasks(),
            'learningResources' => PanelDemoData::learningResources(),
            'skillRadar' => PanelSkillRadarData::analysis(app()->getLocale()),
        ]);
    }
}
