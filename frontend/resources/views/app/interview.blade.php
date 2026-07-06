@extends('app.layouts.app')

@section('title', __('panel.interview.title'))

@section('content')
<div class="mx-auto max-w-5xl" x-data="{ questions: {{ Js::from($interview['questions']) }}, idx: 0, answer: '', scored: false, get q() { return this.questions[this.idx]; }, next() { this.idx = (this.idx + 1) % this.questions.length; this.answer = ''; this.scored = false; }, score() { if (this.answer.trim().length > 10) this.scored = true; } }">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.interview.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.interview.subtitle') }}</p>
    </header>

    <section class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="panel-card p-6">
            <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 font-medium text-emerald-700 dark:text-emerald-300" x-text="q.role"></span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700 dark:bg-slate-800 dark:text-slate-300" x-text="q.type"></span>
            </div>
            <h2 class="mb-5 text-xl font-semibold" x-text="q.question"></h2>
            <textarea x-model="answer" rows="8" class="w-full rounded-2xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" :placeholder="@js(__('panel.interview.answer_placeholder'))"></textarea>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <button type="button" @click="score()" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('panel.interview.score_button') }}</button>
                <button type="button" @click="next()" class="panel-btn-secondary text-sm">{{ __('panel.interview.next_button') }}</button>
            </div>
            <div x-show="scored" x-cloak class="mt-5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ __('panel.interview.demo_score') }}: <span x-text="q.score"></span>/100</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300" x-text="q.feedback"></p>
            </div>
        </div>

        <aside class="panel-card h-fit p-5">
            <h2 class="mb-3 font-semibold">{{ __('panel.interview.rubric') }}</h2>
            <ul class="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                @foreach ($interview['rubric'] as $item)
                    <li class="rounded-xl bg-slate-100 px-3 py-2 dark:bg-slate-800">{{ $item }}</li>
                @endforeach
            </ul>
            <p class="panel-muted mt-4 text-xs">{{ __('panel.interview.demo_note') }}</p>
        </aside>
    </section>
</div>
@endsection
