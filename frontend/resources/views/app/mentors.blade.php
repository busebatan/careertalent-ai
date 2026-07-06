@extends('app.layouts.app')

@section('title', __('panel.mentors.title'))

@section('content')
<div class="mx-auto max-w-6xl" x-data="{ selected: '', booked: [] }">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.mentors.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.mentors.subtitle') }}</p>
    </header>

    <section class="mb-8 grid gap-4 md:grid-cols-3">
        @foreach ($mentors['packages'] as $package)
            <button type="button" @click="selected = @js($package['name'])" class="panel-card p-5 text-left transition hover:border-emerald-500/50" :class="selected === @js($package['name']) ? 'border-emerald-500/60 bg-emerald-500/5' : ''">
                <h2 class="font-semibold">{{ $package['name'] }}</h2>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $package['price'] }}</p>
                <p class="panel-muted text-sm">{{ $package['delivery'] }}</p>
            </button>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        @foreach ($mentors['experts'] as $expert)
            <article class="panel-card p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $expert['name'] }}</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $expert['title'] }} · {{ $expert['company'] }}</p>
                    </div>
                    <span class="rounded-full bg-amber-500/15 px-2 py-1 text-xs text-amber-700 dark:text-amber-300">★ {{ $expert['rating'] }}</span>
                </div>
                <p class="text-sm"><strong>{{ __('panel.mentors.focus') }}:</strong> {{ $expert['focus'] }}</p>
                <p class="panel-muted mt-2 text-sm">{{ __('panel.mentors.next_slot') }}: {{ $expert['slots'] }}</p>
                <button type="button" @click="booked.push('{{ $expert['name'] }}')" class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('panel.mentors.book_button') }}</button>
            </article>
        @endforeach
    </section>

    <div class="panel-card mt-6 p-4" x-show="booked.length" x-cloak>
        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ __('panel.mentors.booked_title') }}</p>
        <p class="panel-muted mt-1 text-sm"><span x-text="booked.join(', ')"></span> · <span x-text="selected || '{{ __('panel.mentors.no_package') }}'"></span></p>
    </div>
</div>
@endsection
