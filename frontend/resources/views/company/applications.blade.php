@extends('company.layouts.app')
@section('title', __('company.applications.title'))
@section('content')
@php
    $stageKeys = ['new', 'assessment_pending', 'assessment_in_progress', 'technical_review', 'shortlisted', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'];
    $stageOptions = collect($stageKeys)->map(fn (string $stage) => [
        'value' => $stage,
        'label' => __('company.applications.stage_'.$stage),
    ])->values()->all();
    $positionOptions = collect($applications)->pluck('position_title')->filter()->unique()->sort()->values()->all();
    $tableLabels = [
        'results' => __('company.applications.results', ['count' => ':count']),
        'no_results' => __('company.applications.no_results'),
    ];
@endphp
<div class="mx-auto max-w-7xl"
    x-data="companyApplications({
        applications: @js($applications),
        stageOptions: @js($stageOptions),
        positionOptions: @js($positionOptions),
        labels: @js($tableLabels),
    })">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">{{ __('company.applications.title') }}</h1>
        <p class="panel-muted mt-2">{{ __('company.applications.subtitle') }}</p>
    </div>
    @if ($applications === [])
        <section class="panel-card border-dashed p-12 text-center"><p class="panel-muted">{{ __('company.applications.empty') }}</p></section>
    @else
        <section class="panel-card overflow-hidden">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm xl:col-span-2">
                        <span class="font-medium">{{ __('company.applications.search') }}</span>
                        <input class="panel-input-block mt-2" type="search" x-model="query" placeholder="{{ __('company.applications.search_placeholder') }}">
                    </label>
                    <label class="text-sm">
                        <span class="font-medium">{{ __('company.applications.filter_stage') }}</span>
                        <select class="panel-input-block mt-2" x-model="stageFilter">
                            <option value="all">{{ __('company.applications.filter_stage_all') }}</option>
                            <template x-for="option in stageOptions" :key="option.value">
                                <option :value="option.value" x-text="option.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="font-medium">{{ __('company.applications.filter_position') }}</span>
                        <select class="panel-input-block mt-2" x-model="positionFilter">
                            <option value="all">{{ __('company.applications.filter_position_all') }}</option>
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
                       <th class="px-5 py-4">Candidate</th>
                       <th class="px-5 py-4">Position</th>
                       <th class="px-5 py-4">Stage</th>
                       <th class="px-5 py-4">Skill Match</th>
                       <th class="px-5 py-4">Assessment</th>
                       <th class="px-5 py-4">Evidence</th>
                       <th class="px-5 py-4">Last Action</th>
                       <th class="px-5 py-4 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($applications as $application)
                            <tr x-show="isVisible(@js($application))" x-cloak>
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ $application['candidate_name'] }}</p>
                                    <p class="panel-muted mt-1 text-xs">{{ $application['candidate_email'] }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    {{ $application['position_title'] }}
                                </td>

                                <td class="px-5 py-4">
                                    @include('company.partials.stage-badge', ['stage' => $application['current_stage']])
                                </td>

                                <td class="px-5 py-4">
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                       {{ $application['skill_match'] ?? 'N/A' }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    {{ $application['assessment_status'] ?? 'Pending' }}
                                </td>

                                <td class="px-5 py-4">
                                    {{ $application['evidence_confidence'] ?? 'Medium' }}
                                </td>

                                <td class="px-5 py-4 text-xs text-slate-500">
                                    {{ \Carbon\Carbon::parse($application['applied_at'])->diffForHumans() }}
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">

                                        <button class="rounded bg-slate-100 px-3 py-1 text-xs hover:bg-slate-200">
                                            View
                                        </button>

                                        <button class="rounded bg-blue-600 px-3 py-1 text-xs text-white hover:bg-blue-700">
                                            Assessment
                                        </button>

                                        <button class="rounded bg-emerald-600 px-3 py-1 text-xs text-white hover:bg-emerald-700">
                                            Shortlist
                                        </button>

                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr x-show="visibleCount() === 0" x-cloak>
                            <td class="px-5 py-8 text-center text-sm text-slate-500" colspan="8">{{ __('company.applications.no_results') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
