<?php

namespace App\Http\Middleware;

use App\Services\CareerTalentApiClient;
use App\Support\PortalAuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAuthenticated
{
    public function __construct(private readonly CareerTalentApiClient $api) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sessionKey = PortalAuthSession::keyFor($request);
        $token = PortalAuthSession::token($request);
        $result = null;

        if ($token === null && $sessionKey === PortalAuthSession::COMPANY) {
            [$token, $result] = $this->migrateLegacyCompanySession($request);
        }

        if ($token === null) {
            return redirect()->guest($this->loginRoute($request));
        }

        $result ??= $this->api->me($token);
        if (! $result['ok']) {
            $request->session()->forget($sessionKey);

            return redirect()->guest($this->loginRoute($request));
        }

        $request->session()->put($sessionKey.'.user', $result['body']);
        $preferredLocale = $result['body']['preferred_locale'] ?? 'tr';
        if (in_array($preferredLocale, ['tr', 'en'], true)) {
            $request->session()->put('panel_locale', $preferredLocale);
            app()->setLocale($preferredLocale);
        }
        $request->attributes->set('auth.user', $result['body']);

        return $next($request);
    }

    /** @return array{0: ?string, 1: ?array<string, mixed>} */
    private function migrateLegacyCompanySession(Request $request): array
    {
        $legacyToken = $request->session()->get(PortalAuthSession::DEFAULT.'.access_token');
        if (! is_string($legacyToken) || $legacyToken === '') {
            return [null, null];
        }

        $result = $this->api->me($legacyToken);
        if (! $result['ok']
            || ($result['body']['role'] ?? null) !== 'company'
            || ($result['body']['is_admin'] ?? false) === true) {
            return [null, null];
        }

        $request->session()->put(PortalAuthSession::COMPANY, [
            'access_token' => $legacyToken,
            'user' => $result['body'],
        ]);
        $request->session()->forget(PortalAuthSession::DEFAULT);

        return [$legacyToken, $result];
    }

    private function loginRoute(Request $request): string
    {
        if ($request->routeIs('admin.*')) {
            return route('admin.login');
        }

        if ($request->routeIs('company.*')) {
            return route('company.login');
        }

        return route('login');
    }
}
