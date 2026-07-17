@extends('app.layouts.app')

@section('title', __('panel.tasks.title'))

@section('content')
<div class="mx-auto max-w-5xl"
    @if (! empty($isPlanPending) && ! empty($selectedTarget['id']))
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
    @if (! empty($isPlanPending) && empty($weeklyTasks))
        <div class="panel-card mb-6 border-dashed p-6 text-center text-sm text-amber-600 dark:text-amber-300" role="status">
            <p x-show="!error">{{ __('panel.roadmap.plan_generating') }}</p>
            <p x-show="error" x-cloak x-text="error" class="text-red-600 dark:text-red-400"></p>
        </div>
    @endif

    <div x-data="careerTasks({{ Js::from($weeklyTasks) }}, {{ Js::from($personalTasks) }}, @js(route('panel.tasks.evidence', ['taskId' => '__TASK_ID__'])), @js(route('panel.tasks.status', ['taskId' => '__TASK_ID__'])), @js([
        'link' => app()->getLocale() === 'en' ? 'GitHub or public URL' : 'GitHub veya açık URL',
        'file' => app()->getLocale() === 'en' ? 'Private file' : 'Private dosya',
        'submit' => app()->getLocale() === 'en' ? 'Submit evidence' : 'Kanıt gönder',
        'pending' => app()->getLocale() === 'en' ? 'Pending' : 'Bekliyor',
        'queued' => app()->getLocale() === 'en' ? 'Queued' : 'Kuyrukta',
        'reviewing' => app()->getLocale() === 'en' ? 'Reviewing' : 'İnceleniyor',
        'accepted' => app()->getLocale() === 'en' ? 'Accepted' : 'Kabul edildi',
        'completed' => app()->getLocale() === 'en' ? 'Completed' : 'Tamamlandı',
        'revision_required' => app()->getLocale() === 'en' ? 'Revision requested' : 'Revizyon istendi',
        'personal' => __('panel.tasks.personal'),
    ]), {{ Js::from(['create' => route('panel.tasks.personal.create'), 'update' => route('panel.tasks.personal.update', ['taskId' => '__TASK_ID__']), 'delete' => route('panel.tasks.personal.delete', ['taskId' => '__TASK_ID__']), 'note' => route('panel.tasks.note.update', ['taskId' => '__TASK_ID__']), 'statusUpdate' => route('panel.tasks.status.update', ['taskId' => '__TASK_ID__']), 'targetId' => $selectedTarget['id'] ?? null]) }}, @js((int) ($stats['baseline'] ?? 0)))">
    <header class="mb-8"><h1 class="mb-1 text-2xl font-bold">{{ __('panel.tasks.title') }}</h1><p class="text-slate-600 dark:text-slate-400">{{ __('panel.tasks.subtitle') }}</p></header>

    <section id="ozet" class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.readiness') }}</p>
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400" x-text="'%' + readiness"></p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" :style="'width: ' + readiness + '%'"></div>
            </div>
            <p class="panel-muted mt-1 text-xs" x-show="!targetReady" x-cloak>{{ __('panel.dashboard.readiness_hybrid_hint', ['baseline' => (int) ($stats['baseline'] ?? 0)]) }}</p>
            <p class="mt-2 text-sm font-medium text-emerald-600 dark:text-emerald-400" x-show="targetReady" x-cloak>{{ __('panel.dashboard.readiness_target_ready') }}</p>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.target_career') }}</p>
            <p class="text-lg font-semibold">{{ $stats['career'] !== '' ? $stats['career'] : '—' }}</p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.roadmap.selected_target') }}</p>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.dashboard.this_week') }}</p>
            <p class="text-lg font-semibold" x-text="doneCount + '/' + totalCount + ' {{ app()->getLocale() === 'en' ? 'tasks' : 'görev' }}'"></p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.dashboard.from_roadmap') }}</p>
        </div>
    </section>

    @if (! empty($selectedTarget))
        <div class="panel-card mb-6 border-emerald-500/30 bg-emerald-500/10 p-4"><p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('panel.roadmap.selected_target') }}</p><p class="mt-1 font-semibold">{{ $selectedTarget['title'] }}</p></div>
    @endif
    @if (! empty($careerEngineError))<p class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200" role="status">{{ $careerEngineError }}</p>@endif
    <p x-show="error" x-cloak class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-700 dark:text-red-200" x-text="error" role="alert"></p>

    <form class="panel-card mb-6 flex gap-2 p-4" @submit.prevent="addTask()">
        <input type="text" x-model="newTaskTitle" class="panel-input-block min-w-0 flex-1" placeholder="{{ __('panel.dashboard.tasks_add_placeholder') }}">
        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('panel.dashboard.tasks_add') }}</button>
    </form>

    <div class="space-y-4">
        <template x-for="task in tasks" :key="task.id"><article class="panel-card space-y-4 p-5" :class="task.done && 'opacity-70'">
            <div class="flex items-start gap-3">
                <input type="checkbox" class="mt-1 h-4 w-4 rounded" :checked="task.done" @change="toggleTask(task)">
                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold" :class="task.done && 'line-through'" x-text="task.title"></h2><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300" x-text="labels[task.status] || labels.pending"></span></div><p x-show="task.hint" class="panel-muted mt-1 text-sm" x-text="task.hint"></p>
                    <template x-if="task.training_suggestions?.length"><ul class="mt-3 space-y-1 text-xs text-slate-500"><template x-for="resource in task.training_suggestions" :key="resource.catalog_id || resource.title"><li><a :href="resource.url" target="_blank" rel="noopener noreferrer" class="text-emerald-600 hover:underline" x-text="resource.title || resource.catalog_id"></a><span x-show="resource.provider" x-text="' · ' + resource.provider"></span></li></template></ul></template>
                </div>
                <div class="flex gap-2"><button type="button" class="panel-outline-btn" @click="toggleNote(task)">{{ __('panel.dashboard.tasks_note_add') }}</button><button x-show="task.source === 'custom'" type="button" class="panel-btn-danger" @click="removeTask(task)">{{ __('panel.dashboard.tasks_delete') }}</button></div>
            </div>
            <div x-show="task.showNote || task.note" x-cloak><label class="panel-muted mb-1 block text-xs">{{ __('panel.dashboard.tasks_note') }}</label><textarea x-model="task.note" @input.debounce.500ms="saveNote(task)" rows="2" class="panel-input-block w-full" placeholder="{{ __('panel.dashboard.tasks_note_placeholder') }}"></textarea></div>
            <p x-show="task.feedback" x-cloak class="rounded-lg bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-200" x-text="task.feedback"></p>
        </article></template>
    </div>
    @if (empty($isPlanPending))
        <p x-show="!tasks.length" x-cloak class="panel-card border-dashed p-6 text-center text-sm text-slate-500">{{ __('panel.dashboard.tasks_empty') }}</p>
    @endif
    </div>
</div>
@endsection
