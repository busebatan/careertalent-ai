@extends('app.layouts.app')

@section('title', __('panel.job_matches.title'))

@section('content')
<div class="mx-auto max-w-4xl"
    x-data="panelJobMatches(
        {{ Js::from($seedJobs) }},
        {{ Js::from([
            'analyzeUrl' => route('panel.job-matches.analyze'),
            'csrfToken' => csrf_token(),
            'locale' => app()->getLocale() === 'en' ? 'en-GB' : 'tr-TR',
            'recommendations' => [
                'apply' => __('panel.job_matches.rec_apply'),
                'prepare' => __('panel.job_matches.rec_prepare'),
                'wait' => __('panel.job_matches.rec_wait'),
            ],
            'errors' => [
                'generic' => __('panel.job_matches.error_generic'),
                'duplicate' => __('panel.job_matches.error_duplicate'),
            ],
        ]) }}
    )">

    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.job_matches.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.job_matches.subtitle') }}</p>
    </header>

    <section class="panel-card mb-8 p-5">
        <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('panel.job_matches.add_title') }}</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">{{ __('panel.job_matches.add_desc') }}</p>

        <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="addJob()">
            <label class="sr-only" for="job-url">{{ __('panel.job_matches.url_label') }}</label>
            <input id="job-url" type="url" x-model="jobUrl" :placeholder="@js(__('panel.job_matches.url_placeholder'))"
                class="panel-input-block min-w-0 flex-1" autocomplete="off" :disabled="loading">
            <button type="submit"
                class="shrink-0 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60"
                :disabled="loading || !jobUrl.trim()">
                <span x-show="!loading">{{ __('panel.job_matches.analyze_btn') }}</span>
                <span x-show="loading" x-cloak>{{ __('panel.job_matches.analyzing_btn') }}</span>
            </button>
        </form>

        <p x-show="error" x-cloak x-text="error" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert"></p>

        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/60">
            <p class="mb-2 font-medium text-slate-800 dark:text-slate-200">{{ __('panel.job_matches.profile_skills') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($userSkills as $skill)
                    <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">{{ $skill }}</span>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                {{ __('panel.job_matches.readiness_note', ['score' => $readiness]) }}
            </p>
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ __('panel.job_matches.list_title') }}</h2>
            <span class="text-sm text-slate-500 dark:text-slate-400" x-text="jobs.length + ' {{ __('panel.job_matches.list_count_suffix') }}'"></span>
        </div>

        <div x-show="sortedJobs.length === 0" x-cloak class="panel-card p-8 text-center">
            <p class="mb-1 font-medium text-slate-800 dark:text-slate-200">{{ __('panel.job_matches.empty_title') }}</p>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('panel.job_matches.empty_desc') }}</p>
        </div>

        <div class="space-y-4" x-show="sortedJobs.length > 0">
            <template x-for="job in sortedJobs" :key="job.id">
                <article class="panel-card p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100" x-text="job.title"></h3>
                                <span class="rounded-md px-2 py-0.5 text-xs font-semibold"
                                    :class="recommendationBadgeClass(job)"
                                    x-text="recommendationLabel(job)"></span>
                            </div>
                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                <span x-text="job.company"></span>
                                <span class="text-slate-400 dark:text-slate-500"> · </span>
                                <span x-text="job.source"></span>
                            </p>
                            <p class="mt-2 truncate text-xs text-slate-500 dark:text-slate-400">
                                <a :href="job.url" target="_blank" rel="noopener noreferrer"
                                    class="text-emerald-600 hover:underline dark:text-emerald-400" x-text="job.url"></a>
                            </p>
                            <p class="mt-2 text-xs text-slate-500" x-text="formatDate(job.analyzed_at)"></p>
                        </div>

                        <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('panel.job_matches.match_label') }}</p>
                                <p class="text-3xl font-bold" :class="scoreClass(job.match_score)">
                                    <span x-text="job.match_score"></span>%
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <a :href="job.url" target="_blank" rel="noopener noreferrer" class="panel-outline-btn">
                                    {{ __('panel.job_matches.open_link') }}
                                </a>
                                <button type="button" class="panel-btn-danger" @click="removeJob(job)">
                                    {{ __('panel.job_matches.remove') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                                {{ __('panel.job_matches.matched_skills') }}
                            </p>
                            <template x-if="job.matched_skills.length">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="skill in job.matched_skills" :key="skill">
                                        <span class="rounded bg-white px-2 py-0.5 text-xs text-emerald-800 dark:bg-slate-900 dark:text-emerald-300" x-text="skill"></span>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!job.matched_skills.length" class="text-xs text-slate-500">{{ __('panel.job_matches.no_matched') }}</p>
                        </div>

                        <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">
                                {{ __('panel.job_matches.missing_skills') }}
                            </p>
                            <template x-if="job.missing_skills.length">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="skill in job.missing_skills" :key="skill">
                                        <span class="rounded bg-white px-2 py-0.5 text-xs text-amber-800 dark:bg-slate-900 dark:text-amber-300" x-text="skill"></span>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!job.missing_skills.length" class="text-xs text-slate-500">{{ __('panel.job_matches.no_missing') }}</p>
                        </div>
                    </div>
                </article>
            </template>
        </div>

        <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">{{ __('panel.job_matches.demo_note') }}</p>
    </section>
</div>
@endsection
