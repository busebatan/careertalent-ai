<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;

class TasksController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.tasks', [
            'weeklyTasks' => PanelDemoData::weeklyTasks(),
            'stats' => PanelDemoData::stats(),
        ]);
    }
}
