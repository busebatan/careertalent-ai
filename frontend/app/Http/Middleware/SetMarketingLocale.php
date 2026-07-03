<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetMarketingLocale
{
    /** @var list<string> */
    private const LOCALES = ['tr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('marketing_locale', config('app.locale', 'tr'));

        if (in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
