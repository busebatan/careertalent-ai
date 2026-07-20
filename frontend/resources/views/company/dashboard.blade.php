@extends('company.layouts.app')
@section('title', __('company.dashboard.title'))
@section('content')
@php
    $permissions = is_array($companyMembership['permissions'] ?? null) ? $companyMembership['permissions'] : [];
    $indicators = is_array($dashboard['indicators'] ?? null) ? $dashboard['indicators'] : [
        'active_positions' => 0, 'new_applications' => 0, 'assessment_pending' => 0,
        'technical_review_pending' => 0, 'shortlisted' => 0,
        'assessment_usage' => ['used' => 0, 'quota' => null],
    ];
    $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
    $tasks = is_array($dashboard['tasks'] ?? null) ? $dashboard['tasks'] : [];
    $localeDecimal = app()->getLocale() === 'tr' ? ',' : '.';
    $percent = fn ($value) => $value === null ? __('company.dashboard.no_data') : '%'.number_format($value * 100, 0, $localeDecimal, '');
    $shortlistHours = $summary['average_shortlist_hours'] ?? null;
    $shortlistTime = $shortlistHours === null
        ? __('company.dashboard.no_data')
        : ($shortlistHours < 24
            ? __('company.dashboard.hours', ['value' => number_format($shortlistHours, 1, $localeDecimal, '')])
            : __('company.dashboard.days', ['value' => number_format($shortlistHours / 24, 1, $localeDecimal, '')]));
    $lossStage = data_get($summary, 'largest_loss_stage.stage');
    $usage = is_array($indicators['assessment_usage'] ?? null) ? $indicators['assessment_usage'] : ['used' => 0, 'quota' => null];
    $metricCards = [
        ['key' => 'active_positions', 'label' => __('company.dashboard.active_positions'), 'icon' => 'briefcase-business', 'route' => 'company.positions', 'permission' => 'positions.view'],
        ['key' => 'new_applications', 'label' => __('company.dashboard.new_applications'), 'icon' => 'user-plus', 'route' => 'company.applications', 'params' => ['queue' => 'new'], 'permission' => 'applications.view'],
        ['key' => 'assessment_pending', 'label' => __('company.dashboard.assessment_pending'), 'icon' => 'clipboard-clock', 'route' => 'company.applications', 'params' => ['queue' => 'assessment_pending'], 'permission' => 'applications.view'],
        ['key' => 'technical_review_pending', 'label' => __('company.dashboard.technical_review_pending'), 'icon' => 'scan-search', 'route' => 'company.applications', 'params' => ['queue' => 'technical_review'], 'permission' => 'applications.view'],
        ['key' => 'shortlisted', 'label' => __('company.dashboard.shortlisted'), 'icon' => 'list-checks', 'route' => 'company.applications', 'params' => ['stage' => 'shortlisted'], 'permission' => 'applications.view'],
    ];
@endphp
<div class="mx-auto max-w-7xl">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="company-accent-text text-sm font-semibold">{{ $companyMembership['organization_name'] }}</p>
            <h1 class="mt-1 text-3xl font-bold">{{ __('company.dashboard.title') }}</h1>
            <p class="panel-muted mt-2">{{ __('company.dashboard.subtitle') }}</p>
        </div>
        <form method="get" action="{{ route('company.dashboard') }}" class="flex items-center gap-3">
            <label for="dashboard-period" class="panel-muted text-sm">{{ __('company.dashboard.period') }}</label>
            <select id="dashboard-period" name="period" onchange="this.form.submit()" class="panel-input min-w-36">
                @foreach (['7d', '30d', '90d'] as $period)
                    <option value="{{ $period }}" @selected($dashboardPeriod === $period)>{{ __('company.dashboard.period_'.$period) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($companyError)
        <div class="panel-card border-red-500/30 p-5 text-red-500">{{ $companyError }}</div>
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="{{ __('company.dashboard.title') }}">
            @foreach ($metricCards as $card)
                @php
                    $canOpen = in_array($card['permission'], $permissions, true);
                    $url = $canOpen ? route($card['route'], $card['params'] ?? []) : null;
                @endphp
                <article class="panel-card group relative overflow-hidden p-5">
                    @if ($url)<a href="{{ $url }}" class="absolute inset-0" aria-label="{{ $card['label'] }}"></a>@endif
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="panel-muted max-w-xs text-sm">{{ $card['label'] }}</p>
                            <p class="mt-3 text-3xl font-bold">{{ $indicators[$card['key']] ?? 0 }}</p>
                        </div>
                        <span class="company-dashboard-icon"><i data-lucide="{{ $card['icon'] }}" class="h-5 w-5" aria-hidden="true"></i></span>
                    </div>
                </article>
            @endforeach
            <article class="panel-card group relative overflow-hidden p-5">
                @if (in_array('assessments.view', $permissions, true))<a href="{{ route('company.assessments') }}" class="absolute inset-0" aria-label="{{ __('company.dashboard.assessment_usage') }}"></a>@endif
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="panel-muted max-w-xs text-sm">{{ __('company.dashboard.assessment_usage') }}</p>
                        <p class="mt-3 text-3xl font-bold">{{ $usage['used'] ?? 0 }} <span class="panel-muted text-lg font-semibold">/ {{ $usage['quota'] ?? __('company.dashboard.unlimited') }}</span></p>
                    </div>
                    <span class="company-dashboard-icon"><i data-lucide="gauge" class="h-5 w-5" aria-hidden="true"></i></span>
                </div>
            </article>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
            <section class="panel-card p-6">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold">{{ __('company.dashboard.todos') }}</h2>
                    <span class="company-dashboard-icon"><i data-lucide="list-todo" class="h-5 w-5" aria-hidden="true"></i></span>
                </div>
                @if ($tasks === [])
                    <div class="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center dark:border-slate-700">
                        <p class="panel-muted text-sm">{{ __('company.dashboard.todos_empty') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($tasks as $task)
                            @php
                                $taskKey = 'company.dashboard.task_'.$task['type'];
                                $taskText = __($taskKey, [
                                    'count' => $task['count'] ?? 0,
                                    'position' => data_get($task, 'position.title', ''),
                                ]);
                                $taskPermission = $task['type'] === 'position_deadline' ? 'positions.view' : 'applications.view';
                                $canOpenTask = in_array($taskPermission, $permissions, true);
                            @endphp
                            @if ($canOpenTask)
                                <a href="{{ $task['target'] }}" class="company-task-row">
                                    <span class="company-task-dot" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1 text-sm font-medium">{{ $taskText }}</span>
                                    <i data-lucide="arrow-right" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                                </a>
                            @else
                                <div class="company-task-row"><span class="company-task-dot" aria-hidden="true"></span><span class="min-w-0 flex-1 text-sm font-medium">{{ $taskText }}</span></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="panel-card p-6">
                <h2 class="mb-5 text-lg font-semibold">{{ __('company.dashboard.summary') }}</h2>
                <dl class="space-y-4">
                    <div class="company-summary-row"><dt>{{ __('company.dashboard.application_to_assessment') }}</dt><dd>{{ $percent($summary['application_to_assessment_rate'] ?? null) }}</dd></div>
                    <div class="company-summary-row"><dt>{{ __('company.dashboard.assessment_to_interview') }}</dt><dd>{{ $percent($summary['assessment_to_interview_rate'] ?? null) }}</dd></div>
                    <div class="company-summary-row"><dt>{{ __('company.dashboard.average_shortlist') }}</dt><dd>{{ $shortlistTime }}</dd></div>
                    <div class="company-summary-row"><dt>{{ __('company.dashboard.largest_loss_stage') }}</dt><dd>{{ $lossStage ? __('company.applications.stage_'.$lossStage) : __('company.dashboard.no_data') }}</dd></div>
                </dl>
            </section>
        </div>

        <section class="panel-card mt-6 p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-lg font-semibold">{{ __('company.dashboard.team_summary') }}</h2>
                @if (in_array('members.view', $permissions, true))<a class="company-btn-secondary" href="{{ route('company.team') }}">{{ __('company.dashboard.manage_team') }}</a>@endif
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><p class="panel-muted text-sm">{{ __('company.dashboard.members_total') }}</p><p class="mt-2 text-2xl font-bold">{{ $dashboard['members_total'] ?? 0 }}</p></div>
                <div><p class="panel-muted text-sm">{{ __('company.dashboard.members_active') }}</p><p class="company-accent-text mt-2 text-2xl font-bold">{{ $dashboard['members_active'] ?? 0 }}</p></div>
                <div><p class="panel-muted text-sm">{{ __('company.dashboard.invitations') }}</p><p class="mt-2 text-2xl font-bold">{{ $dashboard['invitations_pending'] ?? 0 }}</p></div>
            </div>
        </section>
    @endif
</div>
@endsection
