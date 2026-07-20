@extends('company.layouts.app')
@section('title', __('company_positions.title'))
@section('content')
@php
    $permissions = $companyMembership['permissions'] ?? [];
    $statusTabs = ['draft', 'published', 'paused', 'closed', 'archived'];
@endphp
<div class="mx-auto max-w-[1480px]">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="company-accent-text text-sm font-semibold">{{ $companyMembership['organization_name'] }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ __('company_positions.title') }}</h1>
            <p class="panel-muted mt-2 max-w-3xl">{{ __('company_positions.subtitle') }}</p>
        </div>
        @if(in_array('positions.write', $permissions, true))
            <a class="company-btn-primary" href="{{ route('company.positions.new') }}">
                <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                {{ __('company_positions.new') }}
            </a>
        @endif
    </header>

    <nav class="mb-6 flex gap-2 overflow-x-auto pb-1" aria-label="{{ __('company_positions.fields.status') }}">
        <a class="whitespace-nowrap {{ $positionStatus === null ? 'company-btn-primary' : 'company-btn-secondary' }}" href="{{ route('company.positions') }}">{{ __('company_positions.status.all') }}</a>
        @foreach($statusTabs as $status)
            <a class="whitespace-nowrap {{ $positionStatus === $status ? 'company-btn-primary' : 'company-btn-secondary' }}" href="{{ route('company.positions', ['status' => $status, 'q' => $positionQuery ?: null]) }}">{{ __('company_positions.status.'.$status) }} <span class="opacity-70">{{ $positionStatusCounts[$status] ?? 0 }}</span></a>
        @endforeach
    </nav>

    <form class="panel-card mb-6 flex flex-col gap-3 p-4 sm:flex-row" method="get" action="{{ route('company.positions') }}">
        @if($positionStatus)<input type="hidden" name="status" value="{{ $positionStatus }}">@endif
        <label class="sr-only" for="position-search">{{ __('company.applications.search') }}</label>
        <input id="position-search" class="panel-input-block flex-1" name="q" value="{{ $positionQuery }}" placeholder="Pozisyon veya departman ara">
        <button class="company-btn-secondary" type="submit">{{ __('company.applications.search') }}</button>
    </form>

    @if ($positions === [])
        <section class="panel-card border-dashed p-12 text-center">
            <span class="company-dashboard-icon mx-auto"><i data-lucide="briefcase-business" class="h-5 w-5" aria-hidden="true"></i></span>
            <p class="panel-muted mt-4">{{ __('company_positions.empty') }}</p>
        </section>
    @else
        <section class="panel-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-[1180px] w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-4 font-semibold">{{ __('company_positions.fields.title') }}</th>
                            <th class="px-4 py-4 font-semibold">{{ __('company_positions.fields.location') }}</th>
                            <th class="px-4 py-4 font-semibold">{{ __('company_positions.fields.workplace_type') }}</th>
                            <th class="px-4 py-4 font-semibold">{{ __('company_positions.fields.opened_at') }}</th>
                            <th class="px-4 py-4 text-center font-semibold">{{ __('company_positions.metrics.applications') }}</th>
                            <th class="px-4 py-4 text-center font-semibold">{{ __('company_positions.metrics.assessments') }}</th>
                            <th class="px-4 py-4 text-center font-semibold">{{ __('company_positions.metrics.shortlisted') }}</th>
                            <th class="px-4 py-4 font-semibold">{{ __('company_positions.fields.recruiter') }}</th>
                            <th class="px-5 py-4 font-semibold">{{ __('company_positions.fields.technical_manager') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($positions as $position)
                            <tr class="transition hover:bg-emerald-500/[0.04]">
                                <td class="px-5 py-5">
                                    <a class="font-semibold text-slate-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300" href="{{ route('company.positions.show', ['position' => $position['id']]) }}">{{ $position['title'] }}</a>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span>{{ $position['department'] ?: '—' }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{{ __('company_positions.status.'.($position['status'] ?? 'draft')) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-5 text-slate-600 dark:text-slate-300">{{ $position['location'] ?? '—' }}</td>
                                <td class="px-4 py-5 text-slate-600 dark:text-slate-300">{{ isset($position['workplace_type']) ? __('company.positions.workplace_'.$position['workplace_type']) : '—' }}</td>
                                <td class="px-4 py-5 text-slate-600 dark:text-slate-300">{{ !empty($position['opened_at']) ? \Carbon\Carbon::parse($position['opened_at'])->format('d.m.Y') : '—' }}</td>
                                <td class="px-4 py-5 text-center text-lg font-bold">{{ $position['application_count'] ?? 0 }}</td>
                                <td class="px-4 py-5 text-center text-lg font-bold">{{ $position['assessment_completed_count'] ?? 0 }}</td>
                                <td class="px-4 py-5 text-center text-lg font-bold">{{ $position['shortlisted_count'] ?? 0 }}</td>
                                <td class="px-4 py-5 text-slate-600 dark:text-slate-300">{{ $position['recruiter_name'] ?? '—' }}</td>
                                <td class="px-5 py-5 text-slate-600 dark:text-slate-300">{{ $position['technical_manager_name'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @php($lastPage = max(1, (int) ceil($positionTotal / max(1, $positionPageSize))))
        @if($lastPage > 1)<nav class="mt-5 flex items-center justify-between" aria-label="Sayfalama"><a class="company-btn-secondary {{ $positionPage <= 1 ? 'pointer-events-none opacity-50' : '' }}" href="{{ route('company.positions', ['status' => $positionStatus, 'q' => $positionQuery ?: null, 'page' => max(1, $positionPage - 1)]) }}">Önceki</a><span class="panel-muted text-sm">{{ $positionPage }} / {{ $lastPage }}</span><a class="company-btn-secondary {{ $positionPage >= $lastPage ? 'pointer-events-none opacity-50' : '' }}" href="{{ route('company.positions', ['status' => $positionStatus, 'q' => $positionQuery ?: null, 'page' => min($lastPage, $positionPage + 1)]) }}">Sonraki</a></nav>@endif
    @endif
</div>
@endsection
