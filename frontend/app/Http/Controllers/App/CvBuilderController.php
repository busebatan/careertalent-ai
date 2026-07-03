<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use Illuminate\Support\Facades\Lang;

class CvBuilderController extends PanelController
{
    public function show()
    {
        return $this->panelView('app.cv-builder', [
            'cvDraft' => PanelDemoData::cvDraft(),
            'cvLabels' => $this->cvLabelsForJs(),
            'skillRadar' => \App\Data\PanelSkillRadarData::analysis(app()->getLocale()),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cvLabelsForJs(): array
    {
        $labels = [];

        foreach (['tr', 'en'] as $locale) {
            $labels[$locale] = Lang::get('panel.cv_builder', [], $locale);
        }

        return $labels;
    }
}
