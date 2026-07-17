<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use Illuminate\Http\RedirectResponse;

class LocaleController extends PanelController
{
    /** @var list<string> */
    private const LOCALES = ['tr', 'en'];

    public function switch(string $locale, CareerTalentApiClient $api): RedirectResponse
    {
        if (! in_array($locale, self::LOCALES, true)) {
            abort(404);
        }

        $result = $api->updatePreferredLocale($locale);
        if (! ($result['ok'] ?? false)) {
            return redirect()->back()->with('panel_error', __('panel.header.language_update_failed'));
        }

        session([
            'panel_locale' => $locale,
            'auth.user.preferred_locale' => $locale,
        ]);

        return redirect()->back();
    }
}
