@extends('app.layouts.app')

@section('title', __('panel.tasks.title'))

@section('content')
<div class="mx-auto max-w-3xl"
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
        ]) }}
    )">

    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.tasks.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.tasks.subtitle') }}</p>
    </header>

    @include('app.partials.panel-weekly-tasks', ['mode' => 'full'])
</div>
@endsection
