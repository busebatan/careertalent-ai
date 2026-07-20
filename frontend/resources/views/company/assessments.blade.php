@extends('company.layouts.app')
@section('title', __('company.assessments.title'))
@section('content')
@php
    $statusKeys = ['assigned', 'in_progress', 'completed', 'expired', 'cancelled'];
    $statusOptions = collect($statusKeys)->map(fn (string $status) => [
        'value' => $status,
        'label' => __('company.assessments.status_'.$status),
    ])->values()->all();
    $positionOptions = collect($assessments)->pluck('position_title')->filter()->unique()->sort()->values()->all();
    $tableLabels = [
        'results' => __('company.assessments.results', ['count' => ':count']),
        'no_results' => __('company.assessments.no_results'),
    ];
@endphp
<div class="mx-auto max-w-7xl"
    x-data="companyAssessments({
        assessments: @js($assessments),
        statusOptions: @js($statusOptions),
        positionOptions: @js($positionOptions),
        labels: @js($tableLabels),
    })">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">{{ __('company.assessments.title') }}</h1>
        <p class="panel-muted mt-2">{{ __('company.assessments.subtitle') }}</p>
    </div>

    <section class="panel-card mb-6 p-6">
        <p class="panel-muted text-sm">{{ __('company.assessments.usage') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ $assessmentUsage['used'] ?? 0 }} @if(($assessmentUsage['quota'] ?? null) !== null)<span class="panel-muted text-lg">/ {{ $assessmentUsage['quota'] }}</span>@endif</p>
        @if(($assessmentUsage['quota'] ?? null) === null)<p class="panel-muted mt-2 text-xs">{{ __('company.assessments.quota_pending') }}</p>@endif
    </section>

    @if ($assessments === [])
        <section class="panel-card border-dashed p-12 text-center"><p class="panel-muted">{{ __('company.assessments.empty') }}</p></section>
    @else
        <section class="panel-card overflow-hidden">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm xl:col-span-2">
                        <span class="font-medium">{{ __('company.assessments.search') }}</span>
                        <input class="panel-input-block mt-2" type="search" x-model="query" placeholder="{{ __('company.assessments.search_placeholder') }}">
                    </label>
                    <label class="text-sm">
                        <span class="font-medium">{{ __('company.assessments.filter_status') }}</span>
                        <select class="panel-input-block mt-2" x-model="statusFilter">
                            <option value="all">{{ __('company.assessments.filter_status_all') }}</option>
                            <template x-for="option in statusOptions" :key="option.value">
                                <option :value="option.value" x-text="option.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="font-medium">{{ __('company.assessments.filter_position') }}</span>
                        <select class="panel-input-block mt-2" x-model="positionFilter">
                            <option value="all">{{ __('company.assessments.filter_position_all') }}</option>
                            <template x-for="position in positionOptions" :key="position">
                                <option :value="position" x-text="position"></option>
                            </template>
                        </select>
                    </label>
                </div>
                <p class="panel-muted mt-3 text-xs" x-text="labels.results.replace(':count', String(visibleCount()))"></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900/60">
                        <tr>
                            <th class="px-5 py-4">{{ __('company.assessments.candidate') }}</th>
                            <th class="px-5 py-4">{{ __('company.assessments.position') }}</th>
                            <th class="px-5 py-4">{{ __('company.assessments.assessment') }}</th>
                            <th class="px-5 py-4">{{ __('company.assessments.status') }}</th>
                            <th class="px-5 py-4">{{ __('company.assessments.assigned_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($assessments as $assessment)
                            <tr x-show="isVisible(@js($assessment))" x-cloak>
                                <td class="px-5 py-4 font-semibold">{{ $assessment['candidate_name'] }}</td>
                                <td class="px-5 py-4">{{ $assessment['position_title'] }}</td>
                                <td class="px-5 py-4">{{ $assessment['title'] ?: '—' }}</td>
                                <td class="px-5 py-4">@include('company.partials.assessment-status-badge', ['status' => $assessment['status']])</td>
                                <td class="px-5 py-4">{{ \Carbon\Carbon::parse($assessment['assigned_at'])->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                        <tr x-show="visibleCount() === 0" x-cloak>
                            <td class="px-5 py-8 text-center text-sm text-slate-500" colspan="5">{{ __('company.assessments.no_results') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
