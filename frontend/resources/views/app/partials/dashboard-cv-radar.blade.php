<div x-data="panelCvRadar({{ Js::from([
    'cv_file' => __('panel.skill_radar.cv_file', ['name' => ':name']),
    'empty_title' => __('panel.skill_radar.empty_title'),
    'empty_desc' => __('panel.skill_radar.empty_desc'),
    'upload_cta' => __('panel.skill_radar.upload_cta'),
    'create_cv' => __('panel.dashboard.create_cv'),
]) }})" x-init="init()">

    <div x-show="hasCv" x-cloak>
        @include('app.partials.skill-radar-chart', [
            'skillRadar' => $skillRadar,
            'cvFileDynamic' => true,
            'showCvToolbar' => true,
        ])
    </div>

    <section class="panel-card mb-8 border-dashed p-8 text-center" x-show="!hasCv" x-cloak>
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl dark:bg-slate-800">📊</div>
        <h2 class="mb-2 text-lg font-semibold" x-text="labels.empty_title"></h2>
        <p class="panel-muted mx-auto mb-4 max-w-md text-sm" x-text="labels.empty_desc"></p>
        <div class="flex flex-wrap justify-center gap-2">
            <a href="{{ route('panel.profile') }}#cv-yukle" class="inline-flex rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                x-text="labels.upload_cta"></a>
            <a href="{{ route('panel.cv-builder') }}" class="inline-flex rounded-xl border border-emerald-600 px-5 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                x-text="labels.create_cv"></a>
        </div>
    </section>
</div>
