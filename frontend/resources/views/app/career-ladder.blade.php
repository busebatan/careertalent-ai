@extends('app.layouts.app')

@section('title', __('panel.career_ladder.title'))

@section('content')
<div class="mx-auto max-w-5xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.career_ladder.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.career_ladder.subtitle') }}</p>
        @if (! empty($fromApi))
            <p class="mt-3 inline-flex rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                {{ __('panel.career_ladder.from_cv_analysis') }}
            </p>
        @endif
    </header>


    @if (! empty($selectedTarget))
        <div class="panel-card mb-6 border-emerald-500/30 bg-emerald-500/10 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('panel.career_ladder.selected_target') }}</p>
            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ $selectedTarget['title'] }}</p>
        </div>
    @endif

    <section class="panel-card mb-8 p-5">
        <h2 class="mb-1 font-semibold">{{ __('panel.career_ladder.custom_target_title') }}</h2>
        <p class="panel-muted mb-4 text-sm">{{ __('panel.career_ladder.custom_target_desc') }}</p>
        <div class="grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('panel.career-ladder.select') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="mode" value="custom">
                <label class="block text-sm">
                    <span class="panel-muted mb-1 block text-xs">{{ __('panel.career_ladder.custom_role_label') }}</span>
                    <input name="target_role" type="text" required maxlength="120" class="panel-input-block" placeholder="{{ __('panel.career_ladder.custom_role_placeholder') }}">
                </label>
                <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('panel.career_ladder.go_roadmap') }}</button>
            </form>
            <form method="POST" action="{{ route('panel.career-ladder.select') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="mode" value="job_url">
                <label class="block text-sm">
                    <span class="panel-muted mb-1 block text-xs">{{ __('panel.career_ladder.job_url_label') }}</span>
                    <input name="job_url" type="url" required maxlength="2048" class="panel-input-block" placeholder="{{ __('panel.career_ladder.job_url_placeholder') }}">
                </label>
                <button class="panel-btn-secondary">{{ __('panel.career_ladder.go_roadmap') }}</button>
            </form>
        </div>
    </section>

    @if (! empty($careerLadder))
        @include('app.partials.career-ladder', ['hideSectionHeader' => true, 'selectedTarget' => $selectedTarget])
    @else
        <section class="panel-card border-dashed p-8 text-center">
            <h2 class="mb-2 text-lg font-semibold">{{ app()->getLocale() === 'en' ? 'AI analysis is not ready' : 'AI analizi henüz hazır değil' }}</h2>
            <p class="panel-muted text-sm">{{ $careerEngineError ?: (app()->getLocale() === 'en' ? 'Upload a CV to generate your career ladder.' : 'Kariyer merdivenini oluşturmak için CV yükle.') }}</p>
            <a href="{{ route('panel.cv-builder') }}" class="mt-4 inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white">{{ __('panel.dashboard.upload_cv') }}</a>
        </section>
    @endif
</div>
@endsection
