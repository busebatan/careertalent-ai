@extends('app.layouts.app')

@section('title', __('panel.job_listings.title'))

@section('content')
<div class="mx-auto max-w-7xl" data-job-listings
    x-data="panelJobListings({{ Js::from($jobListings) }}, {{ Js::from([
        'locale' => app()->getLocale() === 'en' ? 'en-GB' : 'tr-TR',
        'unspecified' => __('panel.job_listings.unspecified'),
        'noDeadline' => __('panel.job_listings.no_deadline'),
        'workplaces' => [
            'onsite' => __('panel.job_listings.workplace_onsite'),
            'hybrid' => __('panel.job_listings.workplace_hybrid'),
            'remote' => __('panel.job_listings.workplace_remote'),
        ],
        'employment' => [
            'full_time' => __('panel.job_listings.employment_full_time'),
            'part_time' => __('panel.job_listings.employment_part_time'),
            'contract' => __('panel.job_listings.employment_contract'),
            'internship' => __('panel.job_listings.employment_internship'),
        ],
        'levels' => [
            'junior' => __('panel.job_listings.level_junior'),
            'mid' => __('panel.job_listings.level_mid'),
            'senior' => __('panel.job_listings.level_senior'),
            'lead' => __('panel.job_listings.level_lead'),
        ],
    ]) }}, {{ Js::from($demoCvDocuments) }}, {{ Js::from($cvVersions) }})"
    x-init="applyUrl = '{{ route('panel.applications.create') }}'"
>
    <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600 dark:text-emerald-400">{{ __('panel.job_listings.eyebrow') }}</p>
            <h1 class="text-3xl font-bold tracking-tight text-slate-950 dark:text-white">{{ __('panel.job_listings.title') }}</h1>
            <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-400">{{ __('panel.job_listings.subtitle') }}</p>
        </div>
        <a href="{{ route('panel.applications') }}" class="panel-btn-secondary inline-flex shrink-0 items-center gap-2 text-sm">
            <i data-lucide="files" class="h-4 w-4" aria-hidden="true"></i>
            {{ __('panel.job_listings.my_applications') }}
        </a>
    </header>

    @if ($listingsError)
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200" role="status">
            {{ __('panel.job_listings.load_error') }} {{ __('panel.job_listings.demo_available') }}
        </div>
    @endif

    <section class="panel-card mb-6 p-4 sm:p-5" aria-label="{{ __('panel.job_listings.filters') }}">
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_220px]">
            <label class="relative block">
                <span class="sr-only">{{ __('panel.job_listings.search') }}</span>
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <input type="search" x-model="query" class="panel-input-block w-full pl-10" placeholder="{{ __('panel.job_listings.search_placeholder') }}">
            </label>
            <label>
                <span class="sr-only">{{ __('panel.job_listings.workplace') }}</span>
                <select x-model="workplace" class="panel-input-block w-full">
                    <option value="">{{ __('panel.job_listings.all_workplaces') }}</option>
                    <option value="onsite">{{ __('panel.job_listings.workplace_onsite') }}</option>
                    <option value="hybrid">{{ __('panel.job_listings.workplace_hybrid') }}</option>
                    <option value="remote">{{ __('panel.job_listings.workplace_remote') }}</option>
                </select>
            </label>
            <label>
                <span class="sr-only">{{ __('panel.job_listings.employment_type') }}</span>
                <select x-model="employment" class="panel-input-block w-full">
                    <option value="">{{ __('panel.job_listings.all_employment_types') }}</option>
                    <option value="full_time">{{ __('panel.job_listings.employment_full_time') }}</option>
                    <option value="part_time">{{ __('panel.job_listings.employment_part_time') }}</option>
                    <option value="contract">{{ __('panel.job_listings.employment_contract') }}</option>
                    <option value="internship">{{ __('panel.job_listings.employment_internship') }}</option>
                </select>
            </label>
        </div>
    </section>

    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ __('panel.job_listings.open_positions') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400"><span x-text="filteredItems.length"></span> {{ __('panel.job_listings.result_suffix') }}</p>
    </div>

    <section class="grid gap-5 lg:grid-cols-2">
        <template x-for="job in filteredItems" :key="job.position.id">
            <article class="panel-card group flex min-h-72 flex-col overflow-hidden p-5 transition hover:-translate-y-0.5 hover:border-emerald-400/60 hover:shadow-lg dark:hover:border-emerald-700"
                :data-job-listing-demo="job.is_demo ? 'true' : null">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-sm font-bold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                            x-text="job.organization.name.slice(0, 2).toLocaleUpperCase()"></div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-600 dark:text-slate-300" x-text="job.organization.name"></p>
                            <h3 class="mt-1 text-xl font-bold leading-tight text-slate-950 dark:text-white" x-text="job.position.title"></h3>
                        </div>
                    </div>
                    <span x-show="job.is_demo" class="shrink-0 rounded-full border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300">{{ __('panel.job_listings.demo_badge') }}</span>
                </div>

                <div class="mb-5 flex flex-wrap gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 dark:bg-slate-800" x-text="workplaceLabel(job.position.workplace_type)"></span>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 dark:bg-slate-800" x-text="employmentLabel(job.position.employment_type)"></span>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 dark:bg-slate-800" x-text="job.position.location || labels.unspecified"></span>
                </div>

                <p class="line-clamp-3 flex-1 text-sm leading-6 text-slate-600 dark:text-slate-400" x-text="job.position.description || job.position.responsibilities || '{{ __('panel.job_listings.description_missing') }}'"></p>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('panel.job_listings.deadline') }} <span class="font-medium text-slate-700 dark:text-slate-300" x-text="formatDeadline(job.position.application_deadline)"></span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" class="panel-btn-secondary text-sm" @click="openDetails(job)">{{ __('panel.job_listings.view_details') }}</button>
                        <button type="button" class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500" @click="beginApplication(job)">{{ __('panel.job_listings.apply') }}</button>
                    </div>
                </div>
            </article>
        </template>
    </section>

    <section x-show="filteredItems.length === 0" x-cloak class="panel-card mt-5 p-10 text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
            <i data-lucide="search-x" class="h-5 w-5 text-slate-500" aria-hidden="true"></i>
        </div>
        <h2 class="font-semibold text-slate-950 dark:text-white">{{ __('panel.job_listings.empty_title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('panel.job_listings.empty_description') }}</p>
    </section>

    <div x-show="activeJob" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @keydown.escape.window="closeDetails()" role="dialog" aria-modal="true" aria-labelledby="job-detail-title">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900" @click.outside="closeDetails()">
            <template x-if="activeJob">
                <div>
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300" x-text="activeJob.organization.name"></p>
                                <span x-show="activeJob.is_demo" class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">{{ __('panel.job_listings.demo_badge') }}</span>
                            </div>
                            <h2 id="job-detail-title" class="text-2xl font-bold text-slate-950 dark:text-white" x-text="activeJob.position.title"></h2>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                <span x-text="activeJob.position.department || labels.unspecified"></span> ·
                                <span x-text="levelLabel(activeJob.position.level)"></span> ·
                                <span x-text="activeJob.position.location || labels.unspecified"></span>
                            </p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="closeDetails()" aria-label="{{ __('panel.job_listings.close') }}">
                            <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="space-y-5 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        <div><h3 class="mb-1 font-semibold text-slate-950 dark:text-white">{{ __('panel.job_listings.about_role') }}</h3><p x-text="activeJob.position.description || '{{ __('panel.job_listings.description_missing') }}'"></p></div>
                        <div x-show="activeJob.position.responsibilities"><h3 class="mb-1 font-semibold text-slate-950 dark:text-white">{{ __('panel.job_listings.responsibilities') }}</h3><p class="whitespace-pre-line" x-text="activeJob.position.responsibilities"></p></div>
                        <div>
                            <h3 class="mb-2 font-semibold text-slate-950 dark:text-white">{{ __('panel.job_listings.skills') }}</h3>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="skill in skills(activeJob)" :key="skill">
                                    <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300" x-text="skill"></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                        <p class="text-xs text-slate-500">{{ __('panel.job_listings.application_time') }}</p>
                        <button type="button" class="inline-flex rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500" @click="beginApplication(activeJob); closeDetails()">{{ __('panel.job_listings.apply_now') }}</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ─── Platform İçi Başvuru Modalı (tüm ilanlar) ─────────────────────────── --}}
    <div id="application-modal" x-show="demoApplicationOpen" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
        @keydown.escape.window="closeDemoApplication()"
        role="dialog" aria-modal="true" aria-labelledby="application-modal-title">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900"
            @click.outside="closeDemoApplication()">

            {{-- Başlık --}}
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400"
                        x-text="applicationJob?.is_demo ? '{{ __('panel.job_listings.demo_application_eyebrow') }}' : '{{ __('panel.job_listings.apply') }}'"></p>
                    <h2 id="application-modal-title" class="text-xl font-bold text-slate-950 dark:text-white"
                        x-text="applicationJob?.position?.title || '{{ __('panel.job_listings.apply') }}'"></h2>
                    <p class="mt-0.5 text-sm font-medium text-emerald-700 dark:text-emerald-300"
                        x-text="applicationJob?.organization?.name || ''"></p>
                </div>
                <button type="button"
                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                    @click="closeDemoApplication()"
                    :aria-label="'{{ __('panel.job_listings.close') }}'"
                    id="application-modal-close">
                    <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Gövde: Başvuru formu --}}
            <div x-show="!applicationSubmitted && !demoSubmitted">

                {{-- CV Sürümleri varsa --}}
                <template x-if="cvVersions.length > 0">
                    <div class="space-y-4">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('panel.job_listings.select_cv_version') }}</span>
                            <select x-model="selectedVersionId" class="panel-input-block w-full" id="cv-version-select">
                                <template x-for="version in cvVersions" :key="version.id">
                                    <option :value="version.id"
                                        x-text="version.version_name + (version.is_main ? ' ★' : '')"></option>
                                </template>
                            </select>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-600 transition hover:border-emerald-300 dark:border-slate-700 dark:text-slate-300" id="application-consent-label">
                            <input type="checkbox" x-model="demoConsent"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                id="application-consent">
                            <span>{{ __('panel.job_listings.demo_consent') }}</span>
                        </label>

                        {{-- Hata mesajı --}}
                        <p x-show="applicationError"
                            class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300"
                            x-text="applicationError" role="alert"></p>

                        <template x-if="!applicationJob?.is_demo">
                            <button type="button"
                                id="complete-application-btn"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!selectedVersionId || !demoConsent || applicationSubmitting"
                                @click="completeApplication()">
                                <i x-show="applicationSubmitting" data-lucide="loader-2" class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                                <span x-text="applicationSubmitting ? '{{ __('panel.job_listings.applying') }}' : '{{ __('panel.job_listings.complete_application') }}'"></span>
                            </button>
                        </template>
                        <template x-if="applicationJob?.is_demo">
                            <button type="button"
                                id="complete-demo-btn"
                                class="w-full rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!selectedVersionId || !demoConsent"
                                @click="completeDemoApplication()">
                                {{ __('panel.job_listings.complete_demo') }}
                            </button>
                        </template>
                    </div>
                </template>

                {{-- CV Sürümü yoksa (eski cvDocuments'a dön) --}}
                <template x-if="cvVersions.length === 0 && cvDocuments.length > 0">
                    <div class="space-y-4">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('panel.job_listings.select_cv') }}</span>
                            <select x-model="selectedCvId" class="panel-input-block w-full" id="cv-doc-select">
                                <template x-for="document in cvDocuments" :key="document.id">
                                    <option :value="document.id" x-text="document.display_name || document.original_name || document.id"></option>
                                </template>
                            </select>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                            <input type="checkbox" x-model="demoConsent" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>{{ __('panel.job_listings.demo_consent') }}</span>
                        </label>
                        <button type="button"
                            class="w-full rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!selectedCvId || !demoConsent"
                            @click="completeDemoApplication()">
                            {{ __('panel.job_listings.complete_demo') }}
                        </button>
                    </div>
                </template>

                {{-- Ne sürüm ne de doc varsa --}}
                <template x-if="cvVersions.length === 0 && cvDocuments.length === 0">
                    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                        <p>{{ __('panel.job_listings.no_cv_versions') }}</p>
                        <a href="{{ route('panel.cv-builder') }}" class="mt-3 inline-flex font-semibold text-emerald-700 hover:underline dark:text-emerald-300">{{ __('panel.job_listings.go_to_cv') }}</a>
                    </div>
                </template>
            </div>

            {{-- Başarı: Gerçek başvuru tamamlandı --}}
            <div x-show="applicationSubmitted" x-cloak class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/50">
                    <i data-lucide="check-circle" class="h-6 w-6 text-emerald-700 dark:text-emerald-300" aria-hidden="true"></i>
                </div>
                <h3 class="font-semibold text-slate-950 dark:text-white">{{ __('panel.job_listings.application_submitted') }}</h3>
                <div class="mt-4 flex flex-col items-center gap-2 sm:flex-row sm:justify-center">
                    <a href="{{ route('panel.applications') }}"
                        class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500">
                        {{ __('panel.job_listings.go_to_applications') }}
                    </a>
                    <button type="button" class="panel-btn-secondary text-sm" @click="closeDemoApplication()">{{ __('panel.job_listings.close') }}</button>
                </div>
            </div>

            {{-- Başarı: Demo başvuru tamamlandı (API kaydı yok) --}}
            <div x-show="demoSubmitted" x-cloak class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/50">
                    <i data-lucide="check" class="h-6 w-6 text-emerald-700 dark:text-emerald-300" aria-hidden="true"></i>
                </div>
                <h3 class="font-semibold text-slate-950 dark:text-white">{{ __('panel.job_listings.demo_completed') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('panel.job_listings.demo_not_persisted') }}</p>
                <button type="button" class="panel-btn-secondary mt-5 text-sm" @click="closeDemoApplication()">{{ __('panel.job_listings.close') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
