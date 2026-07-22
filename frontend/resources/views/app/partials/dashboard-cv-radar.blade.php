<div x-data="panelCvRadar({{ Js::from([
    'cv_file' => __('panel.skill_radar.cv_file', ['name' => ':name']),
    'empty_title' => __('panel.skill_radar.empty_title'),
    'empty_desc' => __('panel.skill_radar.empty_desc'),
    'upload_cta' => __('panel.skill_radar.upload_cta'),
    'create_cv' => __('panel.dashboard.create_cv'),
]) }}, @js($hasCvAnalysis ?? false), @js($cvFileName ?? ''), @js(route('panel.cv.clear')))" x-init="init()">

    <div x-show="hasCv" x-cloak>
        @if (! empty($skillRadar))
            @include('app.partials.skill-radar-chart', [
                'skillRadar' => $skillRadar,
                'cvFileName' => $cvFileName ?? null,
                'cvFileDynamic' => ! empty($cvFileName),
                'showCvToolbar' => true,
                'fromApi' => $hasCvAnalysis ?? false,
                'radarAlignment' => 'intro-centered',
            ])
        @endif
    </div>

    <section data-dashboard-cv-empty class="panel-card mb-8 border-dashed p-8 text-center" x-show="!hasCv" x-cloak>
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-emerald-600 dark:bg-slate-800 dark:text-emerald-400">
            <i data-lucide="radar" class="h-7 w-7" aria-hidden="true"></i>
        </div>
        <h2 class="mb-2 text-lg font-semibold" x-text="labels.empty_title"></h2>
        <p class="panel-muted mx-auto mb-4 max-w-md text-sm" x-text="labels.empty_desc"></p>
        <div class="flex flex-wrap justify-center gap-2">
            <a href="{{ route('panel.cv-builder') }}"
                class="inline-flex rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                x-text="labels.upload_cta"></a>
            <a href="{{ route('panel.cv-builder') }}"
                class="inline-flex rounded-xl border border-emerald-600 px-5 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                x-text="labels.create_cv"></a>
        </div>
    </section>
</div>
