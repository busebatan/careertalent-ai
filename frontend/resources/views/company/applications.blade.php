@extends('company.layouts.app')
@section('title', __('company.applications.title'))
@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-8">
        <p class="company-accent-text text-sm font-semibold">{{ $companyMembership['organization_name'] }}</p>
        <h1 class="mt-1 text-3xl font-bold">{{ __('company.applications.title') }}</h1>
        <p class="panel-muted mt-2">{{ __('company.applications.subtitle') }}</p>
    </div>
    @if ($companyError)<div class="panel-card mb-6 border-red-500/30 p-5 text-red-500">{{ $companyError }}</div>@endif

    <nav class="mb-5 flex flex-wrap gap-2" aria-label="{{ __('company.applications.title') }}">
        @foreach(['new','assessment_pending','technical_review','scorecard_missing','retention_due'] as $queue)
            <a class="{{ ($applicationFilters['queue'] ?? null) === $queue ? 'company-btn-primary' : 'company-btn-secondary' }}" href="{{ route('company.applications', ['queue' => $queue]) }}">{{ __('company.applications.queue_'.$queue) }}</a>
        @endforeach
    </nav>

    @if ($applications === [])
        <section class="panel-card border-dashed p-12 text-center"><p class="panel-muted">{{ __('company.applications.empty') }}</p></section>
    @else
        <section class="panel-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900/60"><tr>
                        <th class="px-5 py-4">{{ __('company.applications.candidate') }}</th><th class="px-5 py-4">{{ __('company.applications.position') }}</th><th class="px-5 py-4">{{ __('company.applications.stage') }}</th><th class="px-5 py-4">{{ __('company.applications.applied_at') }}</th><th class="px-5 py-4">{{ __('company.applications.retention') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($applications as $application)<tr>
                            <td class="px-5 py-4"><p class="font-semibold">{{ $application['candidate_name'] }}</p><p class="panel-muted mt-1 text-xs">{{ $application['candidate_email'] }}</p></td>
                            <td class="px-5 py-4">{{ $application['position_title'] }}</td>
                            <td class="px-5 py-4"><span class="company-context-card rounded-full px-2.5 py-1 text-xs font-semibold">{{ __('company.applications.stage_'.$application['current_stage']) }}</span></td>
                            <td class="px-5 py-4">{{ \Carbon\Carbon::parse($application['applied_at'])->format('d.m.Y H:i') }}</td>
                            <td class="px-5 py-4">{{ $application['retention_expires_at'] ? \Carbon\Carbon::parse($application['retention_expires_at'])->format('d.m.Y') : '—' }}</td>
                        </tr>@endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
