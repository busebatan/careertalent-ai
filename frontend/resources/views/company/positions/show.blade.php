@extends('company.layouts.app')
@section('title', data_get($positionDetail, 'position.title', __('company_positions.detail')))
@section('content')
@php
    $position = $positionDetail['position'];
    $permissions = $companyMembership['permissions'] ?? [];
    $canWrite = in_array('positions.write', $permissions, true);
@endphp
<div class="mx-auto max-w-[1480px]">
    <header class="mb-6">
        <a class="company-accent-text inline-flex items-center gap-2 text-sm font-semibold" href="{{ route('company.positions') }}"><i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>{{ __('company_positions.title') }}</a>
        <div class="mt-4 flex flex-wrap items-start justify-between gap-5">
            <div>
                <div class="flex flex-wrap items-center gap-3"><h1 class="text-3xl font-bold tracking-tight">{{ $position['title'] }}</h1><span class="company-context-card rounded-full px-3 py-1 text-xs font-semibold">{{ __('company_positions.status.'.($position['status'] ?? 'draft')) }}</span></div>
                <p class="panel-muted mt-2">{{ $position['department'] ?? '—' }} · {{ $position['location'] ?? '—' }} · {{ isset($position['workplace_type']) ? __('company.positions.workplace_'.$position['workplace_type']) : '—' }}</p>
            </div>
            @if($canWrite)
                <form method="post" action="{{ route('company.positions.analyze', ['position' => $position['id']]) }}">@csrf<button class="company-btn-primary" type="submit"><i data-lucide="sparkles" class="h-4 w-4" aria-hidden="true"></i>{{ __('company_positions.ai.analyze') }}</button></form>
            @endif
        </div>
    </header>

    <nav class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200 pb-3 dark:border-slate-800" aria-label="{{ __('company_positions.detail') }}">
        @foreach($positionTabs as $tab)
            <a class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition {{ $positionTab === $tab ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-900' }}" href="{{ route('company.positions.show', ['position' => $position['id'], 'tab' => $tab]) }}">{{ __('company_positions.tabs.'.$tab) }}</a>
        @endforeach
    </nav>

    @include('company.positions.tabs.'.$positionTab, compact('position', 'positionDetail', 'permissions', 'canWrite'))
</div>
@endsection
