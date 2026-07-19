@extends('company.layouts.app')
@section('title', __('company.dashboard.title'))
@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-8"><p class="company-accent-text text-sm font-semibold">{{ $companyMembership['organization_name'] }}</p><h1 class="mt-1 text-3xl font-bold">{{ __('company.dashboard.title') }}</h1><p class="panel-muted mt-2">{{ __('company.dashboard.subtitle') }}</p></div>
    @if ($companyError)<div class="panel-card border-red-500/30 p-5 text-red-500">{{ $companyError }}</div>
    @else
        <section class="grid gap-4 md:grid-cols-3">
            <article class="panel-card p-5"><p class="panel-muted text-sm">{{ __('company.dashboard.members_total') }}</p><p class="mt-2 text-3xl font-bold">{{ $dashboard['members_total'] }}</p></article>
            <article class="panel-card p-5"><p class="panel-muted text-sm">{{ __('company.dashboard.members_active') }}</p><p class="company-accent-text mt-2 text-3xl font-bold">{{ $dashboard['members_active'] }}</p></article>
            <article class="panel-card p-5"><p class="panel-muted text-sm">{{ __('company.dashboard.invitations') }}</p><p class="mt-2 text-3xl font-bold">{{ $dashboard['invitations_pending'] }}</p></article>
        </section>
        <section class="panel-card mt-6 p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-lg font-semibold">{{ __('company.dashboard.foundation_title') }}</h2><p class="panel-muted mt-2 max-w-2xl text-sm">{{ __('company.dashboard.foundation_text') }}</p></div>@if (in_array('members.view', $companyMembership['permissions'], true))<a class="company-btn-primary" href="{{ route('company.team') }}">{{ __('company.dashboard.manage_team') }}</a>@endif</div></section>
    @endif
</div>
@endsection
