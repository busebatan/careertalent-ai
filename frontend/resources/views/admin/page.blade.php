@extends('admin.layouts.app')

@section('title', $page['title'])

@section('content')
<div class="mx-auto max-w-7xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $page['title'] }}</h1>
        <p class="max-w-3xl text-slate-600 dark:text-slate-400">{{ $page['subtitle'] }}</p>
    </header>

    @if ($adminError)
        <p class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>
    @else
        <section class="panel-card overflow-hidden">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('admin.page.records_title') }}</h2>
                <p class="panel-muted text-sm">{{ __('admin.page.records_hint', ['total' => $page['total']]) }}</p>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($page['rows'] as $row)
                    <article class="grid gap-4 p-5 md:grid-cols-[1fr_8rem_10rem_14rem] md:items-center">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $row['name'] }}</p>
                            <p class="panel-muted mt-1 text-sm">{{ $row['meta'] }}</p>
                        </div>
                        <p class="text-sm font-semibold admin-accent-text">{{ $row['score'] }}</p>
                        <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $row['status'] }}</span>
                        <p class="text-sm text-slate-700 dark:text-slate-300">{{ $row['next'] }}</p>
                    </article>
                @empty
                    <p class="panel-muted p-5 text-sm">{{ __('admin.page.empty_rows') }}</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
