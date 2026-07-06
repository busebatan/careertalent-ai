@extends('app.layouts.app')

@section('title', __('panel.job_radar.title'))

@section('content')
<div class="mx-auto max-w-6xl" x-data="{ alerts: {{ Js::from($radar['alerts']) }}, role: 'all', source: 'all', readyOnly: false, get filtered() { return this.alerts.filter(a => (this.role === 'all' || a.role === this.role) && (this.source === 'all' || a.source === this.source) && (!this.readyOnly || a.match >= 70)); } }">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.job_radar.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.job_radar.subtitle') }}</p>
    </header>

    <section class="panel-card mb-6 grid gap-3 p-4 md:grid-cols-3">
        <select x-model="role" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value="all">{{ __('panel.job_radar.all_roles') }}</option>
            @foreach ($radar['roles'] as $role)
                <option value="{{ $role }}">{{ $role }}</option>
            @endforeach
        </select>
        <select x-model="source" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value="all">{{ __('panel.job_radar.all_sources') }}</option>
            @foreach ($radar['sources'] as $source)
                <option value="{{ $source }}">{{ $source }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
            <input type="checkbox" x-model="readyOnly" class="rounded border-slate-300 text-emerald-600">
            {{ __('panel.job_radar.ready_only') }}
        </label>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <template x-for="alert in filtered" :key="alert.company + alert.role">
            <article class="panel-card p-5">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold" x-text="alert.company"></h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400" x-text="alert.role"></p>
                    </div>
                    <p class="text-2xl font-bold" :class="alert.match >= 70 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">%<span x-text="alert.match"></span></p>
                </div>
                <p class="panel-muted text-xs"><span x-text="alert.source"></span> · <span x-text="alert.salary"></span></p>
                <div class="mt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('panel.job_radar.gaps') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="gap in alert.gaps" :key="gap"><span class="rounded-full bg-red-500/10 px-2 py-1 text-xs text-red-700 dark:text-red-300" x-text="gap"></span></template>
                    </div>
                </div>
                <p class="mt-4 rounded-xl bg-emerald-500/10 p-3 text-sm text-slate-700 dark:text-slate-300" x-text="alert.action"></p>
            </article>
        </template>
    </section>
</div>
@endsection
