<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\PanelLearningPlanner;
use App\Services\PanelTargetRoleStore;

class LearningController extends PanelController
{
    public function show()
    {
        $data = $this->panelApiData('learning', [
            'learning_resources' => PanelDemoData::learningResources(),
        ]);

        $target = PanelTargetRoleStore::get();

        return $this->panelView('app.learning', [
            'learningResources' => PanelLearningPlanner::resources($data['learning_resources'], $target),
            'selectedTarget' => $target,
        ]);
    }
}
