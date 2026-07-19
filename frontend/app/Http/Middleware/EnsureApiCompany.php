<?php

namespace App\Http\Middleware;

use App\Services\CareerTalentApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiCompany
{
    public function __construct(private readonly CareerTalentApiClient $api) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth.user', []);
        abort_unless(($user['role'] ?? null) === 'company' && ($user['is_admin'] ?? false) === false, 403);

        $context = $this->api->companyContext();
        abort_unless($context['ok'], 403);
        $memberships = is_array($context['body']['memberships'] ?? null) ? $context['body']['memberships'] : [];
        abort_if($memberships === [], 403, 'Aktif kurum üyeliği bulunamadı.');

        $activeId = (string) $request->session()->get('company.organization_id', '');
        $active = collect($memberships)->firstWhere('organization_id', $activeId) ?? $memberships[0];
        $request->session()->put('company.organization_id', $active['organization_id']);
        $request->session()->put('company.memberships', $memberships);
        $request->attributes->set('company.membership', $active);

        return $next($request);
    }
}
