@extends('app.layouts.app')

@section('title', __('panel.job_matches.title'))

@section('content')
<div class="mx-auto max-w-4xl" x-data="panelJobMatches({{ Js::from($seedJobs) }}, {{ Js::from([
    'analyzeUrl' => route('panel.job-matches.analyze'),
    'statusUrl' => route('panel.job-matches.status', ['jobId' => '__JOB__']),
    'saveUrl' => route('panel.job-matches.save', ['jobId' => '__JOB__']),
    'appliedUrl' => route('panel.job-matches.mark-applied', ['jobId' => '__JOB__']),
    'applyUrl' => route('panel.job-matches.apply', ['jobId' => '__JOB__']),
    'deleteUrl' => route('panel.job-matches.destroy', ['jobId' => '__JOB__']),
    'cvStatusUrl' => route('panel.cv.analysis-status', ['analysisId' => '__ANALYSIS__']),
    'csrfToken' => csrf_token(), 'locale' => app()->getLocale() === 'en' ? 'en-GB' : 'tr-TR',
    'errors' => ['generic' => __('panel.job_matches.error_generic'), 'timeout' => __('panel.job_matches.error_timeout')],
    'labels' => [
        'readiness' => __('panel.job_matches.readiness_note', ['score' => '__SCORE__']),
        'cvFailed' => __('panel.job_matches.cv_failed'),
    ],
]) }}, {{ Js::from($latestAnalysis) }})">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.job_matches.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.job_matches.subtitle') }}</p>
    </header>

    <section class="panel-card mb-8 p-5">
        <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('panel.job_matches.add_title') }}</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">{{ __('panel.job_matches.add_desc') }}</p>
        <form class="space-y-3" @submit.prevent="addJob()">
            <label class="sr-only" for="job-url">{{ __('panel.job_matches.url_label') }}</label>
            <input id="job-url" type="url" x-model="jobUrl" placeholder="{{ __('panel.job_matches.url_placeholder') }}" class="panel-input-block w-full" :disabled="loading">
            <div class="flex items-center gap-3"><div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div><span class="text-xs text-slate-500">{{ __('panel.job_matches.or') }}</span><div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div></div>
            <label class="sr-only" for="job-text">{{ __('panel.job_matches.text_label') }}</label>
            <textarea id="job-text" x-model="jobText" rows="5" placeholder="{{ __('panel.job_matches.text_placeholder') }}" class="panel-input-block w-full resize-y" :disabled="loading"></textarea>
            <button type="submit" disabled class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || !cvReady || (!jobUrl.trim() && jobText.trim().length < 40)">
                <span x-show="!loading">{{ __('panel.job_matches.analyze_btn') }}</span><span x-show="loading" x-cloak>{{ __('panel.job_matches.analyzing_btn') }}</span>
            </button>
        </form>
        <p x-show="error" x-cloak x-text="error" class="mt-3 text-sm text-red-600 dark:text-red-400" role="alert"></p>
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/60">
            <p class="mb-2 font-medium text-slate-800 dark:text-slate-200">{{ __('panel.job_matches.profile_skills') }}</p>
            <div x-show="cvReady" class="flex flex-wrap gap-2">
                <template x-for="skill in cv.skills" :key="skill.name"><span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300" x-text="skill.name"></span></template>
                <span x-show="!cv.skills.length" class="text-xs text-slate-500">{{ __('panel.job_matches.no_cv_skills') }}</span>
            </div>
            <p x-show="cv.status === 'queued' || cv.status === 'running'" x-cloak class="text-xs text-amber-600 dark:text-amber-400">{{ __('panel.job_matches.cv_processing') }}</p>
            <p x-show="(cv.status === 'queued' || cv.status === 'running') && cv.error_message" x-cloak class="mt-1 text-xs text-red-600 dark:text-red-400">{{ __('panel.job_matches.cv_status_error') }}</p>
            <p x-show="cv.status === 'failed'" x-cloak class="text-xs text-red-600 dark:text-red-400"><span x-text="cv.error_message || config.labels.cvFailed"></span> <a href="{{ route('panel.cv-builder') }}" class="font-medium underline">{{ __('panel.job_matches.cv_center') }}</a></p>
            <p x-show="cv.status === 'missing'" x-cloak class="text-xs text-slate-500">{{ __('panel.job_matches.cv_required') }} <a href="{{ route('panel.cv-builder') }}" class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">{{ __('panel.job_matches.cv_center') }}</a></p>
            <p x-show="cvReady" x-cloak class="mt-3 text-xs text-slate-500 dark:text-slate-400" x-text="config.labels.readiness.replace('__SCORE__', cv.readiness)"></p>
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-lg font-semibold">{{ __('panel.job_matches.list_title') }}</h2><span class="text-sm text-slate-500" x-text="jobs.length + ' {{ __('panel.job_matches.list_count_suffix') }}'"></span></div>
        <div x-show="!sortedJobs.length" x-cloak class="panel-card p-8 text-center"><p class="mb-1 font-medium">{{ __('panel.job_matches.empty_title') }}</p><p class="text-sm text-slate-600 dark:text-slate-400">{{ __('panel.job_matches.empty_desc') }}</p></div>
        <div class="space-y-4">
            <template x-for="job in sortedJobs" :key="job.id"><article class="panel-card p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0"><h3 class="font-semibold" x-text="job.title || '{{ __('panel.job_matches.generic_title') }}'"></h3><p class="text-sm text-slate-600 dark:text-slate-400"><span x-text="job.company"></span><span x-show="job.source"> · </span><span x-text="job.source"></span></p><a x-show="job.source_url" :href="job.source_url" target="_blank" rel="noopener noreferrer" class="mt-2 block truncate text-xs text-emerald-600" x-text="job.source_url"></a><p class="mt-2 text-xs text-slate-500" x-text="formatDate(job.created_at)"></p><p x-show="job.source_cv_file_name" x-cloak class="mt-1 text-xs text-slate-500"><span class="font-medium">{{ __('panel.job_matches.analysis_cv') }}:</span> <span x-text="job.source_cv_file_name"></span></p></div>
                    <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end"><div x-show="job.status === 'ready'" x-cloak class="text-right"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('panel.job_matches.match_label') }}</p><p class="text-3xl font-bold" :class="scoreClass(job.match_score)"><span x-text="job.match_score"></span>%</p></div><div class="flex gap-2"><button x-show="!job.saved && job.status === 'ready'" type="button" class="panel-outline-btn" @click="saveJob(job)">{{ __('panel.job_matches.save') }}</button><button x-show="job.saved && !job.application_created" type="button" class="panel-outline-btn" @click="markApplied(job)">{{ __('panel.job_matches.mark_applied') }}</button><span x-show="job.application_created" class="rounded-xl bg-emerald-500/10 px-3 py-2 text-xs text-emerald-700">{{ __('panel.job_matches.application_created') }}</span><button type="button" class="panel-btn-danger" @click="removeJob(job)">{{ __('panel.job_matches.remove') }}</button></div></div>
                </div>
                <p x-show="job.status === 'queued' || job.status === 'running'" x-cloak class="mt-4 text-sm text-amber-600">{{ __('panel.job_matches.analyzing_ai') }}</p>
                <div x-show="job.status === 'failed'" x-cloak class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-300" role="alert"><p class="font-semibold">{{ __('panel.job_matches.analysis_failed') }}</p><p class="mt-1" x-text="job.error_message || config.errors.generic"></p></div>
                <div x-show="job.status === 'ready'" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/20"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ __('panel.job_matches.matched_skills') }}</p><div class="flex flex-wrap gap-1.5"><template x-for="skill in job.matched_skills" :key="skill"><span class="rounded bg-white px-2 py-0.5 text-xs text-emerald-800 dark:bg-slate-900 dark:text-emerald-300" x-text="skill"></span></template><p x-show="!job.matched_skills.length" class="text-xs text-slate-500">{{ __('panel.job_matches.no_matched') }}</p></div></div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 dark:border-amber-900/50 dark:bg-amber-950/20"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700">{{ __('panel.job_matches.missing_skills') }}</p><div class="flex flex-wrap gap-1.5"><template x-for="skill in job.missing_skills" :key="skill"><span class="rounded bg-white px-2 py-0.5 text-xs text-amber-800 dark:bg-slate-900 dark:text-amber-300" x-text="skill"></span></template><p x-show="!job.missing_skills.length" class="text-xs text-slate-500">{{ __('panel.job_matches.no_missing') }}</p></div></div>
                </div>
                <div x-show="job.status === 'ready'" class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700"><h4 class="mb-3 text-sm font-semibold">{{ __('panel.job_matches.cv_suggestions') }}</h4><div class="space-y-2"><template x-for="suggestion in job.cv_suggestions" :key="suggestion.id"><label class="flex gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" :class="!suggestion.safe_to_apply && 'opacity-70'"><input type="checkbox" class="mt-1" x-model="job.selected" :value="suggestion.id" :disabled="!suggestion.safe_to_apply"><span><span class="text-sm font-medium" x-text="suggestion.title"></span><span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase dark:bg-slate-800" x-text="suggestion.action"></span><span class="block text-xs text-slate-500" x-text="suggestion.reason"></span><span class="mt-1 block text-sm" x-text="suggestion.suggested_text"></span><span x-show="!suggestion.safe_to_apply" class="mt-1 block text-xs text-amber-600">{{ __('panel.job_matches.development_only') }}</span></span></label></template></div><button type="button" class="mt-3 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="!job.selected.length || ['queued','running'].includes(job.apply_status)" @click="applyJob(job)">{{ __('panel.job_matches.apply_selected') }}</button><p x-show="['queued','running'].includes(job.apply_status)" class="mt-2 text-xs text-amber-600">{{ __('panel.job_matches.reanalyzing') }}</p><p x-show="job.apply_status === 'ready'" class="mt-2 text-xs text-emerald-600">{{ __('panel.job_matches.applied') }}</p></div>
            </article></template>
        </div>
    </section>

    <!-- Apply Modal -->
    <div x-show="showApplyModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
        @keydown.escape.window="showApplyModal = false">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900"
            @click.outside="showApplyModal = false">
            
            <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-emerald-50 p-2 dark:bg-emerald-950/30">
                    <i data-lucide="send" class="h-6 w-6 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ app()->getLocale() === 'en' ? 'Position Application' : 'Pozisyona Başvur' }}
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400" x-show="activeJobForApply" x-text="activeJobForApply.title + ' - ' + activeJobForApply.company"></p>
                </div>
            </div>

            <div class="space-y-4">
                <template x-if="loadingVersions">
                    <div class="py-4 text-center text-sm text-slate-500">
                        <span class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-emerald-600 border-t-transparent mr-2"></span>
                        {{ app()->getLocale() === 'en' ? 'Loading resume versions...' : 'Özgeçmiş sürümleri yükleniyor...' }}
                    </div>
                </template>

                <template x-if="!loadingVersions && cvVersions.length === 0">
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300">
                        <p class="font-semibold mb-1">
                            {{ app()->getLocale() === 'en' ? 'No CV Versions Found' : 'CV Sürümü Bulunamadı' }}
                        </p>
                        <p class="text-xs">
                            {{ app()->getLocale() === 'en' ? 'You do not have any saved CV versions. Your main profile will be used as a backup snapshot copy.' : 'Kaydedilmiş bir CV sürümünüz bulunmuyor. Başvurunuz için ana profil verileriniz yedek snapshot kopyası olarak kullanılacaktır.' }}
                        </p>
                    </div>
                </template>

                <template x-if="!loadingVersions && cvVersions.length > 0">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                            {{ app()->getLocale() === 'en' ? 'Choose Resume Version' : 'Başvuru Yapılacak Özgeçmiş Sürümü' }}
                        </label>
                        <select x-model="selectedCvVersionId" class="panel-input-block mt-2 w-full">
                            <template x-for="version in cvVersions" :key="version.id">
                                <option :value="version.id" :selected="version.id === selectedCvVersionId"
                                    x-text="version.version_name + ' (' + version.language.toUpperCase() + ')' + (version.is_main ? ' [Ana Sürüm]' : '')">
                                </option>
                            </template>
                        </select>
                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            {{ app()->getLocale() === 'en' ? 'Only the snapshot copy of this selected version will be shared with the company. The company will not see your other versions.' : 'Kurum adayı incelerken yalnızca seçtiğiniz bu sürümün statik snapshot kopyasını görebilecektir. Diğer sürümleriniz gizli kalır.' }}
                        </p>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="showApplyModal = false"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    {{ app()->getLocale() === 'en' ? 'Cancel' : 'İptal' }}
                </button>
                <button type="button" @click="confirmApply()"
                    class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition shadow-sm hover:shadow active:scale-95">
                    {{ app()->getLocale() === 'en' ? 'Submit Application' : 'Başvuruyu Gönder' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
