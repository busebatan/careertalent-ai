<?php

namespace App\Http\Controllers\App;

use Illuminate\Http\RedirectResponse;

class LocaleController extends PanelController
{
    /** @var list<string> */
    private const LOCALES = ['tr', 'en'];

    public function switch(string $locale): RedirectResponse
    {
        if (! in_array($locale, self::LOCALES, true)) {
            abort(404);
        }

        session(['panel_locale' => $locale]);

        return redirect()->back();
    }
}
