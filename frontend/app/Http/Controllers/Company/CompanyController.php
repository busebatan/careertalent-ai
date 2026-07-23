<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
        'ats_config.view',
        'ats_config.write',
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
        $organizationId = $this->organizationId($request);
        $active = $api->companyPositions($organizationId, ['page' => 1, 'page_size' => 100]);
        $archived = $api->companyPositions($organizationId, ['status' => 'archived', 'page' => 1, 'page_size' => 100]);

        $activeBody = $active['ok'] ? $active['body'] : [];
        $archivedBody = $archived['ok'] ? $archived['body'] : [];
        $positions = collect(array_merge($activeBody['items'] ?? [], $archivedBody['items'] ?? []))
            ->unique('id')
            ->values()
            ->all();

        return $this->view('company.positions', $request, [
            'positions' => $positions,
            'companyError' => $active['ok'] && $archived['ok'] ? null : ($active['error'] ?? $archived['error']),
            'positionStatusCounts' => $activeBody['status_counts'] ?? [],
        ]);
    }

    public function newPosition(Request $request, CareerTalentApiClient $api): View
    {
        $organizationId = $this->organizationId($request);
        $members = $api->companyMembers($organizationId);
        $ats = $api->companyAtsConfig($organizationId);

        return $this->view('company.positions.create', $request, [
            'members' => $members['ok'] ? ($members['body']['members'] ?? []) : [],
            'atsConfig' => $ats['ok'] ? $ats['body'] : ['terms' => [], 'notes' => null],
            'companyError' => $members['ok'] && $ats['ok'] ? null : ($members['error'] ?? $ats['error']),
        ]);
    }

    public function position(Request $request, CareerTalentApiClient $api): View
    {
        $position = (string) $request->route('position');
        $permissions = $request->attributes->get('company.membership')['permissions'] ?? [];
        $tabs = ['overview', 'requirements'];
        if (in_array('applications.view', $permissions, true)) {
            $tabs[] = 'applications';
        }
        if (in_array('assessments.view', $permissions, true)) {
            $tabs[] = 'assessment';
        }
        if (in_array('applications.view', $permissions, true)) {
            $tabs[] = 'comparison';
        }
        array_push($tabs, 'activity', 'questions', 'share', 'settings');
        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'overview';
        $result = $api->companyPosition($this->organizationId($request), $position);
        abort_unless($result['ok'], $result['status'] === 404 ? 404 : 503, $result['error']);

        return $this->view('company.positions.show', $request, [
            'positionDetail' => $result['body'],
            'positionTab' => $tab,
            'positionTabs' => $tabs,
            'companyError' => null,
        ]);
    }

    public function createPosition(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->createCompanyPosition($this->organizationId($request), $this->positionPayload($request, true));

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['company' => $result['error']]);
        }

        $positionId = $result['body']['id'] ?? $result['body']['position']['id'] ?? null;

        return is_string($positionId)
            ? redirect()->route('company.positions.show', ['position' => $positionId])->with('status', __('company_positions.feedback.created'))
            : redirect()->route('company.positions')->with('status', __('company_positions.feedback.created'));
    }

    public function updatePosition(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $position = (string) $request->route('position');
        $result = $api->updateCompanyPosition($this->organizationId($request), $position, $this->positionPayload($request, false));

        return $result['ok']
            ? redirect()->route('company.positions.show', ['position' => $position])->with('status', __('company_positions.feedback.updated'))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function ats(Request $request, CareerTalentApiClient $api): View
    {
        $result = $api->companyAtsConfig($this->organizationId($request));

        return $this->view('company.ats', $request, [
            'atsConfig' => $result['ok'] ? $result['body'] : ['terms' => [], 'notes' => null],
            'companyError' => $result['ok'] ? null : $result['error'],
        ]);
    }

    public function updateAts(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'provider' => ['required', Rule::in(['generic', 'greenhouse', 'lever', 'workable', 'sap_successfactors', 'teamtailor', 'custom'])],
            'system_name' => ['nullable', 'string', 'max:120'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'candidate_analysis_instructions' => ['nullable', 'string', 'max:20000'],
        ]);
        $result = $api->updateCompanyAtsConfig($this->organizationId($request), [
            'provider' => $payload['provider'],
            'system_name' => $payload['system_name'] ?? null,
            'terms' => $this->termList($payload['terms'] ?? ''),
            'notes' => $payload['notes'] ?? null,
            'candidate_analysis_instructions' => $payload['candidate_analysis_instructions'] ?? null,
        ]);

        return $result['ok']
            ? back()->with('status', __('company_positions.feedback.ats_updated'))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function analyzePosition(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $position = (string) $request->route('position');
        $result = $api->analyzeCompanyPosition($this->organizationId($request), $position);

        return $result['ok']
            ? back()->with('status', __('company_positions.feedback.analysis_queued'))
            : back()->withErrors(['company' => $result['error']]);
    }

    public function positionAnalysisStatus(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->companyPositionAiAnalysis(
            $this->organizationId($request),
            (string) $request->route('position'),
            (string) $request->route('analysis'),
        );

        return response()->json(
            $result['ok'] ? $result['body'] : ['detail' => $result['error']],
            $result['ok'] ? 200 : max(400, (int) $result['status']),
        );
    }

    public function updatePositionCriteria(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $position = (string) $request->route('position');
        $criteria = (string) $request->route('criteria');
        $payload = $request->validate([
            'criteria_json' => ['required', 'json', 'max:30000'],
        ]);
        $result = $api->updateCompanyPositionCriteria($this->organizationId($request), $position, $criteria, [
            'criteria' => json_decode($payload['criteria_json'], true, flags: JSON_THROW_ON_ERROR),
        ]);

        return $result['ok']
            ? back()->with('status', __('company_positions.feedback.criteria_updated'))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function approvePositionCriteria(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $position = (string) $request->route('position');
        $criteria = (string) $request->route('criteria');
        $result = $api->approveCompanyPositionCriteria($this->organizationId($request), $position, $criteria);

        return $result['ok']
            ? back()->with('status', __('company_positions.feedback.criteria_approved'))
            : back()->withErrors(['company' => $result['error']]);
    }

    public function createShareLink(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $position = (string) $request->route('position');
        $payload = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:160'],
            'channel' => ['required', Rule::in(['linkedin', 'kariyer_net', 'indeed', 'company_website', 'social_media', 'employee_referral', 'agency', 'email', 'other'])],
            'campaign' => ['nullable', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date'],
            'agency_reference' => ['nullable', 'string', 'max:160'],
            'employee_reference' => ['nullable', 'string', 'max:160'],
            'application_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'source_description' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = $api->createCompanyShareLink($this->organizationId($request), $position, array_filter($payload, fn ($value) => $value !== null && $value !== ''));

        return $result['ok']
            ? back()->with('status', __('company_positions.feedback.share_link_created'))
            : back()->withInput()->withErrors(['company' => $result['error']]);
    }

    public function updateShareLink(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $position = (string) $request->route('position');
        $link = (string) $request->route('link');
        $payload = $request->validate(['is_active' => ['required', 'boolean']]);
        $result = $api->updateCompanyShareLink($this->organizationId($request), $position, $link, [
            'is_active' => (bool) $payload['is_active'],
        ]);

        return $result['ok']
            ? back()->with('status', __('company_positions.feedback.share_link_updated'))
            : back()->withErrors(['company' => $result['error']]);
    }

    public function deletePosition(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $position = (string) $request->route('position');
        $result = $api->deleteCompanyPosition($this->organizationId($request), $position);

        return $result['ok']
            ? redirect()->route('company.positions')->with('status', __('company.positions.archived'))
            : back()->withErrors(['company' => $result['error']]);
    }

    public function copyPosition(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->copyCompanyPosition($this->organizationId($request), (string) $request->route('position'));
        if (! $result['ok']) {
            return back()->withErrors(['company' => $result['error']]);
        }
        $positionId = $result['body']['id'] ?? $result['body']['position']['id'] ?? null;

        return is_string($positionId)
            ? redirect()->route('company.positions.show', ['position' => $positionId])->with('status', __('company_positions.feedback.copied'))
            : redirect()->route('company.positions')->with('status', __('company_positions.feedback.copied'));
    }

    public function applications(Request $request, CareerTalentApiClient $api): View
    {
        return $this->applicationsView($request, $api);
    }

    public function positionApplications(Request $request, CareerTalentApiClient $api): View
    {
        $position = (string) $request->route('position');
        return $this->applicationsView($request, $api, $position);
    }

    public function updatePositionApplication(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'stage' => ['nullable', Rule::in(['new', 'assessment_pending', 'assessment_in_progress', 'technical_review', 'shortlisted', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'])],
            'note' => ['nullable', 'string', 'max:5000'],
            'decision' => ['nullable', 'string', 'max:160'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:120'],
        ]);
        if (empty($payload['stage']) && trim((string) ($payload['note'] ?? '')) === '' && trim((string) ($payload['decision'] ?? '')) === '') {
            return back()->withErrors(['application' => 'En az bir aşama, not veya insan kararı girin.']);
        }
        $result = $api->updateCompanyPositionApplication(
            $this->organizationId($request),
            (string) $request->route('position'),
            (string) $request->route('application'),
            array_filter($payload, fn ($value) => $value !== null && $value !== ''),
        );

        return $result['ok']
            ? back()->with('status', __('company_positions.feedback.application_updated'))
            : back()->withInput()->withErrors(['application' => $result['error']]);
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

        return redirect()->route('company.positions', [
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
            'level' => ['nullable', Rule::in(['intern', 'junior', 'mid', 'senior', 'lead', 'manager', 'director'])],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'workplace_type' => ['nullable', Rule::in(['onsite', 'hybrid', 'remote'])],
            'location' => ['nullable', 'string', 'max:160'],
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'salary_max' => ['nullable', 'integer', 'gte:salary_min', 'max:1000000000'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'source_text' => ['nullable', 'string', 'max:30000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'responsibilities' => ['nullable', 'string', 'max:20000'],
            'must_have_skills' => ['nullable', 'string', 'max:10000'],
            'preferred_skills' => ['nullable', 'string', 'max:10000'],
            'learnable_skills' => ['nullable', 'string', 'max:10000'],
            'experience_expectation' => ['nullable', 'string', 'max:10000'],
            'language_work_authorization' => ['nullable', 'string', 'max:10000'],
            'application_deadline' => ['nullable', 'date'],
            'target_start_date' => ['nullable', 'date'],
            'recruiter_membership_id' => ['nullable', 'string', 'max:64'],
            'technical_manager_membership_id' => ['nullable', 'string', 'max:64'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'ats_terms' => ['nullable', 'string', 'max:10000'],
            'ats_notes' => ['nullable', 'string', 'max:20000'],
            'application_form_id' => ['nullable', 'string', 'max:80'],
            'assessment_template_id' => ['nullable', 'string', 'max:80'],
            'estimated_application_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'estimated_assessment_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'assessment_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'assessment_tasks' => ['nullable', 'string', 'max:10000'],
            'allowed_tools' => ['nullable', 'string', 'max:10000'],
            'scoring_rubric' => ['nullable', 'string', 'max:20000'],
            'success_threshold' => ['nullable', 'integer', 'min:0', 'max:100'],
            'human_review_required' => ['nullable', 'boolean'],
            'status' => [$creating ? 'required' : 'sometimes', Rule::in(['draft', 'published', 'paused', 'closed'])],
        ]);
        foreach (['department', 'level', 'employment_type', 'workplace_type', 'location', 'salary_min', 'salary_max', 'salary_currency', 'source_text', 'description', 'responsibilities', 'experience_expectation', 'language_work_authorization', 'application_deadline', 'target_start_date', 'recruiter_membership_id', 'technical_manager_membership_id', 'retention_days', 'ats_notes', 'application_form_id', 'assessment_template_id'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] === '') {
                $payload[$field] = null;
            }
        }
        foreach (['must_have_skills', 'preferred_skills', 'learnable_skills'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->lineList($payload[$field] ?? '');
            }
        }
        if (array_key_exists('ats_terms', $payload)) {
            $payload['ats_terms'] = $this->termList($payload['ats_terms'] ?? '');
        }
        $evaluationConfig = [];
        foreach (['estimated_application_minutes', 'estimated_assessment_minutes'] as $field) {
            if (array_key_exists($field, $payload)) {
                if ($payload[$field] !== null && $payload[$field] !== '') {
                    $evaluationConfig[$field] = (int) $payload[$field];
                }
                unset($payload[$field]);
            }
        }
        foreach (['assessment_duration_minutes' => 'duration_minutes', 'success_threshold' => 'success_threshold'] as $field => $configKey) {
            if (array_key_exists($field, $payload)) {
                if ($payload[$field] !== null && $payload[$field] !== '') {
                    $evaluationConfig[$configKey] = (int) $payload[$field];
                }
                unset($payload[$field]);
            }
        }
        foreach (['assessment_tasks' => 'tasks', 'allowed_tools' => 'allowed_tools'] as $field => $configKey) {
            if (array_key_exists($field, $payload)) {
                $evaluationConfig[$configKey] = $this->lineList($payload[$field] ?? '');
                unset($payload[$field]);
            }
        }
        if (array_key_exists('scoring_rubric', $payload)) {
            $evaluationConfig['rubric'] = $payload['scoring_rubric'] ?: null;
            unset($payload['scoring_rubric']);
        }
        if (array_key_exists('human_review_required', $payload)) {
            $evaluationConfig['human_review_required'] = (bool) $payload['human_review_required'];
            unset($payload['human_review_required']);
        }
        if ($evaluationConfig !== []) {
            $payload['evaluation_config'] = $evaluationConfig;
        }
        foreach (['application_deadline', 'target_start_date'] as $field) {
            if (! empty($payload[$field])) {
                $payload[$field] = Carbon::parse($payload[$field])->toIso8601String();
            }
        }

        return $payload;
    }

    /** @return list<string> */
    private function lineList(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $item): string => trim($item),
            preg_split('/[\r\n,]+/u', $value) ?: [],
        ))));
    }

    /** @return list<string> */
    private function termList(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\r\n|\r|\n/u', $value) ?: [],
        ))));
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
                'label' => '',
                'items' => array_filter([
                    $item('company.dashboard', __('company.nav.home'), 'dashboard', 'dashboard.view'),
                ]),
            ],
            [
                'label' => __('company.nav.recruiting'),
                'items' => array_filter([
                    $item('company.positions', __('company.nav.positions'), 'jobs', 'positions.view'),
                    $item('company.ats', __('company_positions.ats.nav'), 'scan-search', 'ats_config.view'),
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
