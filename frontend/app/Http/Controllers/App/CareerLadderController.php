<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\PanelCvAnalysisStore;

class CareerLadderController extends PanelController
{
    public function show()
    {
        $data = $this->panelApiData('career-ladder', [
            'career_ladder' => PanelDemoData::careerLadder(),
            'career_tier_meta' => PanelDemoData::careerTierMeta(),
        ]);
        $cvLadder = PanelCvAnalysisStore::careerLadder();
        $ladder = $cvLadder ?? $data['career_ladder'];

        return $this->panelView('app.career-ladder', [
            'careerLadder' => $ladder,
            'careerTierMeta' => $data['career_tier_meta'],
            'fromApi' => $cvLadder !== null,
        ]);
    }
}
