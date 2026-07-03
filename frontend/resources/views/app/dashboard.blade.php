@extends('app.layouts.app')

@section('title', __('panel.dashboard.title'))

@section('content')
<div class="mx-auto max-w-5xl"
    x-data="dashboardWeeklyPlan(
        {{ Js::from($weeklyTasks) }},
        @js($stats['career']),
        {{ Js::from([
            'tasks_count' => __('panel.dashboard.tasks_count', ['done' => ':done', 'total' => ':total']),
            'tasks_add' => __('panel.dashboard.tasks_add'),
            'tasks_add_placeholder' => __('panel.dashboard.tasks_add_placeholder'),
            'tasks_note' => __('panel.dashboard.tasks_note'),
            'tasks_note_add' => __('panel.dashboard.tasks_note_add'),
            'tasks_note_placeholder' => __('panel.dashboard.tasks_note_placeholder'),
            'tasks_delete' => __('panel.dashboard.tasks_delete'),
            'tasks_empty' => __('panel.dashboard.tasks_empty'),
            'tasks_all_done' => __('panel.dashboard.tasks_all_done'),
        ]) }}
    )">

    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.dashboard.welcome') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.dashboard.subtitle') }}</p>
    </header>

    <section id="ozet" class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.readiness') }}</p>
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400" x-text="'%' + readiness" x-cloak></p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" :style="'width:' + readiness + '%'"></div>
            </div>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.target_career') }}</p>
            <p class="text-lg font-semibold" x-text="career"></p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.dashboard.from_gap') }}</p>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.this_week') }}</p>
            <p class="text-lg font-semibold" x-text="tasksCountLabel" x-cloak></p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.dashboard.from_roadmap') }}</p>
        </div>
    </section>

    @include('app.partials.dashboard-cv-radar')

    <section class="mb-10 grid gap-6 lg:grid-cols-2">
        <div class="panel-card flex flex-col p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ __('panel.dashboard.weekly_tasks') }}</h2>
                <a href="{{ route('panel.tasks') }}" class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                    {{ __('panel.dashboard.view_all') }} →
                </a>
            </div>
            @include('app.partials.panel-weekly-tasks', ['mode' => 'preview'])
        </div>

        <div class="panel-card flex flex-col p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ __('panel.dashboard.learning_title') }}</h2>
                <a href="{{ route('panel.learning') }}" class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                    {{ __('panel.dashboard.view_all') }} →
                </a>
            </div>
            @include('app.partials.panel-learning-resources', ['mode' => 'preview'])
        </div>
    </section>
</div>
@endsection
