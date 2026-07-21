@extends('app.layouts.app')
@section('title', __('panel.interview.title'))
@section('content')
<div class="mx-auto max-w-5xl"
    x-data="careerInterview({
        initial: null,
        history: {{ Js::from($interviewHistory) }},
        startUrl: @js(route('panel.interview.start')),
        scoreUrlTemplate: @js(route('panel.interview.score', ['interviewId' => '__INTERVIEW_ID__'])),
        historyUrl: @js(route('panel.interview.history')),
        detailUrlTemplate: @js(route('panel.interview.detail', ['interviewId' => '__INTERVIEW_ID__'])),
        retryUrlTemplate: @js(route('panel.interview.retry', ['interviewId' => '__INTERVIEW_ID__'])),
        labels: {{ Js::from([
            'failed' => __('panel.interview.failed'),
            'progress' => __('panel.interview.question_progress'),
            'historySummary' => __('panel.interview.history_summary'),
            'completed' => __('panel.interview.completed_notice'),
            'statusCompleted' => __('panel.interview.status_completed'),
            'statusArchived' => __('panel.interview.status_archived'),
        ]) }},
    })">
    <header class="mb-8 flex items-start justify-between gap-4">
        <div>
            <h1 class="mb-1 text-2xl font-bold">{{ __('panel.interview.title') }}</h1>
            <p class="text-slate-600 dark:text-slate-400">{{ __('panel.interview.subtitle') }}</p>
        </div>
        <button type="button"
            class="shrink-0 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-60"
            :disabled="busy"
            @click="showLangModal = true"
            x-text="interview ? @js(__('panel.interview.restart')) : @js(__('panel.interview.start'))">{{ __('panel.interview.start') }}</button>
    </header>

    @if ($interviewError)
        <p class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-300">{{ $interviewError }}</p>
    @endif
    <p x-show="error" x-cloak x-text="error" class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-300"></p>
    <p x-show="notice" x-cloak x-text="notice" class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-800 dark:text-emerald-200"></p>

    <section x-show="!interview" x-cloak class="panel-card p-8 text-center">
        <p class="panel-muted">{{ __('panel.interview.empty') }}</p>
    </section>

    <section data-interview-active x-show="interview && question" x-cloak class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="panel-card p-6">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400" x-text="question?.competency"></p>
                <p data-interview-progress class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300" x-text="progressLabel()"></p>
            </div>
            <h2 class="mb-4 text-xl font-semibold" x-text="question?.question"></h2>
            <p class="panel-muted mb-4 text-sm" x-text="question?.guidance"></p>
            <textarea x-model="answer" rows="8" class="panel-input-block w-full rounded-2xl" :placeholder="@js(__('panel.interview.answer_placeholder'))"></textarea>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" :disabled="busy || answer.trim().length < 20" @click="score()"
                    class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-60">
                    {{ __('panel.interview.score_button') }}
                </button>
                <button type="button" data-interview-previous :disabled="busy || idx === 0" @click="previous()" class="panel-btn-secondary text-sm disabled:opacity-50">
                    {{ __('panel.interview.previous_button') }}
                </button>
                <button type="button" data-interview-next :disabled="busy || idx >= questionCount - 1" @click="next()" class="panel-btn-secondary text-sm disabled:opacity-50">
                    {{ __('panel.interview.next_button') }}
                </button>
            </div>
        </div>

        <aside class="panel-card h-fit p-5">
            <h2 class="mb-3 font-semibold">{{ __('panel.interview.ai_feedback') }}</h2>
            <div x-show="!result" class="panel-muted text-sm">{{ __('panel.interview.feedback_empty') }}</div>
            <div x-show="result" x-cloak>
                <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400" x-text="result?.score + '/100'"></p>
                <p class="mt-3 text-sm" x-text="result?.feedback"></p>
                <ul class="mt-3 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                    <template x-for="item in result?.improvements || []" :key="item"><li x-text="'• ' + item"></li></template>
                </ul>
            </div>
        </aside>
    </section>

    <section data-interview-history class="mt-10">
        <div class="mb-4">
            <h2 class="text-xl font-semibold">{{ __('panel.interview.history_title') }}</h2>
            <p class="panel-muted mt-1 text-sm">{{ __('panel.interview.history_subtitle') }}</p>
        </div>

        <div x-show="history.length === 0" x-cloak class="panel-card p-6 text-center">
            <p class="panel-muted text-sm">{{ __('panel.interview.history_empty') }}</p>
        </div>

        <div x-show="history.length > 0" x-cloak class="space-y-4">
            <template x-for="item in history" :key="item.id">
                <article class="panel-card overflow-hidden">
                    <button type="button" class="flex w-full flex-col gap-4 p-5 text-left sm:flex-row sm:items-center sm:justify-between" :aria-expanded="openHistoryId === item.id" @click="toggleHistory(item)">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold" x-text="item.target_role"></h3>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300" x-text="statusLabel(item.status)"></span>
                            </div>
                            <p class="panel-muted mt-1 text-xs">
                                <span x-text="formatDate(item.ended_at || item.created_at)"></span>
                                <span aria-hidden="true"> · </span>
                                <span x-text="historySummary(item)"></span>
                            </p>
                            <p class="mt-1 text-xs text-sky-700 dark:text-sky-300" x-text="item.cv_name_snapshot ? @js(__('panel.interview.source_cv', ['name' => '__NAME__'])).replace('__NAME__', item.cv_name_snapshot) : @js(__('panel.interview.general_context'))"></p>
                        </div>
                        <div class="flex shrink-0 items-center gap-4">
                            <div x-show="item.average_score !== null && item.average_score !== undefined" class="text-right">
                                <p class="panel-muted text-[10px] uppercase tracking-wide">{{ __('panel.interview.average_score') }}</p>
                                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" x-text="item.average_score + '/100'"></p>
                            </div>
                            <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400" x-text="openHistoryId === item.id ? @js(__('panel.interview.hide_details')) : @js(__('panel.interview.show_details'))"></span>
                        </div>
                    </button>

                    <div x-show="openHistoryId === item.id" x-cloak class="border-t border-slate-200 p-5 dark:border-slate-800">
                        <p x-show="historyLoadingId === item.id" class="panel-muted text-sm">...</p>
                        <template x-if="details[item.id]">
                            <div>
                                <div class="space-y-4">
                                    <template x-for="(historyQuestion, historyIndex) in details[item.id].questions || []" :key="historyQuestion.id">
                                        <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400" x-text="progressFor(historyIndex, details[item.id].questions.length)"></p>
                                            <h4 class="mt-2 font-semibold" x-text="historyQuestion.question"></h4>
                                            <template x-if="answerFor(details[item.id], historyQuestion.id)">
                                                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_16rem]">
                                                    <div>
                                                        <p class="panel-muted text-xs font-semibold uppercase tracking-wide">{{ __('panel.interview.answer_label') }}</p>
                                                        <p class="mt-1 whitespace-pre-wrap text-sm" x-text="answerFor(details[item.id], historyQuestion.id)?.answer"></p>
                                                        <p class="panel-muted mt-4 text-xs font-semibold uppercase tracking-wide">{{ __('panel.interview.feedback_label') }}</p>
                                                        <p class="mt-1 text-sm" x-text="answerFor(details[item.id], historyQuestion.id)?.feedback"></p>
                                                    </div>
                                                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/60">
                                                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" x-text="answerFor(details[item.id], historyQuestion.id)?.score + '/100'"></p>
                                                        <p class="panel-muted mt-3 text-xs font-semibold uppercase tracking-wide">{{ __('panel.interview.strengths_label') }}</p>
                                                        <ul class="mt-1 space-y-1 text-xs"><template x-for="strength in answerFor(details[item.id], historyQuestion.id)?.strengths || []" :key="strength"><li x-text="'• ' + strength"></li></template></ul>
                                                        <p class="panel-muted mt-3 text-xs font-semibold uppercase tracking-wide">{{ __('panel.interview.improvements_label') }}</p>
                                                        <ul class="mt-1 space-y-1 text-xs"><template x-for="improvement in answerFor(details[item.id], historyQuestion.id)?.improvements || []" :key="improvement"><li x-text="'• ' + improvement"></li></template></ul>
                                                    </div>
                                                </div>
                                            </template>
                                            <p x-show="!answerFor(details[item.id], historyQuestion.id)" class="panel-muted mt-3 text-sm">{{ __('panel.interview.not_answered') }}</p>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" data-interview-retry class="mt-5 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-60" :disabled="busy" @click.stop="retry(item)">
                                    {{ __('panel.interview.retry') }}
                                </button>
                            </div>
                        </template>
                    </div>
                </article>
            </template>
        </div>
    </section>

    <div x-show="showLangModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <div @click.away="showLangModal = false" class="w-full max-w-sm rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-800">
            <h3 class="mb-1 text-lg font-bold text-slate-900 dark:text-white">Mülakat Dili / Interview Language</h3>
            <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Hangi dilde pratik yapmak istersin?</p>
            <div class="flex flex-col gap-3">
                <button type="button" @click="start('tr')" class="flex w-full items-center justify-center gap-3 rounded-xl bg-slate-50 p-4 font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-700/50"><span class="text-2xl">🇹🇷</span> Türkçe Pratik Yap</button>
                <button type="button" @click="start('en')" class="flex w-full items-center justify-center gap-3 rounded-xl bg-slate-50 p-4 font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-700/50"><span class="text-2xl">🇬🇧</span> Practice in English</button>
            </div>
            <button type="button" @click="showLangModal = false" class="mt-5 w-full text-sm font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">İptal</button>
        </div>
    </div>
</div>
@endsection
