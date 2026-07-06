<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;

class StudentFeaturesController extends PanelController
{
    public function skillPassport()
    {
        return $this->panelView('app.skill-passport', [
            'passport' => PanelDemoData::skillPassport(),
        ]);
    }

    public function interview()
    {
        return $this->panelView('app.interview', [
            'interview' => PanelDemoData::interviewSimulator(),
        ]);
    }

    public function applications()
    {
        return $this->panelView('app.applications', [
            'applications' => PanelDemoData::applicationTracker(),
        ]);
    }

    public function jobRadar()
    {
        return $this->panelView('app.job-radar', [
            'radar' => PanelDemoData::jobRadar(),
        ]);
    }

    public function mentors()
    {
        return $this->panelView('app.mentors', [
            'mentors' => PanelDemoData::mentorMarketplace(),
        ]);
    }
}
