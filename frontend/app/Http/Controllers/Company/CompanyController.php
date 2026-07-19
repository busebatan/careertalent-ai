<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const PERMISSION_KEYS = [
        'dashboard.view',
        'organization.update',
        'members.view',
        'members.invite',
        'members.manage',
    ];

    public function dashboard(Request $request, CareerTalentApiClient $api): View
    {
        $result = $api->companyDashboard($this->organizationId($request));

        return $this->view('company.dashboard', $request, [
            'dashboard' => $result['ok'] ? $result['body'] : null,
            'companyError' => $result['ok'] ? null : $result['error'],
        ]);
    }

    public function profile(Request $request): View
    {
        return $this->view('company.profile', $request);
    }

    public function updateProfile(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'billing_email' => ['required', 'email'],
            'website' => ['nullable', 'url', 'max:2048'],
        ]);
        $result = $api->updateCompanyOrganization($this->organizationId($request), $payload);

        return $result['ok']
            ? back()->with('status', __('company.profile.updated'))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function team(Request $request, CareerTalentApiClient $api): View
    {
        $result = $api->companyMembers($this->organizationId($request));

        return $this->view('company.team', $request, [
            'team' => $result['ok'] ? $result['body'] : [
                'permission_keys' => self::PERMISSION_KEYS,
                'members' => [],
                'pending_invitations' => [],
            ],
            'companyError' => $result['ok'] ? null : $result['error'],
        ]);
    }

    public function invite(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:owner,admin,recruiter,hiring_manager,viewer'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(self::PERMISSION_KEYS)],
        ]);
        $result = $api->inviteCompanyMember($this->organizationId($request), $payload);

        return $result['ok']
            ? back()->with('status', __('company.team.invited'))->with('company_invite_url', route('company.invitation', $result['body']['token']))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function updateMember(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $membership = (string) $request->route('membership');
        $payload = $request->validate([
            'role' => ['required', 'in:owner,admin,recruiter,hiring_manager,viewer'],
            'status' => ['required', 'in:active,suspended'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(self::PERMISSION_KEYS)],
        ]);
        $result = $api->updateCompanyMember($this->organizationId($request), $membership, $payload);

        return $result['ok'] ? back()->with('status', __('company.team.updated')) : back()->withErrors(['company' => $result['error']]);
    }

    public function switchOrganization(Request $request): RedirectResponse
    {
        $organization = (string) $request->route('organization');
        $memberships = $request->session()->get('company.memberships', []);
        $membership = collect($memberships)->firstWhere('organization_id', $organization);
        abort_unless(is_array($membership), 403);
        $request->session()->put('company.organization_id', $organization);

        return redirect()->route('company.dashboard', [
            'organizationSlug' => $membership['organization_slug'],
        ]);
    }

    private function organizationId(Request $request): string
    {
        return (string) $request->attributes->get('company.membership')['organization_id'];
    }

    private function view(string $name, Request $request, array $data = []): View
    {
        return view($name, [
            ...$data,
            'apiHealth' => ['ok' => true],
            'companyMembership' => $request->attributes->get('company.membership'),
            'companyMemberships' => $request->session()->get('company.memberships', []),
            'companyNav' => $this->companyNav($request->attributes->get('company.membership', [])),
            'companyUser' => $request->attributes->get('auth.user', []),
        ]);
    }

    /**
     * @return list<array{label: string, items: list<array{route: string, label: string, icon: string}>}>
     */
    private function companyNav(array $membership): array
    {
        $permissions = is_array($membership['permissions'] ?? null) ? $membership['permissions'] : [];
        $item = fn (string $route, string $label, string $icon, string $permission): ?array => in_array($permission, $permissions, true)
            ? compact('route', 'label', 'icon', 'permission') : null;
        $groups = [
            [
                'label' => __('company.nav.general'),
                'items' => array_filter([
                    $item('company.dashboard', __('company.nav.dashboard'), 'dashboard', 'dashboard.view'),
                ]),
            ],
            [
                'label' => __('company.nav.organization'),
                'items' => array_filter([
                    $item('company.team', __('company.nav.team'), 'admins', 'members.view'),
                    $item('company.profile', __('company.nav.profile'), 'profile', 'organization.update'),
                ]),
            ],
        ];

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }
}
