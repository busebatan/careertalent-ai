@extends('app.layouts.app')

@section('title', __('panel.cv_builder.title'))

@push('head')
<style>@include('app.partials.cv-builder-styles')</style>
@endpush

@section('content')
<div class="mx-auto max-w-7xl"
    x-data="cvBuilder({{ Js::from($cvDraft) }}, {{ Js::from($cvLabels) }}, @js(app()->getLocale()), @js($hasCvAnalysis ?? false), @js($cvFileName ?? ''), @js(route('panel.cv.analyze-builder')), @js(route('panel.cv.clear')), @js(route('panel.cv.analysis-status', ['analysisId' => '__ANALYSIS_ID__'])), @js(route('panel.cv.archive-generated')), @js($restoredFromHistory ?? false), @js(route('panel.cv.analysis-stream', ['analysisId' => '__ANALYSIS_ID__'])), @js($analysisStatus ?? ''), @js($analysisId ?? ''))">

    <header class="mb-6">
        <div>
            <h1 class="mb-1 text-2xl font-bold">{{ __('panel.cv_builder.title') }}</h1>
            <p class="text-slate-600 dark:text-slate-400">{{ __('panel.cv_builder.subtitle') }}</p>
        </div>
    </header>

    @if (! empty($builderImportMeta))
        <div data-cv-builder-import-notice
            class="mb-6 rounded-2xl border border-amber-400/40 bg-amber-50 px-5 py-4 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <p class="font-semibold">{{ __('panel.cv_builder.import_notice_title') }}</p>
            <p class="mt-1 text-sm">{{ __('panel.cv_builder.import_source', ['name' => $builderImportMeta['source_file_name'] ?? 'CV']) }}</p>
            @if (! empty($builderImportMissingFields))
                <p class="mt-2 text-sm">{{ __('panel.cv_builder.import_notice_desc', ['count' => count($builderImportMissingFields)]) }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($builderImportMissingFields as $field)
                        <span class="rounded-full border border-amber-400/50 bg-white/70 px-2.5 py-1 text-xs font-medium dark:bg-slate-950/30">
                            {{ data_get(__('panel.cv_builder.import_fields'), $field, $field) }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- Sürüm Oluşturma Modalı -->
    <div x-show="showVersionCreateModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
        @keydown.escape.window="showVersionCreateModal = false">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900"
            @click.outside="showVersionCreateModal = false">
            <h2 class="mb-2 text-lg font-bold text-slate-900 dark:text-white">
                {{ app()->getLocale() === 'en' ? 'Save CV Version' : 'CV Sürümü Kaydet' }}
            </h2>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                {{ app()->getLocale() === 'en' ? 'This will save the current builder content for the selected language as a standalone version.' : 'Bu işlem, seçtiğiniz dildeki mevcut editör içeriğini bağımsız bir sürüm olarak kaydeder.' }}
            </p>

            <template x-if="versionError">
                <div class="mb-4 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/30 dark:text-red-300" x-text="versionError"></div>
            </template>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        {{ app()->getLocale() === 'en' ? 'Version Name' : 'Sürüm Adı' }}
                    </label>
                    <input type="text" x-model="newVersionName" placeholder="Örn: Backend Developer TR, Full Stack EN"
                        class="panel-input-block mt-1 w-full" maxlength="160">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        {{ app()->getLocale() === 'en' ? 'Language Source' : 'Kaynak Dil' }}
                    </label>
                    <select x-model="newVersionLang" class="panel-input-block mt-1 w-full">
                        <option value="tr">{{ app()->getLocale() === 'en' ? 'Turkish Content (TR)' : 'Türkçe İçerik (TR)' }}</option>
                        <option value="en">{{ app()->getLocale() === 'en' ? 'English Content (EN)' : 'İngilizce İçerik (EN)' }}</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 py-1">
                    <input type="checkbox" id="newVersionIsMain" x-model="newVersionIsMain"
                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-800">
                    <label for="newVersionIsMain" class="text-sm text-slate-700 dark:text-slate-300">
                        {{ app()->getLocale() === 'en' ? 'Set as Main CV Version' : 'Ana CV Sürümü Yap' }}
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="showVersionCreateModal = false"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    {{ app()->getLocale() === 'en' ? 'Cancel' : 'İptal' }}
                </button>
                <button type="button" @click="createVersionFromCurrent()"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition">
                    {{ app()->getLocale() === 'en' ? 'Save' : 'Kaydet' }}
                </button>
            </div>
        </div>
    </div>
    @php($currentIsUploaded = is_array($currentCv ?? null) && ($currentCv['kind'] ?? null) === 'uploaded')
    @php($currentIsGenerated = is_array($currentCv ?? null) && ($currentCv['kind'] ?? null) === 'generated')

    @if (empty($skillRadar))
    <section id="cv-analiz-yukle"
        class="panel-card mb-8 overflow-hidden p-6 lg:p-8"
        data-cv-analysis-upload
        x-data="profileCvUpload(@js(app()->getLocale()), @js(route('panel.cv.analyze')), @js(route('panel.cv.analysis-status', ['analysisId' => '__ANALYSIS_ID__'])), '', '', @js(route('panel.cv.analysis-stream', ['analysisId' => '__ANALYSIS_ID__'])))">
        <div class="mb-6">
            <h2 class="mb-2 font-semibold">{{ __('panel.profile.cv_file_title') }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('panel.cv_builder.upload_desc') }}</p>
        </div>

        @if ($currentIsUploaded)
            <div data-cv-current-file x-show="saveStatus !== 'saving'"
                x-data="cvBuilderImport(@js($currentCv), {
                    statusUrl: @js(route('panel.cv.builder-draft.status', ['documentId' => $currentCv['id']])),
                    queueUrl: @js(route('panel.cv.builder-draft.queue', ['documentId' => $currentCv['id']])),
                    openUrl: @js(route('panel.cv-builder', ['cvDocument' => $currentCv['id']])),
                    labels: @js(['failed' => __('panel.profile.cv_builder_import_failed'), 'timeout' => __('panel.profile.cv_builder_import_timeout')])
                })"
                class="mb-4 flex flex-col gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ $currentCv['display_name'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('panel.profile.last_upload', ['date' => \Illuminate\Support\Carbon::parse($currentCv['created_at'])->format('d.m.Y H:i')]) }}</p>
                    <p x-show="pending" x-cloak class="mt-2 text-xs font-medium text-sky-700 dark:text-sky-300" role="status">
                        {{ __('panel.profile.cv_builder_import_preparing') }}
                    </p>
                    <p x-show="ready" x-cloak class="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-300" role="status">
                        {{ __('panel.profile.cv_builder_import_ready') }}
                    </p>
                    <p x-show="error" x-cloak x-text="error" class="mt-2 text-xs font-medium text-red-700 dark:text-red-300" role="alert"></p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <a x-show="ready" x-cloak href="{{ route('panel.cv-builder', ['cvDocument' => $currentCv['id']]) }}"
                        class="font-medium text-sky-600 hover:underline dark:text-sky-400">
                        {{ __('panel.profile.cv_builder_import_open') }}
                    </a>
                    <button x-show="canQueue" x-cloak type="button" @click.stop="queue()" :disabled="busy"
                        class="font-medium text-sky-600 hover:underline disabled:opacity-60 dark:text-sky-400">
                        <span x-text="status === 'failed' ? @js(__('panel.profile.cv_builder_import_retry')) : @js(__('panel.profile.cv_builder_import_create'))"></span>
                    </button>
                    <button type="button" @click.stop="resetOpen = true"
                        class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                        {{ __('panel.skill_radar.clear_cv') }}
                    </button>
                </div>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-stretch">
            <div class="min-w-0">
                <p x-show="loading" x-cloak
                    class="mb-4 rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-800 dark:text-sky-200"
                    role="status">
                    {{ __('panel.profile.cv_analyzing') }}
                </p>
                <p x-show="error" x-cloak
                    class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200"
                    x-text="error" role="alert"></p>

                <label class="panel-upload-zone min-h-36"
                    :class="[
                        loading ? 'pointer-events-none opacity-60' : '',
                        dragOver ? 'panel-upload-zone-active' : '',
                    ]"
                    @dragover.prevent="onDragOver($event)"
                    @dragleave.prevent="onDragLeave($event)"
                    @drop.prevent="onDrop($event)">
                    <i data-lucide="file-text" class="mb-2 h-8 w-8 text-emerald-500" aria-hidden="true"></i>
                    <span class="mb-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('panel.profile.upload_drag') }}</span>
                    <span class="text-xs text-slate-500">{{ __('panel.profile.upload_hint') }}</span>
                    <input type="file" accept="application/pdf,.pdf" class="hidden"
                        :disabled="loading" @change="onFileSelect($event)">
                </label>
            </div>

            <div x-show="loading || (saveStatus !== 'saving' && @js($currentIsUploaded && ! empty($skillRadar)))" x-cloak
                class="flex min-h-36 items-stretch lg:w-44">
                <div x-show="loading" x-cloak
                    class="panel-card flex w-full flex-col items-center justify-center border-sky-500/20 bg-sky-500/5 px-5 py-4 text-center dark:bg-sky-500/10"
                    data-cv-analysis-pending role="status">
                    <i data-lucide="loader-circle" class="mb-2 h-6 w-6 animate-spin text-sky-500" aria-hidden="true"></i>
                    <p class="text-sm font-medium text-sky-700 dark:text-sky-300">{{ __('panel.profile.cv_analyzing') }}</p>
                </div>

                @if ($currentIsUploaded && ! empty($skillRadar))
                    <div x-show="!loading"
                        class="panel-card flex w-full flex-col items-center justify-center border-emerald-500/20 bg-emerald-500/5 px-5 py-4 text-center dark:bg-emerald-500/10"
                        data-cv-analysis-score>
                        <p class="panel-muted text-xs uppercase tracking-wide">{{ __('panel.skill_radar.overall') }}</p>
                        <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">%{{ $skillRadar['overall_match'] }}</p>
                        <a href="{{ route('panel.career-ladder') }}" class="mt-1 inline-block text-xs text-emerald-600 hover:underline dark:text-emerald-400">
                            {{ __('panel.skill_radar.view_ladder') }} →
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>

        @include('app.partials.career-reset-modal', ['resetAction' => 'clearCvAnalysis()'])
    @else
        @include('app.partials.skill-radar-chart', [
            'skillRadar' => $skillRadar,
            'cvFileName' => $cvFileName ?? null,
            'fromApi' => $hasCvAnalysis ?? false,
            'collapsible' => true,
            'showClearInline' => true,
            'radarAlignment' => 'intro-centered',
        ])
    @endif

    <p x-show="analyzeError" x-cloak class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200" x-text="analyzeError"></p>

    <div data-cv-builder-status
        x-show="saveStatus === 'saving' || @js($currentIsGenerated)"
        @if (! $currentIsGenerated) x-cloak @endif
        class="mb-4 flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        :class="analysisPending() ? 'border-sky-500/30 bg-sky-500/10' : 'border-emerald-500/30 bg-emerald-500/10'"
        role="status">
        <div class="min-w-0">
            <div x-show="analysisPending()" class="flex items-center gap-2 text-sm text-sky-800 dark:text-sky-200">
                <i data-lucide="loader-circle" class="h-4 w-4 shrink-0 animate-spin" aria-hidden="true"></i>
                <span x-text="uiLabels[panelLocale].analyzing"></span>
            </div>
            <div x-show="!analysisPending()">
                <p class="truncate text-sm text-emerald-700 dark:text-emerald-300" x-text="cvFileName"></p>
                @if ($currentIsGenerated && ! empty($currentCv['created_at']))
                    <p class="mt-1 text-xs text-slate-500">{{ __('panel.profile.last_upload', ['date' => \Illuminate\Support\Carbon::parse($currentCv['created_at'])->format('d.m.Y H:i')]) }}</p>
                @endif
            </div>
        </div>
        <div x-show="saveStatus !== 'saving' && hasReadyAnalysis" class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            @if (! empty($skillRadar))
                <span data-cv-analysis-score class="font-semibold text-emerald-700 dark:text-emerald-300">
                    {{ __('panel.skill_radar.overall') }} %{{ $skillRadar['overall_match'] }}
                </span>
            @endif
            <a href="{{ route('panel.career-ladder') }}" class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                {{ __('panel.skill_radar.view_ladder') }} →
            </a>
            <button type="button" @click.stop="resetOpen = true"
                class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                {{ __('panel.skill_radar.clear_cv') }}
            </button>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        @include('app.partials.cv-builder-form')
        @include('app.partials.cv-builder-preview')
    </div>

    @include('app.partials.cv-version-manager')

    <div x-show="pdfExportStatus === 'done' && !pdfModalOpen" x-cloak
        class="fixed bottom-6 left-1/2 z-50 max-w-sm -translate-x-1/2 rounded-xl border border-emerald-300 bg-white px-4 py-3 text-sm text-slate-800 shadow-lg dark:border-emerald-800 dark:bg-slate-900 dark:text-slate-100"
        role="status">
        <span x-text="uiLabels[panelLocale].pdf_success"></span>
    </div>

    {{-- PDF dil seçimi modal --}}
    <div x-show="pdfModalOpen" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
        @keydown.escape.window="pdfExportStatus !== 'exporting' && closePdfModal()">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900"
            @click.outside="pdfExportStatus !== 'exporting' && closePdfModal()"
            role="dialog" aria-modal="true" aria-busy="pdfExportStatus === 'exporting'">
            <h2 class="mb-2 text-lg font-semibold text-slate-900 dark:text-white"
                x-text="uiLabels[panelLocale].pdf_modal_title"></h2>
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-400"
                x-text="uiLabels[panelLocale].pdf_modal_desc"></p>
            <p x-show="pdfExportError" x-cloak
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200"
                x-text="pdfExportError" role="alert"></p>
            <label class="mb-4 block text-sm font-medium text-slate-700 dark:text-slate-200">
                <span x-text="uiLabels[panelLocale].pdf_file_name"></span>
                <input type="text" x-model="pdfFileName" maxlength="250" class="panel-input-block mt-2" :placeholder="uiLabels[panelLocale].pdf_file_name_placeholder">
            </label>
            <div class="flex flex-col gap-2">
                <button type="button" @click="confirmPdfDownload('tr')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="pdfExportStatus === 'exporting'">
                    <i data-lucide="loader-circle" x-show="pdfExportStatus === 'exporting' && pdfExportingLang === 'tr'" x-cloak
                        class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                    <span x-text="uiLabels[panelLocale].pdf_download_tr"></span>
                </button>
                <button type="button" @click="confirmPdfDownload('en')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-600 px-4 py-3 text-sm font-medium text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                    :disabled="pdfExportStatus === 'exporting'">
                    <i data-lucide="loader-circle" x-show="pdfExportStatus === 'exporting' && pdfExportingLang === 'en'" x-cloak
                        class="h-4 w-4 animate-spin" aria-hidden="true"></i>
                    <span x-text="uiLabels[panelLocale].pdf_download_en"></span>
                </button>
                <button type="button" @click="closePdfModal()"
                    class="mt-2 rounded-xl px-4 py-2 text-sm text-slate-500 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:text-slate-200"
                    :disabled="pdfExportStatus === 'exporting'"
                    x-text="uiLabels[panelLocale].cancel"></button>
            </div>
        </div>
    </div>
</div>

@include('app.partials.cv-builder-scripts')
@endsection
