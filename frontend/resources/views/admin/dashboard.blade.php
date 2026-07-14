@extends('admin.layouts.app')

@section('title', __('admin.dashboard.title'))

@section('content')
<div class="mx-auto max-w-7xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('admin.dashboard.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('admin.dashboard.subtitle') }}</p>
    </header>

    @if ($adminError)
        <p class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>
    @else
        <section class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $stat)
                <article class="panel-card p-5">
                    <p class="panel-muted text-sm">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-xs admin-accent-text">{{ $stat['detail'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="mb-8 panel-card overflow-hidden">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('admin.dashboard.recent_students') }}</h2>
                <p class="panel-muted text-sm">{{ __('admin.dashboard.recent_students_hint') }}</p>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($recentStudents as $student)
                    <article class="flex flex-col gap-1 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $student['name'] }}</p>
                            <p class="panel-muted text-sm">{{ $student['email'] }}</p>
                        </div>
                        <p class="panel-muted text-sm">
                            @if (! empty($student['registered_at']))
                                {{ __('admin.dashboard.registered_at', ['date' => $student['registered_at']]) }}
                            @else
                                {{ __('admin.dashboard.registered_unknown') }}
                            @endif
                        </p>
                    </article>
                @empty
                    <p class="panel-muted p-5 text-sm">{{ __('admin.dashboard.no_students') }}</p>
                @endforelse
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($modules as $module)
                <article class="panel-card flex flex-col p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-slate-900 dark:text-white">{{ $module['title'] }}</h2>
                            <p class="panel-muted mt-1 text-sm">{{ $module['description'] }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ __('admin.dashboard.records_count', ['count' => $module['count']]) }}</span>
                    </div>
                    <a href="{{ route($module['route']) }}" class="admin-btn-primary mt-auto w-fit">{{ __('admin.dashboard.open_records') }}</a>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
