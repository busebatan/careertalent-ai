<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAdmin
{
    private const ROUTE_PERMISSIONS = [
        'admin.dashboard' => 'dashboard.view',
        'admin.career-data*' => 'career_data.manage',
        'admin.students' => 'students.view',
        'admin.readiness' => 'readiness.view',
        'admin.skill-passport' => 'skill_passport.view',
        'admin.job-radar' => 'job_radar.view',
        'admin.applications' => 'applications.view',
        'admin.interviews' => 'interviews.view',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth.user', []);
        abort_unless(($user['is_admin'] ?? false) === true, 403);

        if (($user['must_change_password'] ?? false) === true && ! $request->routeIs('admin.profile', 'admin.profile.update')) {
            return redirect()->route('admin.profile');
        }

        $role = (string) ($user['role'] ?? '');
        $isSuperAdmin = $role === 'super_admin' || ($role === '' && ($user['is_admin'] ?? false) === true);
        if ($request->routeIs('admin.accounts*')) {
            abort_unless($isSuperAdmin, 403);
        }

        if (! $isSuperAdmin) {
            $permissions = is_array($user['admin_permissions'] ?? null) ? $user['admin_permissions'] : [];
            foreach (self::ROUTE_PERMISSIONS as $route => $permission) {
                if ($request->routeIs($route)) {
                    abort_unless(in_array($permission, $permissions, true), 403);
                    break;
                }
            }
        }

        return $next($request);
    }
}
