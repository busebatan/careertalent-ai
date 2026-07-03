<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPanelLocale
{
    /** @var list<string> */
    private const LOCALES = ['tr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('panel_locale', 'tr');

        if (in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
