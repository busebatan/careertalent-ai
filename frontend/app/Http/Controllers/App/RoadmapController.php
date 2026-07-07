<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\PanelRoadmapPlanner;
use App\Services\PanelTargetRoleStore;

class RoadmapController extends PanelController
{
    public function show()
    {
        $data = $this->panelApiData('roadmap', [
            'stats' => PanelDemoData::stats(),
            'weekly_tasks' => PanelDemoData::weeklyTasks(),
        ]);

        $plan = PanelRoadmapPlanner::plan($data['stats'], $data['weekly_tasks'], PanelTargetRoleStore::get());

        return $this->panelView('app.roadmap', [
            'stats' => $plan['stats'],
            'roadmapTasks' => $plan['tasks'],
            'selectedTarget' => $plan['target'],
            'tasksStorageKey' => PanelTargetRoleStore::storageKey(),
        ]);
    }
}
