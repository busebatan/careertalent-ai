@extends('company.layouts.app')
@section('title', __('company.assessments.title'))
@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-8">
        <p class="company-accent-text text-sm font-semibold">{{ $companyMembership['organization_name'] }}</p>
        <h1 class="mt-1 text-3xl font-bold">{{ __('company.assessments.title') }}</h1>
        <p class="panel-muted mt-2">{{ __('company.assessments.subtitle') }}</p>
    </div>
    @if ($companyError)<div class="panel-card mb-6 border-red-500/30 p-5 text-red-500">{{ $companyError }}</div>@endif

    <section class="panel-card mb-6 p-6">
        <p class="panel-muted text-sm">{{ __('company.assessments.usage') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ $assessmentUsage['used'] ?? 0 }} @if(($assessmentUsage['quota'] ?? null) !== null)<span class="panel-muted text-lg">/ {{ $assessmentUsage['quota'] }}</span>@endif</p>
        @if(($assessmentUsage['quota'] ?? null) === null)<p class="panel-muted mt-2 text-xs">{{ __('company.assessments.quota_pending') }}</p>@endif
    </section>

    @if ($assessments === [])
        <section class="panel-card border-dashed p-12 text-center"><p class="panel-muted">{{ __('company.assessments.empty') }}</p></section>
    @else
        <section class="panel-card overflow-hidden"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900/60"><tr><th class="px-5 py-4">{{ __('company.assessments.candidate') }}</th><th class="px-5 py-4">{{ __('company.assessments.position') }}</th><th class="px-5 py-4">{{ __('company.assessments.assessment') }}</th><th class="px-5 py-4">{{ __('company.assessments.status') }}</th><th class="px-5 py-4">{{ __('company.assessments.assigned_at') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">@foreach($assessments as $assessment)<tr><td class="px-5 py-4 font-semibold">{{ $assessment['candidate_name'] }}</td><td class="px-5 py-4">{{ $assessment['position_title'] }}</td><td class="px-5 py-4">{{ $assessment['title'] ?: '—' }}</td><td class="px-5 py-4">{{ __('company.assessments.status_'.$assessment['status']) }}</td><td class="px-5 py-4">{{ \Carbon\Carbon::parse($assessment['assigned_at'])->format('d.m.Y H:i') }}</td></tr>@endforeach</tbody>
        </table></div></section>
    @endif
</div>
@endsection
