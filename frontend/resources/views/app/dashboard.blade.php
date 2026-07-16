@extends('app.layouts.app')

@section('title', __('panel.dashboard.title'))

@section('content')
<div class="mx-auto max-w-5xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.dashboard.welcome') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.dashboard.subtitle') }}</p>
    </header>

    @if (! empty($careerEngineStatus) && ! in_array($careerEngineStatus, ['ready', 'empty'], true))
        <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200" role="status">
            {{ is_string($careerEngineStatus) ? $careerEngineStatus : __('panel.dashboard.tasks_empty') }}
        </div>
    @endif

    <section id="ozet" class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.readiness') }}</p>
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">%{{ (int) ($stats['readiness'] ?? 0) }}</p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(0, min(100, (int) ($stats['readiness'] ?? 0))) }}%"></div>
            </div>
            @if (! empty($stats['target_ready']))
                <p class="mt-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ __('panel.dashboard.readiness_target_ready') }}</p>
            @else
                <p class="panel-muted mt-1 text-xs">{{ __('panel.dashboard.readiness_hybrid_hint', ['baseline' => (int) ($stats['baseline'] ?? 0)]) }}</p>
            @endif
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.target_career') }}</p>
            <p class="text-lg font-semibold">{{ $stats['career'] !== '' ? $stats['career'] : '—' }}</p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.dashboard.from_gap') }}</p>
        </div>
        <div class="panel-card p-6">
            @php($doneCount = count(array_filter($weeklyTasks ?? [], static fn ($task) => in_array($task['status'] ?? '', ['completed', 'accepted'], true))))
            <p class="panel-muted text-sm">{{ __('panel.dashboard.this_week') }}</p>
            <p class="text-lg font-semibold">{{ $doneCount }}/{{ count($weeklyTasks ?? []) }} {{ app()->getLocale() === 'en' ? 'tasks' : 'görev' }}</p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.dashboard.from_roadmap') }}</p>
        </div>
    </section>

    @include('app.partials.dashboard-cv-radar')

    <section class="mb-10 grid gap-6 lg:grid-cols-2">
        <div class="panel-card flex flex-col p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ __('panel.dashboard.weekly_tasks') }}</h2>
                <a href="{{ route('panel.tasks') }}" class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">{{ __('panel.dashboard.view_all') }} →</a>
            </div>
            @if (! empty($weeklyTasks))
                <ul class="space-y-3">
                    @foreach (array_slice($weeklyTasks, 0, 3) as $task)
                        <li class="panel-entry p-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-medium">{{ $task['title'] ?? '' }}</p>
                                <span class="text-xs text-slate-500">{{ $task['status'] ?? 'pending' }}</span>
                            </div>
                            @if (! empty($task['hint']))<p class="mt-1 text-xs text-slate-500">{{ $task['hint'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="panel-card border-dashed p-5 text-center text-sm text-slate-500">{{ __('panel.dashboard.tasks_empty') }}</p>
            @endif
        </div>

        <div class="panel-card flex flex-col p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ __('panel.dashboard.learning_title') }}</h2>
                <a href="{{ route('panel.learning') }}" class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">{{ __('panel.dashboard.view_all') }} →</a>
            </div>
            @if (! empty($learningResources))
                @include('app.partials.panel-learning-resources', ['mode' => 'preview'])
            @else
                <p class="panel-card border-dashed p-5 text-center text-sm text-slate-500">{{ app()->getLocale() === 'en' ? 'AI training suggestions will appear after a target plan is ready.' : 'AI eğitim önerileri hedef planı hazır olduğunda görünecek.' }}</p>
            @endif
        </div>
    </section>
</div>
@endsection
