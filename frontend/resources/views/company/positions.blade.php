@extends('company.layouts.app')
@section('title', __('company_positions.title'))
@section('content')
@php
    $permissions = $companyMembership['permissions'] ?? [];
    $statusKeys = ['draft', 'published', 'paused', 'closed', 'archived'];
    $statusOptions = collect($statusKeys)->map(fn (string $status) => [
        'value' => $status,
        'label' => __('company_positions.status.'.$status),
        'count' => $positionStatusCounts[$status] ?? 0,
    ])->values()->all();
    $workplaceOptions = collect($positions)->pluck('workplace_type')->filter()->unique()->sort()->values()->map(fn (string $type) => [
        'value' => $type,
        'label' => __('company.positions.workplace_'.$type),
    ])->values()->all();
    $showUrlTemplate = route('company.positions.show', ['position' => '__ID__']);
    $tableLabels = [
        'results' => __('company_positions.results', ['count' => ':count']),
        'no_results' => __('company_positions.no_results'),
    ];
@endphp
<div class="mx-auto max-w-[1480px]"
    x-data="companyPositions({
        positions: @js($positions),
        statusOptions: @js($statusOptions),
        workplaceOptions: @js($workplaceOptions),
        showUrlTemplate: @js($showUrlTemplate),
        labels: @js($tableLabels),
    })">
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

    @if ($positions === [])
        <section class="panel-card border-dashed p-12 text-center">
            <span class="company-dashboard-icon mx-auto"><i data-lucide="briefcase-business" class="h-5 w-5" aria-hidden="true"></i></span>
            <p class="panel-muted mt-4">{{ __('company_positions.empty') }}</p>
        </section>
    @else
        <section class="panel-card mb-6 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <label class="min-w-0 flex-1 text-sm">
                    <span class="font-medium">{{ __('company_positions.search') }}</span>
                    <input class="panel-input-block mt-2" type="search" x-model="query" placeholder="{{ __('company_positions.search_placeholder') }}">
                </label>
                <label class="w-full text-sm sm:w-auto sm:min-w-[200px]">
                    <span class="font-medium">{{ __('company_positions.filter_status') }}</span>
                    <select class="panel-input-block mt-2" x-model="statusFilter">
                        <option value="all">{{ __('company_positions.filter_status_all') }}</option>
                        <template x-for="option in statusOptions" :key="option.value">
                            <option :value="option.value" x-text="option.count > 0 ? `${option.label} (${option.count})` : option.label"></option>
                        </template>
                    </select>
                </label>
                <label class="w-full text-sm sm:w-auto sm:min-w-[200px]">
                    <span class="font-medium">{{ __('company_positions.filter_workplace') }}</span>
                    <select class="panel-input-block mt-2" x-model="workplaceFilter">
                        <option value="all">{{ __('company_positions.filter_workplace_all') }}</option>
                        <template x-for="option in workplaceOptions" :key="option.value">
                            <option :value="option.value" x-text="option.label"></option>
                        </template>
                    </select>
                </label>
            </div>
            <p class="panel-muted mt-3 text-xs" x-text="labels.results.replace(':count', String(visibleCount()))"></p>
        </section>

        <section class="panel-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-[860px] w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/80 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-4 font-semibold">{{ __('company_positions.fields.title') }}</th>
                            <th class="px-4 py-4 font-semibold">{{ __('company_positions.fields.status') }}</th>
                            <th class="px-4 py-4 font-semibold">{{ __('company_positions.fields.workplace_type') }}</th>
                            <th class="px-4 py-4 font-semibold">{{ __('company_positions.fields.opened_at') }}</th>
                            <th class="px-4 py-4 text-center font-semibold">{{ __('company_positions.metrics.applications') }}</th>
                            <th class="px-4 py-4 text-center font-semibold">{{ __('company_positions.metrics.assessments') }}</th>
                            <th class="px-4 py-4 text-center font-semibold">{{ __('company_positions.metrics.shortlisted') }}</th>
                            <th class="px-5 py-4 font-semibold">{{ __('company_positions.fields.location') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($positions as $position)
                            <tr
                                class="cursor-pointer transition hover:bg-emerald-500/[0.04]"
                                x-show="isVisible(@js($position))"
                                x-cloak
                                role="link"
                                tabindex="0"
                                @click="goToPosition(@js($position))"
                                @keydown.enter.prevent="goToPosition(@js($position))"
                            >
                                <td class="px-5 py-5">
                                    <span class="font-semibold text-slate-950 dark:text-white">{{ $position['title'] }}</span>
                                    <p class="panel-muted mt-1 text-xs">{{ $position['department'] ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-5">@include('company.partials.position-status-badge', ['status' => $position['status'] ?? 'draft'])</td>
                                <td class="px-4 py-5 text-slate-600 dark:text-slate-300">{{ isset($position['workplace_type']) ? __('company.positions.workplace_'.$position['workplace_type']) : '—' }}</td>
                                <td class="px-4 py-5 text-slate-600 dark:text-slate-300">{{ !empty($position['opened_at']) ? \Carbon\Carbon::parse($position['opened_at'])->format('d.m.Y') : '—' }}</td>
                                <td class="px-4 py-5 text-center text-lg font-bold">{{ $position['application_count'] ?? 0 }}</td>
                                <td class="px-4 py-5 text-center text-lg font-bold">{{ $position['assessment_completed_count'] ?? 0 }}</td>
                                <td class="px-4 py-5 text-center text-lg font-bold">{{ $position['shortlisted_count'] ?? 0 }}</td>
                                <td class="px-5 py-5 text-slate-600 dark:text-slate-300">{{ $position['location'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        <tr x-show="visibleCount() === 0" x-cloak>
                            <td class="px-5 py-8 text-center text-sm text-slate-500" colspan="8">{{ __('company_positions.no_results') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
