@extends('app.layouts.app')

@section('title', __('panel.applications.title'))

@section('content')
<div class="mx-auto max-w-7xl" x-data="{ columns: {{ Js::from($applications['columns']) }}, company: '', role: '', addApp() { if (!this.company.trim() || !this.role.trim()) return; this.columns[0].items.unshift({ company: this.company, role: this.role, date: '{{ __('panel.applications.today') }}', next: '{{ __('panel.applications.default_next') }}' }); this.company = ''; this.role = ''; } }">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.applications.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.applications.subtitle') }}</p>
    </header>

    <section class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="panel-card p-5"><p class="panel-muted text-sm">{{ __('panel.applications.active') }}</p><p class="text-3xl font-bold">{{ $applications['metrics']['active'] }}</p></div>
        <div class="panel-card p-5"><p class="panel-muted text-sm">{{ __('panel.applications.interviews') }}</p><p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $applications['metrics']['interviews'] }}</p></div>
        <div class="panel-card p-5"><p class="panel-muted text-sm">{{ __('panel.applications.offers') }}</p><p class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $applications['metrics']['offers'] }}</p></div>
    </section>

    <form class="panel-card mb-6 grid gap-3 p-4 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="addApp()">
        <input x-model="company" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" :placeholder="@js(__('panel.applications.company_placeholder'))">
        <input x-model="role" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" :placeholder="@js(__('panel.applications.role_placeholder'))">
        <button class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('panel.applications.add_button') }}</button>
    </form>

    <section class="grid gap-4 lg:grid-cols-3">
        <template x-for="column in columns" :key="column.id">
            <div class="panel-card p-4">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold" x-text="column.label"></h2>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs dark:bg-slate-800" x-text="column.items.length"></span>
                </div>
                <div class="space-y-3">
                    <template x-for="item in column.items" :key="item.company + item.role">
                        <article class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <p class="font-semibold" x-text="item.company"></p>
                            <p class="text-sm text-slate-700 dark:text-slate-300" x-text="item.role"></p>
                            <p class="panel-muted mt-2 text-xs" x-text="item.date + ' · ' + item.next"></p>
                        </article>
                    </template>
                </div>
            </div>
        </template>
    </section>
</div>
@endsection
