<?php

namespace App\Http\Middleware;

use App\Services\CareerTalentApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAuthenticated
{
    public function __construct(private readonly CareerTalentApiClient $api) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->session()->get('auth.access_token');
        if (! is_string($token) || $token === '') {
            return redirect()->guest($this->loginRoute($request));
        }

        $result = $this->api->me($token);
        if (! $result['ok']) {
            $request->session()->forget('auth');

            return redirect()->guest($this->loginRoute($request));
        }

        $request->session()->put('auth.user', $result['body']);
        $preferredLocale = $result['body']['preferred_locale'] ?? 'tr';
        if (in_array($preferredLocale, ['tr', 'en'], true)) {
            $request->session()->put('panel_locale', $preferredLocale);
            app()->setLocale($preferredLocale);
        }
        $request->attributes->set('auth.user', $result['body']);

        return $next($request);
    }

    private function loginRoute(Request $request): string
    {
        if ($request->is('admin', 'admin/*') && ! $request->is('admin/login')) {
            return route('admin.login');
        }

        return route('login');
    }
}
