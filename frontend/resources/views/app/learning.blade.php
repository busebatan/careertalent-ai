@extends('app.layouts.app')

@section('title', __('panel.learning_page.title'))

@section('content')
<div class="mx-auto max-w-5xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.learning_page.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.learning_page.subtitle') }}</p>
    </header>

    @if (! empty($selectedTarget))
        <div class="panel-card mb-6 border-emerald-500/30 bg-emerald-500/10 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('panel.learning_page.selected_target') }}</p>
            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ $selectedTarget['title'] }}</p>
        </div>
    @endif

    @include('app.partials.panel-learning-resources', ['mode' => 'full'])
</div>
@endsection
