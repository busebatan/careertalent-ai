<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAdmin
{
    private const ROUTE_PERMISSIONS = [
        'admin.dashboard' => 'dashboard.view',
        'admin.organizations' => 'organizations.view',
        'admin.organizations.show' => 'organizations.view',
        'admin.organizations.store' => 'organizations.write',
        'admin.organizations.update' => 'organizations.write',
        'admin.organizations.owner-invite' => 'organizations.write',
        'admin.organizations.destroy' => 'organizations.delete',
        'admin.career-data' => 'career_data.view',
        'admin.career-data.store' => 'career_data.write',
        'admin.career-data.update' => 'career_data.write',
        'admin.career-data.destroy' => 'career_data.delete',
        'admin.students' => 'students.view',
        'admin.students.show' => 'students.view',
        'admin.students.store' => 'students.write',
        'admin.students.update' => 'students.write',
        'admin.students.destroy' => 'students.delete',
        'admin.readiness' => 'readiness.view',
        'admin.skill-passport' => 'skill_passport.view',
        'admin.job-radar' => 'job_radar.view',
        'admin.applications' => 'applications.view',
        'admin.applications.store' => 'applications.write',
        'admin.applications.update' => 'applications.write',
        'admin.applications.destroy' => 'applications.delete',
        'admin.interviews' => 'interviews.view',
        'admin.interviews.store' => 'interviews.write',
        'admin.interviews.update' => 'interviews.write',
        'admin.interviews.destroy' => 'interviews.delete',
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
            if (in_array('organizations.manage', $permissions, true)) {
                $permissions = array_merge($permissions, ['organizations.view', 'organizations.write', 'organizations.delete']);
            }
            if (in_array('career_data.manage', $permissions, true)) {
                $permissions = array_merge($permissions, ['career_data.view', 'career_data.write', 'career_data.delete']);
            }
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
