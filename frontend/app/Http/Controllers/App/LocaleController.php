<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use App\Support\PortalAuthSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends PanelController
{
    /** @var list<string> */
    private const LOCALES = ['tr', 'en'];

    public function switch(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $locale = (string) $request->route('locale');
        if (! in_array($locale, self::LOCALES, true)) {
            abort(404);
        }

        $result = $api->updatePreferredLocale($locale);
        if (! ($result['ok'] ?? false)) {
            return redirect()->back()->with('panel_error', __('panel.header.language_update_failed'));
        }

        $sessionKey = PortalAuthSession::keyFor(request());
        session([
            'panel_locale' => $locale,
            $sessionKey.'.user.preferred_locale' => $locale,
        ]);

        return redirect()->back();
    }
}
