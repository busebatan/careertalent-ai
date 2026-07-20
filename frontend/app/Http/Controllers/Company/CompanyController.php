<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const PERMISSION_KEYS = [
        'dashboard.view',
        'positions.view',
        'positions.write',
        'positions.delete',
        'applications.view',
        'applications.write',
        'assessments.view',
        'assessments.write',
        'scorecards.view',
        'scorecards.submit',
        'organization.update',
        'members.view',
        'members.invite',
        'members.manage',
    ];

    public function dashboard(Request $request, CareerTalentApiClient $api): View
    {
        $period = in_array($request->query('period'), ['7d', '30d', '90d'], true)
            ? $request->query('period')
            : '30d';
        $result = $api->companyDashboard($this->organizationId($request), $period);

        return $this->view('company.dashboard', $request, [
            'dashboard' => $result['ok'] ? $result['body'] : null,
            'companyError' => $result['ok'] ? null : $result['error'],
            'dashboardPeriod' => $period,
        ]);
    }

    public function positions(Request $request, CareerTalentApiClient $api): View
    {
        $status = in_array($request->query('status'), ['draft', 'open', 'paused', 'closed', 'archived'], true)
            ? $request->query('status')
            : null;
        $result = $api->companyPositions($this->organizationId($request), $status);

        return $this->view('company.positions', $request, [
            'positions' => $result['ok'] ? ($result['body']['items'] ?? []) : [],
            'companyError' => $result['ok'] ? null : $result['error'],
            'positionStatus' => $status,
        ]);
    }

    public function createPosition(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->createCompanyPosition($this->organizationId($request), $this->positionPayload($request, true));

        return $result['ok']
            ? redirect()->route('company.positions')->with('status', __('company.positions.created'))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function updatePosition(Request $request, string $position, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->updateCompanyPosition($this->organizationId($request), $position, $this->positionPayload($request, false));

        return $result['ok']
            ? redirect()->route('company.positions')->with('status', __('company.positions.updated'))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function deletePosition(Request $request, string $position, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->deleteCompanyPosition($this->organizationId($request), $position);

        return $result['ok']
            ? redirect()->route('company.positions')->with('status', __('company.positions.archived'))
            : back()->withErrors(['company' => $result['error']]);
    }

    public function applications(Request $request, CareerTalentApiClient $api): View
    {
        return $this->applicationsView($request, $api);
    }

    public function positionApplications(Request $request, string $position, CareerTalentApiClient $api): View
    {
        return $this->applicationsView($request, $api, $position);
    }

    public function assessments(Request $request, CareerTalentApiClient $api): View
    {
        $result = $api->companyAssessments($this->organizationId($request));

        return $this->view('company.assessments', $request, [
            'assessments' => $result['ok'] ? ($result['body']['items'] ?? []) : [],
            'assessmentUsage' => $result['ok'] ? ($result['body']['usage'] ?? ['used' => 0, 'quota' => null]) : ['used' => 0, 'quota' => null],
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

    /** @return array<string, mixed> */
    private function positionPayload(Request $request, bool $creating): array
    {
        $payload = $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:160'],
            'department' => ['nullable', 'string', 'max:120'],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'workplace_type' => ['nullable', Rule::in(['onsite', 'hybrid', 'remote'])],
            'description' => ['nullable', 'string', 'max:10000'],
            'application_deadline' => ['nullable', 'date'],
            'status' => [$creating ? 'required' : 'sometimes', Rule::in(['draft', 'open', 'paused', 'closed'])],
        ]);
        foreach (['department', 'employment_type', 'workplace_type', 'description', 'application_deadline'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] === '') {
                $payload[$field] = null;
            }
        }
        if (! empty($payload['application_deadline'])) {
            $payload['application_deadline'] = Carbon::parse($payload['application_deadline'])->toIso8601String();
        }

        return $payload;
    }

    private function applicationsView(Request $request, CareerTalentApiClient $api, ?string $position = null): View
    {
        $filters = array_filter([
            'queue' => in_array($request->query('queue'), ['new', 'assessment_pending', 'technical_review', 'scorecard_missing', 'retention_due'], true) ? $request->query('queue') : null,
            'stage' => is_string($request->query('stage')) ? $request->query('stage') : null,
            'position_id' => $position,
        ], fn ($value): bool => is_string($value) && $value !== '');
        $result = $api->companyApplications($this->organizationId($request), $filters);

        return $this->view('company.applications', $request, [
            'applications' => $result['ok'] ? ($result['body']['items'] ?? []) : [],
            'applicationFilters' => $filters,
            'companyError' => $result['ok'] ? null : $result['error'],
        ]);
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
                'label' => __('company.nav.recruiting'),
                'items' => array_filter([
                    $item('company.positions', __('company.nav.positions'), 'jobs', 'positions.view'),
                    $item('company.applications', __('company.nav.applications'), 'applications', 'applications.view'),
                    $item('company.assessments', __('company.nav.assessments'), 'tasks', 'assessments.view'),
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
