<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /** @var list<string> */
    private const LOCALES = ['tr', 'en'];

    public function switch(string $locale): RedirectResponse
    {
        if (! in_array($locale, self::LOCALES, true)) {
            abort(404);
        }

        session(['marketing_locale' => $locale]);

        return redirect()->back();
    }
}
