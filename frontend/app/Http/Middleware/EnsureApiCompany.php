<?php

namespace App\Http\Middleware;

use App\Services\CareerTalentApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiCompany
{
    private const ROUTE_PERMISSIONS = [
        'company.entry' => 'dashboard.view',
        'company.dashboard' => 'dashboard.view',
        'company.locale' => 'dashboard.view',
        'company.organization.switch' => 'dashboard.view',
        'company.positions' => 'positions.view',
        'company.positions.create' => 'positions.write',
        'company.positions.update' => 'positions.write',
        'company.positions.delete' => 'positions.delete',
        'company.positions.applications' => 'applications.view',
        'company.applications' => 'applications.view',
        'company.assessments' => 'assessments.view',
        'company.profile*' => 'organization.update',
        'company.team' => 'members.view',
        'company.team.invite' => 'members.invite',
        'company.team.update' => 'members.manage',
    ];

    public function __construct(private readonly CareerTalentApiClient $api) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth.user', []);
        if (($user['role'] ?? null) !== 'company' || ($user['is_admin'] ?? false) === true) {
            $request->session()->forget(['company_auth', 'company']);

            return redirect()->route('company.login')->withErrors([
                'email' => __('marketing.auth.company_required'),
            ]);
        }

        $context = $this->api->companyContext();
        $memberships = is_array($context['body']['memberships'] ?? null) ? $context['body']['memberships'] : [];
        if (! $context['ok'] || $memberships === []) {
            $request->session()->forget(['company_auth', 'company']);

            return redirect()->route('company.login')->withErrors([
                'email' => __('marketing.auth.company_membership_required'),
            ]);
        }

        $organizationSlug = (string) $request->route('organizationSlug', '');
        if ($organizationSlug !== '') {
            $active = collect($memberships)->firstWhere('organization_slug', $organizationSlug);
            abort_if(! is_array($active), 404);
        } else {
            $activeId = (string) $request->session()->get('company.organization_id', '');
            $active = collect($memberships)->firstWhere('organization_id', $activeId) ?? $memberships[0];
        }

        $permissions = is_array($active['permissions'] ?? null) ? $active['permissions'] : [];
        foreach (self::ROUTE_PERMISSIONS as $route => $permission) {
            if ($request->routeIs($route)) {
                abort_unless(in_array($permission, $permissions, true), 403);
                break;
            }
        }

        $request->session()->put('company.organization_id', $active['organization_id']);
        $request->session()->put('company.memberships', $memberships);
        $request->attributes->set('company.membership', $active);
        URL::defaults(['organizationSlug' => $active['organization_slug']]);

        return $next($request);
    }
}
