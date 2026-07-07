<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\PanelRoadmapPlanner;
use App\Services\PanelTargetRoleStore;

class TasksController extends PanelController
{
    public function show()
    {
        $data = $this->panelApiData('tasks', [
            'stats' => PanelDemoData::stats(),
            'weekly_tasks' => PanelDemoData::weeklyTasks(),
        ]);

        $plan = PanelRoadmapPlanner::plan($data['stats'], $data['weekly_tasks'], PanelTargetRoleStore::get());

        return $this->panelView('app.tasks', [
            'weeklyTasks' => $plan['tasks'],
            'stats' => $plan['stats'],
            'selectedTarget' => $plan['target'],
            'tasksStorageKey' => PanelTargetRoleStore::storageKey(),
        ]);
    }
}
