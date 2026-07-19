<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiCandidate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth.user', []);
        abort_if(($user['role'] ?? null) === 'company', 403);

        return $next($request);
    }
}
