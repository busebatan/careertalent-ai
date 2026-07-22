@extends('app.layouts.app')
@section('title', __('panel.chat.title'))
@section('content')
<div data-chat-page class="mx-auto w-full max-w-4xl" x-data="careerChat({{ Js::from($messages) }}, @js(route('panel.chat.send')), @js(route('panel.chat.new')), {{ Js::from([
    'failed' => __('panel.chat.failed'),
    'action_failed' => __('panel.chat.action_failed'),
    'action_timeout' => __('panel.chat.action_timeout'),
    'modes' => [
        'cv' => ['title' => __('panel.chat.mode_cv_title')],
        'interview' => ['title' => __('panel.chat.mode_interview_title')],
        'career' => ['title' => __('panel.chat.mode_career_title')],
    ],
]) }}, {{ Js::from([
    'jobStatusUrl' => route('panel.job-matches.status', ['jobId' => '__JOB__']),
    'createCvVersionUrl' => route('panel.chat.cv-version', ['jobId' => '__JOB__']),
    'versionsUrl' => route('panel.cv.versions.list'),
    'editorUrl' => route('panel.cv-builder', ['cvVersion' => '__VERSION__']),
    'activeCvName' => $activeCvName ?? '',
    'initialThreads' => $chatThreads ?? [],
    'historyHasMore' => $chatHistoryHasMore ?? false,
    'historyUrl' => route('panel.chat.history'),
    'historyDetailUrl' => route('panel.chat.history.detail', ['threadId' => '__THREAD__']),
    'locale' => app()->getLocale() === 'en' ? 'en-US' : 'tr-TR',
]) }})">
    <header class="mb-8 flex shrink-0 items-start justify-between gap-4">
        <div><h1 class="mb-1 text-2xl font-bold">{{ __('panel.chat.title') }}</h1><p class="text-slate-600 dark:text-slate-400">{{ __('panel.chat.subtitle') }}</p></div>
        <button type="button" class="panel-outline-btn" @click="startNewChat()" :disabled="newChatLoading || !messages.length">{{ __('panel.chat.new_chat') }}</button>
    </header>
    @if ($chatError)<p class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-200">{{ $chatError }}</p>@endif

    <section data-chat-panel class="panel-card flex h-[calc(100dvh-15rem)] min-h-[28rem] max-h-[52rem] flex-col overflow-hidden p-5 sm:h-[calc(100dvh-13rem)]">
        <div x-ref="messages" data-chat-messages class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain rounded-2xl border border-slate-200 bg-slate-100 p-4 dark:border-slate-800 dark:bg-slate-950/70" aria-live="polite">
            <div data-chat-mode-selector x-show="!modeSelected && !messages.length" class="rounded-2xl border border-emerald-500/20 bg-white p-4 dark:bg-slate-900">
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('panel.chat.ready_message') }}</p>
                <h2 class="mt-3 font-semibold">{{ __('panel.chat.mode_title') }}</h2>
                <p class="panel-muted mt-1 text-xs">{{ __('panel.chat.mode_subtitle') }}</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <button type="button" class="rounded-xl border border-slate-200 p-4 text-left transition hover:border-emerald-500 hover:bg-emerald-500/5 dark:border-slate-700" @click="selectMode('cv')">
                        <i data-lucide="file-search" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                        <span class="mt-3 block text-sm font-semibold">{{ __('panel.chat.mode_cv_title') }}</span>
                        <span class="panel-muted mt-1 block text-xs">{{ __('panel.chat.mode_cv_description') }}</span>
                    </button>
                    <button type="button" class="rounded-xl border border-slate-200 p-4 text-left transition hover:border-emerald-500 hover:bg-emerald-500/5 dark:border-slate-700" @click="selectMode('interview')">
                        <i data-lucide="messages-square" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                        <span class="mt-3 block text-sm font-semibold">{{ __('panel.chat.mode_interview_title') }}</span>
                        <span class="panel-muted mt-1 block text-xs">{{ __('panel.chat.mode_interview_description') }}</span>
                    </button>
                    <button type="button" class="rounded-xl border border-slate-200 p-4 text-left transition hover:border-emerald-500 hover:bg-emerald-500/5 dark:border-slate-700" @click="selectMode('career')">
                        <i data-lucide="route" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                        <span class="mt-3 block text-sm font-semibold">{{ __('panel.chat.mode_career_title') }}</span>
                        <span class="panel-muted mt-1 block text-xs">{{ __('panel.chat.mode_career_description') }}</span>
                    </button>
                </div>
            </div>

            <div x-show="modeSelected && !messages.length" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl rounded-tl-sm border border-emerald-500/20 bg-emerald-500/10 p-3 text-sm text-slate-800 dark:text-slate-200">
                <span><span class="font-medium">{{ __('panel.chat.mode_selected') }}:</span> <span x-text="modeLabel()"></span></span>
                <button type="button" class="font-medium text-emerald-700 hover:underline dark:text-emerald-300" @click="changeMode()">{{ __('panel.chat.mode_change') }}</button>
            </div>

            <template x-for="message in messages" :key="message.id">
                <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[92%] sm:max-w-[85%]">
                        <p class="whitespace-pre-wrap rounded-2xl p-3 text-sm" :class="message.role === 'user' ? 'rounded-tr-sm bg-emerald-600 text-white' : 'rounded-tl-sm border border-slate-200 bg-white text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'" x-text="message.content"></p>
                        <template x-if="message.meta?.suggested_actions?.length"><ul class="mt-2 space-y-1 text-xs text-emerald-700"><template x-for="suggestion in message.meta.suggested_actions" :key="suggestion"><li x-text="'• ' + suggestion"></li></template></ul></template>

                        <template x-if="message.meta?.action?.type === 'job_cv_draft'">
                            <div data-chat-cv-action class="mt-3 rounded-2xl border border-sky-500/30 bg-white p-4 text-sm shadow-sm dark:bg-slate-900">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div><p class="font-semibold">{{ __('panel.chat.job_cv_title') }}</p><p class="panel-muted mt-1 text-xs" x-text="[message.meta.action.title, message.meta.action.company].filter(Boolean).join(' · ')"></p></div>
                                    <div x-show="message.meta.action.status === 'ready'" class="rounded-xl bg-sky-500/10 px-3 py-2 text-center"><p class="text-[10px] uppercase text-sky-700 dark:text-sky-300">{{ __('panel.chat.match_score') }}</p><p class="font-bold" x-text="'%' + message.meta.action.match_score"></p></div>
                                </div>

                                <p x-show="['queued','running'].includes(message.meta.action.status)" class="mt-3 text-amber-600">{{ __('panel.chat.action_analyzing') }}</p>
                                <div x-show="message.meta.action.status === 'ready'" class="mt-4 space-y-4">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div><p class="mb-1 text-xs font-semibold text-emerald-700">{{ __('panel.chat.matched_skills') }}</p><p class="panel-muted text-xs" x-text="message.meta.action.matched_skills.join(', ') || '—'"></p></div>
                                        <div><p class="mb-1 text-xs font-semibold text-amber-700">{{ __('panel.chat.missing_skills') }}</p><p class="panel-muted text-xs" x-text="message.meta.action.missing_skills.join(', ') || '—'"></p></div>
                                    </div>

                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide">{{ __('panel.chat.changes_preview') }}</p>
                                        <div class="space-y-2"><template x-for="item in message.meta.action.cv_suggestions" :key="item.id"><label class="flex gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700" :class="!item.safe_to_apply && 'opacity-60'"><input type="checkbox" class="mt-1 accent-emerald-600" x-model="message.meta.action.selected" :value="item.id" :disabled="!item.safe_to_apply"><span><span class="font-medium" x-text="item.title"></span><span class="panel-muted mt-1 block text-xs" x-text="item.reason"></span><span class="mt-1 block text-xs" x-text="item.suggested_text"></span><span x-show="!item.safe_to_apply" class="mt-1 block text-xs text-amber-600">{{ __('panel.chat.development_only') }}</span></span></label></template></div>
                                    </div>

                                    <label class="block"><span class="mb-1 block text-xs font-semibold">{{ __('panel.chat.source_cv') }}</span><select x-model="message.meta.action.sourceCvVersionId" class="panel-input-block w-full"><option value="" x-text="@js(__('panel.chat.active_cv')) + (actions.activeCvName ? ' · ' + actions.activeCvName : '')"></option><template x-for="version in cvVersions" :key="version.id"><option :value="version.id" x-text="version.version_name + ' (' + version.language.toUpperCase() + ')' + (version.is_main ? ' · ' + @js(__('panel.chat.main_version')) : '')"></option></template></select></label>
                                    <button type="button" class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 font-medium text-white hover:bg-emerald-500 disabled:opacity-50" :disabled="message.meta.action.creating || !message.meta.action.selected.length" @click="createCvVersion(message.meta.action)" x-text="message.meta.action.creating ? @js(__('panel.chat.creating_version')) : @js(__('panel.chat.create_version'))"></button>
                                </div>
                                <p x-show="message.meta.action.error" x-text="message.meta.action.error" class="mt-3 text-xs text-red-600" role="alert"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <p x-show="sending" class="text-sm text-slate-500">{{ __('panel.chat.thinking') }}</p>
        </div>
        <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600"></p>
        <form x-show="modeSelected" class="mt-4 flex items-end gap-3" @submit.prevent="send()"><textarea x-model="text" rows="2" maxlength="30000" class="panel-input-block min-w-0 flex-1 resize-none rounded-xl" placeholder="{{ __('panel.chat.input_placeholder') }}" :disabled="!modeSelected" @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"></textarea><button :disabled="sending || !modeSelected" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60">{{ __('panel.chat.send') }}</button></form>
    </section>

    <section data-chat-history class="panel-card mt-8 p-5 sm:p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold">{{ __('panel.chat.history_title') }}</h2>
            <p class="panel-muted mt-1 text-sm">{{ __('panel.chat.history_subtitle') }}</p>
        </div>
        @if ($chatHistoryError ?? false)<p class="mb-4 text-sm text-red-600">{{ $chatHistoryError }}</p>@endif
        <p x-show="!threads.length" class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700">{{ __('panel.chat.history_empty') }}</p>
        <div x-show="threads.length" class="divide-y divide-slate-200 border-y border-slate-200 dark:divide-slate-800 dark:border-slate-800">
            <template x-for="thread in threads" :key="thread.id">
                <button type="button" class="flex w-full items-center justify-between gap-4 py-4 text-left hover:text-emerald-600" @click="openHistory(thread)">
                    <span class="min-w-0"><span class="block truncate font-medium" x-text="thread.title"></span><span class="panel-muted mt-1 block text-xs" x-text="formatHistoryDate(thread.updated_at)"></span></span>
                    <span class="shrink-0 text-xs text-slate-500" x-text="@js(__('panel.chat.history_messages')).replace(':count', thread.message_count)"></span>
                </button>
            </template>
        </div>
        <button x-show="historyHasMore" type="button" class="panel-outline-btn mt-5" :disabled="historyLoading" @click="loadMoreHistory()" x-text="historyLoading ? @js(__('panel.chat.history_loading')) : @js(__('panel.chat.history_load_more'))"></button>
    </section>

    <div data-chat-history-modal x-cloak x-show="historyOpen" @keydown.escape.window="closeHistory()" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="chat-history-title">
        <div class="panel-card flex max-h-[85dvh] w-full max-w-2xl flex-col overflow-hidden p-0" @click.outside="closeHistory()">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 dark:border-slate-800">
                <div class="min-w-0"><p id="chat-history-title" class="text-xs font-semibold uppercase tracking-wide text-emerald-600">{{ __('panel.chat.history_dialog_title') }}</p><h2 class="mt-1 truncate text-lg font-semibold" x-text="selectedThread?.thread?.title || ''"></h2></div>
                <button type="button" class="panel-outline-btn" @click="closeHistory()">{{ __('panel.chat.history_close') }}</button>
            </header>
            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5">
                <template x-for="message in (selectedThread?.messages || [])" :key="message.id">
                    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                        <p class="max-w-[88%] whitespace-pre-wrap rounded-2xl p-3 text-sm" :class="message.role === 'user' ? 'rounded-tr-sm bg-emerald-600 text-white' : 'rounded-tl-sm border border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'" x-text="message.content"></p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
