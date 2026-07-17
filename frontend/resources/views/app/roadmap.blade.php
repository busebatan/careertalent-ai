@extends('app.layouts.app')

@section('title', __('panel.roadmap.title'))

@section('content')
<div class="mx-auto max-w-3xl"
    @if (! empty($isAnalysisPending))
        x-data="careerAnalysisWatcher({{ Js::from([
            'status' => $analysisStatus,
            'statusUrl' => route('panel.roadmap.analysis-status'),
            'failedMessage' => __('panel.roadmap.analysis_failed'),
            'errorMessage' => __('panel.roadmap.analysis_status_error'),
            'timeoutMessage' => __('panel.roadmap.analysis_timeout'),
        ]) }})"
        x-init="start()"
    @elseif (! empty($selectedTarget['id']) && ! empty($isPlanPending))
        x-data="careerPlanWatcher({{ Js::from([
            'status' => $selectedTarget['status'] ?? 'queued',
            'statusUrl' => route('panel.roadmap.plan-status', ['targetId' => $selectedTarget['id']]),
            'failedMessage' => __('panel.roadmap.plan_failed'),
            'errorMessage' => __('panel.roadmap.plan_status_error'),
            'timeoutMessage' => __('panel.roadmap.plan_timeout'),
        ]) }})"
        x-init="start()"
    @endif
>
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.roadmap.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.roadmap.subtitle') }}</p>
    </header>

    @if (! empty($careerEngineError))
        <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200" role="status">{{ $careerEngineError }}</div>
    @endif

    @if (! empty($selectedTarget))
        <div class="panel-card mb-4 border-emerald-500/30 bg-emerald-500/10 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('panel.roadmap.selected_target') }}</p>
            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ $selectedTarget['title'] }}</p>
            @if (! empty($selectedTarget['job_url']))
                <a href="{{ $selectedTarget['job_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs text-emerald-700 hover:underline dark:text-emerald-300">{{ __('panel.roadmap.open_job') }}</a>
            @endif
        </div>
    @endif

    <div class="panel-card mb-6 flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="panel-muted text-sm">{{ __('panel.dashboard.target_career') }}</p>
            <p class="text-lg font-semibold">{{ $stats['career'] }}</p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.roadmap.from_gap') }}</p>
        </div>
        <div class="text-right">
            <p class="panel-muted text-sm">{{ __('panel.roadmap.week_label') }}</p>
            <p class="text-lg font-semibold">%{{ $stats['readiness'] }} {{ __('panel.dashboard.readiness') }}</p>
            @if (! empty($stats['target_ready']))
                <p class="mt-1 text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ __('panel.dashboard.readiness_target_ready') }}</p>
            @else
                <p class="panel-muted mt-1 text-xs">{{ __('panel.dashboard.readiness_hybrid_hint', ['baseline' => (int) ($stats['baseline'] ?? 0)]) }}</p>
            @endif
        </div>
    </div>

    <section class="mt-10 scroll-mt-6">
        <h2 class="mb-5 text-xl font-bold">{{ __('panel.career_ladder.title') }}</h2>
        @if (! empty($careerLadder))
            @include('app.partials.career-ladder', ['selectedTarget' => $selectedTarget, 'careerTierMeta' => $careerTierMeta, 'hideSectionHeader' => true])
        @elseif (! empty($isAnalysisPending))
            <div class="panel-card border-dashed p-6 text-center text-sm text-amber-600 dark:text-amber-300" role="status">
                <p x-show="!error">{{ __('panel.roadmap.analysis_in_progress') }}</p>
                <p x-show="error" x-cloak x-text="error" class="text-red-600 dark:text-red-400"></p>
            </div>
        @else
            <div class="panel-card border-dashed p-6 text-center text-sm text-slate-500">{{ app()->getLocale() === 'en' ? 'AI career ladder is not ready.' : 'AI kariyer merdiveni henüz hazır değil.' }}</div>
        @endif
    </section>

    <section class="mt-10 scroll-mt-6">
        <header class="mb-5 flex items-end justify-between gap-3">
            <div><h2 class="text-xl font-bold">{{ __('panel.tasks.title') }}</h2><p class="panel-muted mt-1 text-sm">{{ __('panel.roadmap.tasks_after_target') }}</p></div>
            <a href="{{ route('panel.tasks') }}" class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">{{ __('panel.roadmap.view_tasks') }} →</a>
        </header>
        @if (! empty($roadmapTasks))
        <ol id="gorevler" class="relative mb-8 space-y-0 border-l border-slate-200 pl-6 dark:border-slate-700">
            @foreach ($roadmapTasks as $index => $task)
                <li class="relative pb-8 last:pb-0"><span class="absolute -left-[1.625rem] flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-slate-200 text-[10px] font-bold text-slate-600 dark:border-slate-900 dark:bg-slate-700 dark:text-slate-300">{{ $index + 1 }}</span><div class="panel-card p-4"><p class="mb-1 text-xs font-medium uppercase tracking-wide text-emerald-600 dark:text-emerald-400">{{ __('panel.roadmap.step', ['num' => $index + 1]) }}</p><p class="font-medium text-slate-800 dark:text-slate-100">{{ $task['title'] }}</p>@if (! empty($task['hint']))<p class="panel-muted mt-2 text-sm">{{ $task['hint'] }}</p>@endif
                    @if (! empty($task['training_suggestions']) && is_array($task['training_suggestions']))<ul class="mt-3 space-y-1 text-xs text-slate-500">@foreach ($task['training_suggestions'] as $resource)@if (is_array($resource) && ! empty($resource['url']))<li><a href="{{ $resource['url'] }}" target="_blank" rel="noopener noreferrer" class="text-emerald-600 hover:underline dark:text-emerald-400">{{ $resource['title'] ?? $resource['catalog_id'] ?? '' }}</a>@if (! empty($resource['provider'])) · {{ $resource['provider'] }}@endif</li>@endif @endforeach</ul>@endif
                </div></li>
            @endforeach
        </ol>
        @elseif (! empty($isPlanPending))
            <div class="panel-card mb-8 border-dashed p-6 text-center text-sm text-amber-600 dark:text-amber-300" role="status"><p x-show="!error">{{ __('panel.roadmap.plan_generating') }}</p><p x-show="error" x-cloak x-text="error" class="text-red-600 dark:text-red-400"></p></div>
        @else
            <div class="panel-card mb-8 border-dashed p-6 text-center text-sm text-slate-500">{{ __('panel.dashboard.tasks_empty') }}</div>
        @endif
    </section>

    <section id="egitimler" class="mt-10 scroll-mt-6">
        <header class="mb-5">
            <h2 class="text-xl font-bold">{{ __('panel.learning_page.title') }}</h2>
            <p class="panel-muted mt-1 text-sm">{{ __('panel.learning_page.subtitle') }}</p>
        </header>
        @if (($selectedTarget['plan']['training_search']['status'] ?? null) === 'partial')
            <p class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200">{{ __('panel.learning_page.search_partial') }}</p>
        @endif
        @include('app.partials.panel-learning-resources', ['mode' => 'compact'])
    </section>
</div>
@endsection
