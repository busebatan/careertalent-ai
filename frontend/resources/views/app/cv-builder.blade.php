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

    <!-- CV Sürümleri (CV Center) Yönetimi -->
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="layers" class="h-5 w-5 text-emerald-600 dark:text-emerald-400"></i>
                    {{ app()->getLocale() === 'en' ? 'My Resume Versions (CV Center)' : 'Özgeçmiş Sürümlerim (CV Merkezi)' }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ app()->getLocale() === 'en' ? 'Manage different versions of your CV for different roles or languages.' : 'Farklı roller veya diller için özgeçmiş sürümlerinizi oluşturun ve yönetin.' }}
                </p>
            </div>
            <button type="button" @click="openCreateVersionModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 transition shadow-sm hover:shadow active:scale-95">
                <i data-lucide="plus" class="h-4 w-4"></i>
                {{ app()->getLocale() === 'en' ? 'Save Current as New Version' : 'Mevcut Taslağı Yeni Sürüm Olarak Kaydet' }}
            </button>
        </div>

        <template x-if="cvVersions.length === 0">
            <div class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-500 dark:border-slate-800">
                <p>{{ app()->getLocale() === 'en' ? 'You have no custom CV versions yet. Create one by clicking the button above.' : 'Henüz özel bir CV sürümünüz bulunmuyor. Yukarıdaki butona tıklayarak ilk sürümünüzü oluşturabilirsiniz.' }}</p>
            </div>
        </template>

        <template x-if="cvVersions.length > 0">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="version in cvVersions" :key="version.id">
                    <div class="relative flex flex-col justify-between rounded-xl border p-4 transition-all hover:shadow-md"
                        :class="version.is_main ? 'border-emerald-500 bg-emerald-50/20 dark:bg-emerald-950/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/50'">
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-semibold text-slate-900 dark:text-white" x-text="version.version_name"></h3>
                                <div class="flex gap-1.5">
                                    <span class="rounded px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                        :class="version.language === 'tr' ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'"
                                        x-text="version.language"></span>
                                    <template x-if="version.is_main">
                                        <span class="rounded bg-emerald-600 px-1.5 py-0.5 text-xs font-semibold text-white tracking-wider">
                                            {{ app()->getLocale() === 'en' ? 'Main' : 'Ana' }}
                                        </span>
                                    </template>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                {{ app()->getLocale() === 'en' ? 'Saved: ' : 'Kayıt: ' }}
                                <span x-text="new Date(version.created_at).toLocaleDateString(panelLocale, {day: 'numeric', month: 'short', year: 'numeric'})"></span>
                            </p>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                            <button type="button" @click="loadVersion(version)"
                                class="inline-flex items-center gap-1 rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition dark:bg-sky-950/30 dark:text-sky-300 dark:hover:bg-sky-900/30">
                                <i data-lucide="arrow-up-right" class="h-3 w-3"></i>
                                {{ app()->getLocale() === 'en' ? 'Load to Editor' : 'Editöre Yükle' }}
                            </button>
                            <template x-if="!version.is_main">
                                <button type="button" @click="setVersionMain(version)"
                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition dark:bg-emerald-950/30 dark:text-emerald-300 dark:hover:bg-emerald-900/30">
                                    <i data-lucide="check" class="h-3 w-3"></i>
                                    {{ app()->getLocale() === 'en' ? 'Set as Main' : 'Ana Yap' }}
                                </button>
                            </template>
                            <button type="button" @click="deleteVersion(version)"
                                class="ml-auto inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-900/30">
                                <i data-lucide="trash-2" class="h-3 w-3"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

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
