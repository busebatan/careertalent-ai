@extends('app.layouts.app')

@section('title', __('panel.tasks.title'))

@section('content')
<div class="mx-auto max-w-3xl"
    x-data="careerTasks({{ Js::from($weeklyTasks) }}, @js(route('panel.tasks.evidence', ['taskId' => '__TASK_ID__'])), @js(route('panel.tasks.status', ['taskId' => '__TASK_ID__'])), @js([
        'link' => app()->getLocale() === 'en' ? 'GitHub or public URL' : 'GitHub veya açık URL',
        'file' => app()->getLocale() === 'en' ? 'Private file' : 'Private dosya',
        'submit' => app()->getLocale() === 'en' ? 'Submit evidence' : 'Kanıt gönder',
        'pending' => app()->getLocale() === 'en' ? 'Pending review' : 'İnceleme bekliyor',
        'queued' => app()->getLocale() === 'en' ? 'Queued' : 'Kuyrukta',
        'reviewing' => app()->getLocale() === 'en' ? 'Reviewing' : 'İnceleniyor',
        'accepted' => app()->getLocale() === 'en' ? 'Accepted' : 'Kabul edildi',
        'completed' => app()->getLocale() === 'en' ? 'Completed' : 'Tamamlandı',
        'revision_required' => app()->getLocale() === 'en' ? 'Revision requested' : 'Revizyon istendi',
    ]))">

    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.tasks.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.tasks.subtitle') }}</p>
    </header>

    @if (! empty($careerEngineError))
        <p class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200" role="status">{{ $careerEngineError }}</p>
    @endif

    <p x-show="error" x-cloak class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-700 dark:text-red-200" x-text="error" role="alert"></p>
    <div class="space-y-4">
        <template x-for="task in tasks" :key="task.id">
            <article class="panel-card space-y-4 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold" x-text="task.title"></h2>
                        <p class="panel-muted mt-1 text-sm" x-text="task.hint"></p>
                        <template x-if="task.training_suggestions?.length">
                            <ul class="mt-3 space-y-1 text-xs text-slate-500">
                                <template x-for="resource in task.training_suggestions" :key="resource.catalog_id || resource.id || resource.title">
                                    <li><a :href="resource.url" target="_blank" rel="noopener noreferrer" class="text-emerald-600 hover:underline dark:text-emerald-400" x-text="resource.title || resource.catalog_id"></a><span x-show="resource.provider" x-text="' · ' + resource.provider"></span></li>
                                </template>
                            </ul>
                        </template>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs"
                        :class="['completed', 'accepted'].includes(task.status) ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : (task.status === 'revision_required' ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300')"
                        x-text="labels[task.status] || labels.pending"></span>
                </div>
                <p x-show="task.feedback" x-cloak class="rounded-lg bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-200" x-text="task.feedback"></p>
                <form class="grid gap-2 sm:grid-cols-[8rem_1fr_auto]" @submit.prevent="submitEvidence(task)">
                    <select x-model="form(task).kind" class="panel-input">
                        <option value="link" x-text="labels.link"></option>
                        <option value="file" x-text="labels.file"></option>
                    </select>
                    <input x-show="form(task).kind === 'link'" x-model="form(task).url" type="url" class="panel-input-block" placeholder="https://github.com/...">
                    <input x-show="form(task).kind === 'file'" @change="form(task).file = $event.target.files[0] || null" type="file" class="panel-input-block" accept="application/pdf,image/*">
                    <button type="submit" :disabled="submitting[task.id]" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60" x-text="submitting[task.id] ? '…' : labels.submit"></button>
                </form>
            </article>
        </template>
    </div>
    <p x-show="!tasks.length" x-cloak class="panel-card border-dashed p-6 text-center text-sm text-slate-500">{{ __('panel.dashboard.tasks_empty') }}</p>
</div>
@endsection
