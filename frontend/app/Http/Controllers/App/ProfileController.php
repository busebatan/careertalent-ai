<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\CareerTalentApiClient;

class ProfileController extends PanelController
{
    public function show(CareerTalentApiClient $api)
    {
        return $this->accountView($api, 'profil');
    }

    public function account(CareerTalentApiClient $api)
    {
        return $this->accountView($api, 'profil');
    }

    private function accountView(CareerTalentApiClient $api, string $initialTab)
    {
        return $this->panelView('app.account', [
            'profile' => PanelDemoData::profile(),
            'initialTab' => $initialTab,
        ]);
    }
}
