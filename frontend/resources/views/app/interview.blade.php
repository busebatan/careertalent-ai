@extends('app.layouts.app')
@section('title', __('panel.interview.title'))
@section('content')
<div class="mx-auto max-w-5xl" x-data="careerInterview({{ Js::from($interview) }}, @js(route('panel.interview.start')), @js(route('panel.interview.score', ['interviewId' => '__INTERVIEW_ID__'])), {{ Js::from(['failed' => __('panel.interview.failed')]) }})">
    <header class="mb-8 flex items-start justify-between gap-4"><div><h1 class="mb-1 text-2xl font-bold">{{ __('panel.interview.title') }}</h1><p class="text-slate-600 dark:text-slate-400">{{ __('panel.interview.subtitle') }}</p></div><button type="button" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-60" :disabled="busy" @click="start()" x-text="interview ? @js(__('panel.interview.restart')) : @js(__('panel.interview.start'))"></button></header>
    @if ($interviewError)<p class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-700">{{ $interviewError }}</p>@endif
    <p x-show="error" x-text="error" class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-700"></p>
    <section x-show="!interview" class="panel-card p-8 text-center"><p class="panel-muted">{{ __('panel.interview.empty') }}</p></section>
    <section x-show="interview && question" x-cloak class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="panel-card p-6"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-600" x-text="question?.competency"></p><h2 class="mb-4 text-xl font-semibold" x-text="question?.question"></h2><p class="panel-muted mb-4 text-sm" x-text="question?.guidance"></p><textarea x-model="answer" rows="8" class="panel-input-block w-full rounded-2xl" :placeholder="@js(__('panel.interview.answer_placeholder'))"></textarea><div class="mt-4 flex gap-3"><button type="button" :disabled="busy || answer.trim().length < 20" @click="score()" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-60">{{ __('panel.interview.score_button') }}</button><button type="button" @click="next()" class="panel-btn-secondary text-sm">{{ __('panel.interview.next_button') }}</button></div></div>
        <aside class="panel-card h-fit p-5"><h2 class="mb-3 font-semibold">{{ __('panel.interview.ai_feedback') }}</h2><div x-show="!result" class="panel-muted text-sm">{{ __('panel.interview.feedback_empty') }}</div><div x-show="result" x-cloak><p class="text-3xl font-bold text-emerald-600" x-text="result?.score + '/100'"></p><p class="mt-3 text-sm" x-text="result?.feedback"></p><ul class="mt-3 space-y-1 text-xs text-slate-600"><template x-for="item in result?.improvements || []" :key="item"><li x-text="'• ' + item"></li></template></ul></div></aside>
    </section>
</div>
@endsection
