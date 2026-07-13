<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\CareerTalentApiClient;
use App\Services\PanelTargetRoleStore;
use App\Services\SkillPassportBuilder;

class StudentFeaturesController extends PanelController
{
    public function skillPassport(CareerTalentApiClient $api, SkillPassportBuilder $builder)
    {
        $analysisResult = $api->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];
        $target = PanelTargetRoleStore::get();
        $tasks = [];
        $taskError = null;

        if (is_array($target) && ! empty($target['id'])) {
            $result = $api->careerTargetTasks((string) $target['id']);
            $taskBody = $result['body'] ?? [];
            $tasks = is_array($taskBody) && array_is_list($taskBody) ? $taskBody : ($taskBody['tasks'] ?? []);
            $taskError = ($result['ok'] ?? false) ? null : ($result['error'] ?? null);
        }

        return $this->panelView('app.skill-passport', [
            'passport' => $builder->build($analysis, is_array($tasks) ? $tasks : []),
            'selectedTarget' => $target,
            'careerEngineError' => $taskError,
        ]);
    }

    public function interview()
    {
        $data = $this->panelApiData('interview', [
            'interview' => PanelDemoData::interviewSimulator(),
        ]);

        return $this->panelView('app.interview', [
            'interview' => $data['interview'],
        ]);
    }

    public function applications()
    {
        $data = $this->panelApiData('applications', [
            'applications' => PanelDemoData::applicationTracker(),
        ]);

        return $this->panelView('app.applications', [
            'applications' => $data['applications'],
        ]);
    }

    public function jobRadar()
    {
        $data = $this->panelApiData('job-radar', [
            'radar' => PanelDemoData::jobRadar(),
        ]);

        return $this->panelView('app.job-radar', [
            'radar' => $data['radar'],
        ]);
    }

    public function mentors()
    {
        $data = $this->panelApiData('mentors', [
            'mentors' => PanelDemoData::mentorMarketplace(),
        ]);

        return $this->panelView('app.mentors', [
            'mentors' => $data['mentors'],
        ]);
    }
}
