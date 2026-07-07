<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\PanelCvAnalysisStore;
use App\Services\PanelLearningPlanner;
use App\Services\PanelRoadmapPlanner;
use App\Services\PanelTargetRoleStore;

class DashboardController extends PanelController
{
    public function index()
    {
        $hasCvAnalysis = PanelCvAnalysisStore::has();
        $skillRadar = PanelCvAnalysisStore::skillRadar();
        $cvFileName = PanelCvAnalysisStore::fileName();
        $ladder = PanelCvAnalysisStore::careerLadder();
        $data = $this->panelApiData('dashboard', [
            'stats' => PanelDemoData::stats(),
            'weekly_tasks' => PanelDemoData::weeklyTasks(),
            'learning_resources' => PanelDemoData::learningResources(),
        ]);

        $stats = $data['stats'];
        if (is_array($ladder) && isset($ladder[0])) {
            $stats['career'] = (string) ($ladder[0]['title'] ?? $stats['career']);
            $stats['readiness'] = (int) ($ladder[0]['readiness'] ?? $stats['readiness']);
        }

        $plan = PanelRoadmapPlanner::plan($stats, $data['weekly_tasks'], PanelTargetRoleStore::get());

        return $this->panelView('app.dashboard', [
            'stats' => $plan['stats'],
            'weeklyTasks' => $plan['tasks'],
            'learningResources' => PanelLearningPlanner::resources($data['learning_resources'], $plan['target']),
            'skillRadar' => $skillRadar,
            'hasCvAnalysis' => $hasCvAnalysis,
            'cvFileName' => $cvFileName,
            'selectedTarget' => $plan['target'],
            'tasksStorageKey' => PanelTargetRoleStore::storageKey(),
        ]);
    }
}
