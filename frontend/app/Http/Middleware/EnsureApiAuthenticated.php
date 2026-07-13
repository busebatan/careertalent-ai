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
            return redirect()->guest(route('login'));
        }

        $result = $this->api->me($token);
        if (! $result['ok']) {
            $request->session()->forget('auth');

            return redirect()->guest(route('login'));
        }

        $request->session()->put('auth.user', $result['body']);
        $request->attributes->set('auth.user', $result['body']);

        return $next($request);
    }
}
