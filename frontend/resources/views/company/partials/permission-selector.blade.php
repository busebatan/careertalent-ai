@php
    $permissionSections = [
        'general' => [
            'label' => __('company.nav.general'),
            'modules' => [
                'dashboard' => [
                    'label' => __('company.nav.dashboard'),
                    'permissions' => ['dashboard.view'],
                    'required' => true,
                ],
            ],
        ],
        'recruiting' => [
            'label' => __('company.nav.recruiting'),
            'modules' => [
                'positions' => [
                    'label' => __('company.nav.positions'),
                    'permissions' => ['positions.view', 'positions.write', 'positions.delete'],
                ],
                'ats_config' => [
                    'label' => __('company_positions.ats.nav'),
                    'permissions' => ['ats_config.view', 'ats_config.write'],
                ],
                'applications' => [
                    'label' => __('company.nav.applications'),
                    'permissions' => ['applications.view', 'applications.write'],
                ],
                'assessments' => [
                    'label' => __('company.nav.assessments'),
                    'permissions' => ['assessments.view', 'assessments.write'],
                ],
                'scorecards' => [
                    'label' => __('company.scorecards.title'),
                    'permissions' => ['scorecards.view', 'scorecards.submit'],
                ],
            ],
        ],
        'organization' => [
            'label' => __('company.nav.organization'),
            'modules' => [
                'profile' => [
                    'label' => __('company.nav.profile'),
                    'permissions' => ['organization.update'],
                ],
                'team' => [
                    'label' => __('company.nav.team'),
                    'permissions' => ['members.view', 'members.invite', 'members.manage'],
                ],
            ],
        ],
    ];
    $availablePermissions = array_values($permissionKeys ?? []);
    $selectedPermissionValues = array_values($selectedPermissions ?? []);
    $knownPermissions = [];
@endphp

<div class="space-y-4" data-permission-selector="{{ $permissionSelectorId }}">
    @foreach ($permissionSections as $sectionKey => $section)
        @php
            $sectionModules = [];
            foreach ($section['modules'] as $moduleKey => $module) {
                $modulePermissions = array_values(array_intersect($module['permissions'], $availablePermissions));
                if ($modulePermissions !== []) {
                    $sectionModules[$moduleKey] = ['module' => $module, 'permissions' => $modulePermissions];
                    $knownPermissions = array_merge($knownPermissions, $modulePermissions);
                }
            }
        @endphp
        @continue($sectionModules === [])

        <div class="space-y-2" data-permission-section="{{ $sectionKey }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $section['label'] }}</p>
            @foreach ($sectionModules as $moduleKey => $sectionModule)
                @php
                    $module = $sectionModule['module'];
                    $modulePermissions = $sectionModule['permissions'];
                @endphp

                @if (count($modulePermissions) === 1)
                    @php($permission = $modulePermissions[0])
                    @php($isRequired = (bool) ($module['required'] ?? false))
                    <label class="flex min-h-12 items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition hover:border-emerald-500/40 dark:border-slate-800"
                        data-permission-single="{{ $permission }}">
                        <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                            @checked($isRequired || in_array($permission, $selectedPermissionValues, true))
                            @disabled($isRequired)>
                        @if ($isRequired)<input type="hidden" name="permissions[]" value="{{ $permission }}">@endif
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $module['label'] }}</span>
                        <span class="ml-auto text-xs text-slate-500">{{ $permissionLabels[$permission] ?? $permission }}</span>
                    </label>
                @else
                    @php($moduleId = $permissionSelectorId.'-'.$moduleKey)
                    <details class="group rounded-xl border border-slate-200 transition open:border-emerald-500/40 dark:border-slate-800"
                        data-permission-module="{{ $moduleKey }}">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 px-4 py-3 marker:hidden [&::-webkit-details-marker]:hidden">
                            <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3" data-permission-toggle-label>
                                <input type="checkbox" data-permission-module-toggle aria-controls="{{ $moduleId }}">
                                <span class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $module['label'] }}</span>
                            </label>
                            <span class="text-xs tabular-nums text-slate-500" data-permission-count></span>
                            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        <div id="{{ $moduleId }}" class="grid gap-2 border-t border-slate-200 p-3 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-800" data-permission-options>
                            @foreach ($modulePermissions as $permission)
                                <label class="flex items-start gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-900/60">
                                    <input class="mt-0.5" type="checkbox" name="permissions[]" value="{{ $permission }}"
                                        @checked(in_array($permission, $selectedPermissionValues, true))
                                        data-permission-option>
                                    <span>{{ $permissionLabels[$permission] ?? $permission }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        </div>
    @endforeach

    @foreach (array_values(array_diff($availablePermissions, $knownPermissions)) as $permission)
        <label class="flex min-h-12 items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-800"
            data-permission-single="{{ $permission }}">
            <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissionValues, true))>
            <span>{{ $permissionLabels[$permission] ?? $permission }}</span>
        </label>
    @endforeach
</div>

@once
    <script>
        (() => {
            const initPermissionSelectors = (root = document) => {
                root.querySelectorAll('[data-permission-module]').forEach((module) => {
                    if (module.dataset.permissionReady === 'true') return;

                    const toggle = module.querySelector('[data-permission-module-toggle]');
                    const options = [...module.querySelectorAll('[data-permission-option]')];
                    const count = module.querySelector('[data-permission-count]');
                    if (!toggle || options.length === 0) return;

                    const sync = () => {
                        const selected = options.filter((option) => option.checked).length;
                        toggle.checked = selected === options.length;
                        toggle.indeterminate = selected > 0 && selected < options.length;
                        toggle.setAttribute('aria-checked', toggle.indeterminate ? 'mixed' : String(toggle.checked));
                        if (count) count.textContent = `${selected}/${options.length}`;
                    };

                    toggle.addEventListener('click', (event) => event.stopPropagation());
                    toggle.addEventListener('change', () => {
                        options.forEach((option) => { option.checked = toggle.checked; });
                        sync();
                    });
                    options.forEach((option) => option.addEventListener('change', sync));
                    module.dataset.permissionReady = 'true';
                    sync();
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => initPermissionSelectors(), { once: true });
            } else {
                initPermissionSelectors();
            }
            document.addEventListener('livewire:navigated', () => initPermissionSelectors());
        })();
    </script>
@endonce
