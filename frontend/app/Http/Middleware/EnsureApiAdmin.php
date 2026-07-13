<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth.user', []);
        abort_unless(($user['is_admin'] ?? false) === true, 403);

        return $next($request);
    }
}
