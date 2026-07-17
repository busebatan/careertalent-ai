@extends('app.layouts.app')

@section('title', __('panel.cv_builder.title'))

@push('head')
<style>@include('app.partials.cv-builder-styles')</style>
@endpush

@section('content')
<div class="mx-auto max-w-7xl"
    x-data="cvBuilder({{ Js::from($cvDraft) }}, {{ Js::from($cvLabels) }}, @js(app()->getLocale()), @js($hasCvAnalysis ?? false), @js($cvFileName ?? ''), @js(route('panel.cv.analyze-builder')), @js(route('panel.cv.clear')), @js(route('panel.cv.analysis-status', ['analysisId' => '__ANALYSIS_ID__'])), {{ Js::from($acceptedEvidence ?? []) }}, @js(route('panel.cv.archive-generated')), @js($restoredFromHistory ?? false))">

    <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="mb-1 text-2xl font-bold">{{ __('panel.cv_builder.title') }}</h1>
            <p class="text-slate-600 dark:text-slate-400">{{ __('panel.cv_builder.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="saveCv()"
                class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60"
                :disabled="saveStatus === 'saving'"
                x-text="saveStatus === 'saving' ? uiLabels[panelLocale].analyzing : (saveStatus === 'saved' ? uiLabels[panelLocale].saved : uiLabels[panelLocale].save)">
            </button>
            <button type="button" @click="mode = mode === 'edit' ? 'preview' : 'edit'"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                x-text="mode === 'edit' ? uiLabels[panelLocale].preview : uiLabels[panelLocale].edit">
            </button>
            <button type="button" @click="openPdfModal()"
                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="pdfExportStatus === 'exporting'"
                x-text="uiLabels[panelLocale].download_pdf">
            </button>
        </div>
    </header>

  <p class="panel-muted -mt-4 mb-6 text-sm" x-text="uiLabels[panelLocale].save_hint"></p>

    <div class="sticky bottom-4 z-20 mb-6 flex justify-center lg:hidden">
        <button type="button" @click="saveCv()"
            class="rounded-full bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-lg hover:bg-sky-500 disabled:opacity-60"
            :disabled="saveStatus === 'saving'"
            x-text="saveStatus === 'saved' ? uiLabels[panelLocale].saved : uiLabels[panelLocale].save">
        </button>
    </div>

    <div x-show="showRadar" x-cloak class="mb-8">
        @if (! empty($skillRadar))
            @include('app.partials.skill-radar-chart', [
                'skillRadar' => $skillRadar,
                'cvFileName' => $cvFileName ?? null,
                'cvFileDynamic' => false,
                'fromApi' => $hasCvAnalysis ?? false,
                'showClearInline' => true,
            ])
        @endif
    </div>

    <p x-show="analyzeError" x-cloak class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200" x-text="analyzeError"></p>

    <div class="grid gap-8 lg:grid-cols-2">
        @include('app.partials.cv-builder-form')
        @include('app.partials.cv-builder-preview')
    </div>

    @if (! empty($acceptedEvidence))
        <section class="panel-card mt-8 p-5">
            <h2 class="mb-3 font-semibold">{{ app()->getLocale() === 'en' ? 'Verified achievements from AI review' : 'AI incelemesinden doğrulanmış kazanımlar' }}</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($acceptedEvidence as $achievement)
                    <li><strong>{{ $achievement['title'] }}</strong>@if (! empty($achievement['skill_impacts'])) · {{ implode(', ', $achievement['skill_impacts']) }}@endif</li>
                @endforeach
            </ul>
        </section>
    @endif

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
