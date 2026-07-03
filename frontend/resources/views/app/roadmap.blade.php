@extends('app.layouts.app')

@section('title', __('panel.roadmap.title'))

@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.roadmap.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.roadmap.subtitle') }}</p>
    </header>

    <div class="panel-card mb-6 flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="panel-muted text-sm">{{ __('panel.dashboard.target_career') }}</p>
            <p class="text-lg font-semibold">{{ $stats['career'] }}</p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.roadmap.from_gap') }}</p>
        </div>
        <div class="text-right">
            <p class="panel-muted text-sm">{{ __('panel.roadmap.week_label') }}</p>
            <p class="text-lg font-semibold">%{{ $stats['readiness'] }} {{ __('panel.dashboard.readiness') }}</p>
        </div>
    </div>

    <ol class="relative mb-8 space-y-0 border-l border-slate-200 pl-6 dark:border-slate-700">
        @foreach ($roadmapTasks as $index => $task)
            <li class="relative pb-8 last:pb-0">
                <span class="absolute -left-[1.625rem] flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-slate-200 text-[10px] font-bold text-slate-600 dark:border-slate-900 dark:bg-slate-700 dark:text-slate-300">
                    {{ $index + 1 }}
                </span>
                <div class="panel-card p-4">
                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-emerald-600 dark:text-emerald-400">
                        {{ __('panel.roadmap.step', ['num' => $index + 1]) }}
                    </p>
                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $task['title'] }}</p>
                    @if (! empty($task['hint']))
                        <p class="panel-muted mt-2 text-sm">{{ $task['hint'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('panel.tasks') }}" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500">
            {{ __('panel.roadmap.view_tasks') }}
        </a>
        <a href="{{ route('panel.career-ladder') }}" class="panel-btn-secondary">
            {{ __('panel.roadmap.view_ladder') }}
        </a>
    </div>
</div>
@endsection
