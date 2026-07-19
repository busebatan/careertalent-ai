<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    private const PERMISSION_KEYS = [
        'dashboard.view', 'organizations.manage', 'career_data.manage', 'students.view', 'readiness.view',
        'skill_passport.view', 'job_radar.view', 'applications.view', 'interviews.view',
    ];

    /**
     * @var array<string, array{route: string, icon: string}>
     */
    private const MODULE_KEYS = [
        'students' => ['route' => 'admin.students', 'icon' => 'profile'],
        'readiness' => ['route' => 'admin.readiness', 'icon' => 'ladder'],
        'skill-passport' => ['route' => 'admin.skill-passport', 'icon' => 'passport'],
        'job-radar' => ['route' => 'admin.job-radar', 'icon' => 'radar'],
        'applications' => ['route' => 'admin.applications', 'icon' => 'applications'],
        'interviews' => ['route' => 'admin.interviews', 'icon' => 'interview'],
    ];

    /**
     * @var array<string, array{label: string}>
     */
    private const CAREER_DATA_TABS = [
        'roles' => ['label' => 'career-data.tabs.roles'],
        'skills' => ['label' => 'career-data.tabs.skills'],
        'requirements' => ['label' => 'career-data.tabs.requirements'],
        'sources' => ['label' => 'career-data.tabs.sources'],
    ];

    public function dashboard(CareerTalentApiClient $api)
    {
        $response = $api->adminDashboard();
        $data = is_array($response['body']) ? $response['body'] : [];

        return $this->adminView('admin.dashboard', [
            'stats' => $data['stats'] ?? [],
            'recentStudents' => $data['recent_students'] ?? [],
            'modules' => $this->modules($data['module_counts'] ?? []),
            'adminError' => $response['ok'] ? null : $this->apiError($response['error']),
        ], $api);
    }

    public function profile(CareerTalentApiClient $api): View
    {
        $response = $api->adminProfile();

        return $this->adminView('admin.profile', [
            'profile' => $response['ok'] && is_array($response['body']) ? $response['body'] : session('auth.user', []),
            'adminError' => $response['ok'] ? null : $this->apiError($response['error']),
        ], $api);
    }

    public function updateProfile(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email'],
            'current_password' => ['required', 'string', 'min:8', 'max:128'],
            'new_password' => ['nullable', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);
        unset($data['new_password_confirmation']);
        $oldEmail = (string) session('auth.user.email', '');
        $response = $api->updateAdminProfile($data);
        if (! $response['ok']) {
            return back()->withInput($request->except('current_password', 'new_password', 'new_password_confirmation'))
                ->withErrors(['profile' => $response['error'] ?? __('admin.errors.api_unavailable_generic')]);
        }

        $request->session()->put('auth.user', $response['body']);
        if (! empty($data['new_password']) || $data['email'] !== $oldEmail) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('status', __('admin.profile.login_again'));
        }

        return redirect()->route('admin.profile')->with('status', __('admin.profile.saved'));
    }

    public function accounts(CareerTalentApiClient $api): View
    {
        $response = $api->adminAccounts();
        $body = $response['ok'] && is_array($response['body']) ? $response['body'] : [];

        return $this->adminView('admin.accounts', [
            'accounts' => $body['accounts'] ?? [],
            'permissionKeys' => $body['permission_keys'] ?? self::PERMISSION_KEYS,
            'adminError' => $response['ok'] ? null : $this->apiError($response['error']),
        ], $api);
    }

    public function storeAccount(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email'],
            'temporary_password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::PERMISSION_KEYS)],
        ]);
        unset($data['temporary_password_confirmation']);
        $response = $api->createAdminAccount($data);

        return $response['ok']
            ? redirect()->route('admin.accounts')->with('status', __('admin.accounts.created'))
            : back()->withInput($request->except('temporary_password', 'temporary_password_confirmation'))->withErrors(['accounts' => $response['error']]);
    }

    public function updateAccount(Request $request, CareerTalentApiClient $api, int $user): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email'],
            'is_active' => ['required', 'boolean'],
            'temporary_password' => ['nullable', 'string', 'min:8', 'max:128'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::PERMISSION_KEYS)],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $response = $api->updateAdminAccount($user, $data);

        return $response['ok']
            ? redirect()->route('admin.accounts')->with('status', __('admin.accounts.updated'))
            : back()->withErrors(['accounts' => $response['error']]);
    }

    public function organizations(CareerTalentApiClient $api): View
    {
        $response = $api->adminOrganizations();
        $body = $response['ok'] && is_array($response['body']) ? $response['body'] : [];

        return $this->adminView('admin.organizations', [
            'organizations' => $body['organizations'] ?? [],
            'organizationsTotal' => $body['total'] ?? 0,
            'adminError' => $response['ok'] ? null : $this->apiError($response['error']),
        ], $api);
    }

    public function storeOrganization(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $owner = $request->validate(['owner_email' => ['required', 'email']]);
        $response = $api->createAdminOrganization($this->organizationPayload($request, true));
        if (! $response['ok']) {
            return back()->withInput()->withErrors(['organizations' => $response['error']]);
        }
        $invitation = $api->inviteAdminOrganizationOwner($response['body']['id'], $owner['owner_email']);
        if (! $invitation['ok']) {
            return redirect()->route('admin.organizations')->withErrors(['organizations' => __('admin.organizations.created_invite_failed')]);
        }

        return redirect()->route('admin.organizations')->with('status', __('admin.organizations.created'))
            ->with('company_invite_url', route('company.invitation', $invitation['body']['token']));
    }

    public function updateOrganization(
        Request $request,
        CareerTalentApiClient $api,
        string $organization,
    ): RedirectResponse {
        $response = $api->updateAdminOrganization($organization, $this->organizationPayload($request));

        return $response['ok']
            ? redirect()->route('admin.organizations')->with('status', __('admin.organizations.updated'))
            : back()->withErrors(['organizations' => $response['error']]);
    }

    public function inviteOrganizationOwner(
        Request $request,
        CareerTalentApiClient $api,
        string $organization,
    ): RedirectResponse {
        $data = $request->validate(['owner_email' => ['required', 'email']]);
        $response = $api->inviteAdminOrganizationOwner($organization, $data['owner_email']);

        return $response['ok']
            ? redirect()->route('admin.organizations')
                ->with('status', __('admin.organizations.owner_invited'))
                ->with('company_invite_url', route('company.invitation', $response['body']['token']))
            : back()->withInput()->withErrors(['organizations' => $response['error']]);
    }

    public function students(CareerTalentApiClient $api)
    {
        return $this->page('students', $api);
    }

    public function readiness(CareerTalentApiClient $api)
    {
        return $this->page('readiness', $api);
    }

    public function skillPassport(CareerTalentApiClient $api)
    {
        return $this->page('skill-passport', $api);
    }

    public function jobRadar(CareerTalentApiClient $api)
    {
        return $this->page('job-radar', $api);
    }

    public function applications(CareerTalentApiClient $api)
    {
        return $this->page('applications', $api);
    }

    public function interviews(CareerTalentApiClient $api)
    {
        return $this->page('interviews', $api);
    }

    public function careerData(Request $request, CareerTalentApiClient $api): View
    {
        $tab = $request->string('tab', 'roles')->toString();
        abort_unless(isset(self::CAREER_DATA_TABS[$tab]), 404);

        $responses = [];
        foreach (array_keys(self::CAREER_DATA_TABS) as $resource) {
            $responses[$resource] = $api->adminCareerData($resource);
        }
        $failed = array_filter($responses, fn (array $response): bool => ! $response['ok']);

        return $this->adminView('admin.career-data', [
            'activeTab' => $tab,
            'tabs' => array_map(
                fn (string $key): array => ['key' => $key, 'label' => __(self::CAREER_DATA_TABS[$key]['label'])],
                array_keys(self::CAREER_DATA_TABS),
            ),
            'records' => array_map(
                fn (array $response): array => $response['ok'] && is_array($response['body']) ? $response['body'] : [],
                $responses,
            ),
            'adminError' => $failed ? $this->apiError(reset($failed)['error']) : null,
        ], $api);
    }

    public function storeCareerData(Request $request, CareerTalentApiClient $api, string $resource): RedirectResponse
    {
        $this->assertCareerDataResource($resource);
        $response = $api->createAdminCareerData($resource, $this->careerDataPayload($request, $resource));

        return $this->careerDataRedirect($resource, $response['ok'] ? null : $this->apiError($response['error']), $request);
    }

    public function updateCareerData(Request $request, CareerTalentApiClient $api, string $resource, int $record): RedirectResponse
    {
        $this->assertCareerDataResource($resource);
        $response = $api->updateAdminCareerData($resource, $record, $this->careerDataPayload($request, $resource));

        return $this->careerDataRedirect($resource, $response['ok'] ? null : $this->apiError($response['error']), $request);
    }

    public function destroyCareerData(Request $request, CareerTalentApiClient $api, string $resource, int $record): RedirectResponse
    {
        $this->assertCareerDataResource($resource);
        $response = $api->deleteAdminCareerData($resource, $record);

        return $this->careerDataRedirect($resource, $response['ok'] ? null : $this->apiError($response['error']), $request);
    }

    private function page(string $key, CareerTalentApiClient $api)
    {
        abort_unless(isset(self::MODULE_KEYS[$key]), 404);

        $module = $this->moduleDefinition($key);
        $response = $api->adminModule($key);
        $data = is_array($response['body']) ? $response['body'] : [];

        return $this->adminView('admin.page', [
            'page' => [
                'title' => $data['title'] ?? $module['title'],
                'subtitle' => $data['subtitle'] ?? $module['description'],
                'total' => $data['total'] ?? 0,
                'rows' => $data['rows'] ?? [],
            ],
            'adminError' => $response['ok'] ? null : $this->apiError($response['error']),
        ], $api);
    }

    /**
     * @return list<array{key: string, title: string, description: string, route: string, icon: string, count: int}>
     */
    private function modules(mixed $counts): array
    {
        $counts = is_array($counts) ? $counts : [];

        $keys = array_values(array_filter(array_keys(self::MODULE_KEYS), fn (string $key): bool => array_key_exists($key, $counts)));

        return array_map(function (string $key) use ($counts): array {
            $module = $this->moduleDefinition($key);

            return [
                'key' => $key,
                ...$module,
                'count' => is_int($counts[$key] ?? null) ? $counts[$key] : 0,
            ];
        }, $keys);
    }

    /**
     * @return array{title: string, description: string, route: string, icon: string}
     */
    private function moduleDefinition(string $key): array
    {
        $meta = self::MODULE_KEYS[$key];

        return [
            'title' => __("admin.modules.{$key}.title"),
            'description' => __("admin.modules.{$key}.description"),
            'route' => $meta['route'],
            'icon' => $meta['icon'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function adminView(string $view, array $data, CareerTalentApiClient $api)
    {
        $adminUser = session('auth.user', []);

        return view($view, array_merge($data, [
            'apiHealth' => $api->health(),
            'adminNav' => $this->adminNav(),
            'adminUser' => $adminUser,
            'isSuperAdmin' => ($adminUser['role'] ?? null) === 'super_admin' || ! array_key_exists('role', $adminUser),
        ]));
    }

    /**
     * @return list<array{route: string, label: string, icon: string}>
     */
    private function adminNav(): array
    {
        $user = session('auth.user', []);
        $super = ($user['role'] ?? null) === 'super_admin' || ! array_key_exists('role', $user);
        $permissions = $super ? self::PERMISSION_KEYS : ($user['admin_permissions'] ?? []);
        $item = fn (string $route, string $label, string $icon, string $permission): ?array => in_array($permission, $permissions, true)
            ? compact('route', 'label', 'icon', 'permission') : null;

        $groups = [
            ['label' => __('admin.nav_groups.general'), 'items' => array_filter([
                $item('admin.dashboard', __('admin.nav.dashboard'), 'dashboard', 'dashboard.view'),
                $super ? ['route' => 'admin.accounts', 'label' => __('admin.nav.accounts'), 'icon' => 'admins'] : null,
            ])],
            ['label' => __('admin.nav_groups.organizations'), 'items' => array_filter([
                $item('admin.organizations', __('admin.nav.organizations'), 'organizations', 'organizations.manage'),
            ])],
            ['label' => __('admin.nav_groups.data'), 'items' => array_filter([
                $item('admin.career-data', __('career-data.title'), 'radar', 'career_data.manage'),
            ])],
            ['label' => __('admin.nav_groups.students'), 'items' => array_filter(array_map(
                fn (string $key): ?array => in_array($key, ['students', 'readiness', 'skill-passport'], true)
                    ? $item(self::MODULE_KEYS[$key]['route'], __("admin.modules.{$key}.title"), self::MODULE_KEYS[$key]['icon'], str_replace('-', '_', $key).'.view') : null,
                array_keys(self::MODULE_KEYS),
            ))],
            ['label' => __('admin.nav_groups.operations'), 'items' => array_filter(array_map(
                fn (string $key): ?array => in_array($key, ['job-radar', 'applications', 'interviews'], true)
                    ? $item(self::MODULE_KEYS[$key]['route'], __("admin.modules.{$key}.title"), self::MODULE_KEYS[$key]['icon'], str_replace('-', '_', $key).'.view') : null,
                array_keys(self::MODULE_KEYS),
            ))],
        ];

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    private function apiError(?string $error): string
    {
        return $error
            ? __('admin.errors.api_unavailable', ['error' => $error])
            : __('admin.errors.api_unavailable_generic');
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationPayload(Request $request, bool $creating = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'organization_type' => ['required', 'in:employer,agency'],
            'size_band' => ['required', 'in:smb,mid_market,enterprise'],
            'status' => ['required', 'in:onboarding,active,suspended,closed'],
            'plan_code' => ['required', 'in:pilot,starter,growth,agency,enterprise'],
            'billing_email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo_url' => ['nullable', 'url:https', 'max:2048'],
        ];
        if (! $creating) {
            $rules['slug'] = ['required', 'string', 'min:2', 'max:100', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'];
        }

        return $request->validate($rules);
    }

    private function assertCareerDataResource(string $resource): void
    {
        abort_unless(isset(self::CAREER_DATA_TABS[$resource]), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function careerDataPayload(Request $request, string $resource): array
    {
        return match ($resource) {
            'roles' => $request->validate([
                'slug' => ['required', 'string', 'min:2', 'max:100'],
                'title' => ['required', 'string', 'min:2', 'max:255'],
                'description' => ['nullable', 'string', 'max:10000'],
                'weeks_template' => ['required', 'integer', 'min:1', 'max:104'],
            ]),
            'skills' => array_merge($request->validate([
                'slug' => ['required', 'string', 'min:2', 'max:120'],
                'name' => ['required', 'string', 'min:2', 'max:160'],
                'skill_type' => ['required', 'in:technical,domain,soft,tool'],
                'description' => ['nullable', 'string', 'max:10000'],
            ]), ['is_active' => $request->boolean('is_active')]),
            'sources' => $request->validate([
                'slug' => ['required', 'string', 'min:2', 'max:120'],
                'name' => ['required', 'string', 'min:2', 'max:180'],
                'source_type' => ['required', 'in:official,report,market,manual'],
                'url' => ['nullable', 'string', 'max:2048'],
                'reference_uri' => ['nullable', 'string', 'max:1024'],
                'version' => ['nullable', 'string', 'max:80'],
                'checksum_sha256' => ['nullable', 'string', 'size:64'],
                'license' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:10000'],
                'status' => ['required', 'in:active,archived'],
            ]),
            'requirements' => $request->validate([
                'career_role_id' => ['required', 'integer', 'min:1'],
                'career_skill_id' => ['required', 'integer', 'min:1'],
                'data_source_id' => ['nullable', 'integer', 'min:1'],
                'requirement_type' => ['required', 'in:required,preferred'],
                'expected_level' => ['required', 'in:basic,intermediate,advanced,expert'],
                'weight' => ['required', 'integer', 'min:1', 'max:100'],
                'notes' => ['nullable', 'string', 'max:10000'],
            ]),
        };
    }

    private function careerDataRedirect(string $resource, ?string $error, Request $request): RedirectResponse
    {
        $redirect = redirect()->route('admin.career-data', ['tab' => $resource]);

        return $error ? $redirect->withErrors(['career_data' => $error])->withInput() : $redirect;
    }
}
