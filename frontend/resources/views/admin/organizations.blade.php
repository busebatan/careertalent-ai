@extends('admin.layouts.app')

@section('title', __('admin.organizations.title'))

@section('content')
@php
    $types = trans('admin.organizations.types');
    $sizes = trans('admin.organizations.sizes');
    $statuses = trans('admin.organizations.statuses');
    $plans = trans('admin.organizations.plans');
@endphp
<div class="mx-auto max-w-7xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('admin.organizations.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('admin.organizations.subtitle') }}</p>
    </header>

    @if (session('status'))<p class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($adminError)<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>@endif
    @if ($errors->has('organizations'))<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('organizations') }}</p>@endif

    <section class="panel-card mb-8 p-6">
        @if(session('company_invite_url'))<div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4"><p class="text-sm font-semibold">{{ __('admin.organizations.owner_invite_link') }}</p><input class="panel-input-block mt-2" readonly value="{{ session('company_invite_url') }}"></div>@endif
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('admin.organizations.create') }}</h2>
                <p class="panel-muted mt-1 text-sm">{{ __('admin.organizations.create_hint') }}</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ trans_choice('admin.organizations.total', $organizationsTotal, ['count' => $organizationsTotal]) }}</span>
        </div>
        <form method="post" action="{{ route('admin.organizations.store') }}" class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @csrf
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.name') }}<input class="panel-input-block mt-2" name="name" value="{{ old('name') }}" required></label>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-950/50 xl:col-span-2">
                <span class="font-medium">{{ __('admin.organizations.profile_url') }}</span>
                <p class="panel-muted mt-2 text-xs">{{ __('admin.organizations.profile_url_auto') }}</p>
            </div>
            <label class="text-sm font-medium">{{ __('admin.organizations.type') }}<select class="panel-input-block mt-2" name="organization_type">@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('organization_type', 'employer') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">{{ __('admin.organizations.size') }}<select class="panel-input-block mt-2" name="size_band">@foreach ($sizes as $value => $label)<option value="{{ $value }}" @selected(old('size_band', 'smb') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">{{ __('admin.organizations.status') }}<select class="panel-input-block mt-2" name="status">@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', 'onboarding') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">{{ __('admin.organizations.plan') }}<select class="panel-input-block mt-2" name="plan_code">@foreach ($plans as $value => $label)<option value="{{ $value }}" @selected(old('plan_code', 'pilot') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.billing_email') }}<input class="panel-input-block mt-2" name="billing_email" type="email" value="{{ old('billing_email') }}" required></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.owner_email') }}<input class="panel-input-block mt-2" name="owner_email" type="email" value="{{ old('owner_email') }}" required></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.website') }}<input class="panel-input-block mt-2" name="website" type="url" value="{{ old('website') }}" placeholder="https://"></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.logo_url') }}<input class="panel-input-block mt-2" name="logo_url" type="url" value="{{ old('logo_url') }}" placeholder="https://"></label>
            <label class="text-sm font-medium xl:col-span-4">{{ __('admin.organizations.description') }}<textarea class="panel-input-block mt-2 min-h-28" name="description" maxlength="1000">{{ old('description') }}</textarea></label>
            <div class="xl:col-span-4"><button class="admin-btn-primary" type="submit">{{ __('admin.organizations.create') }}</button></div>
        </form>
    </section>

    <section class="space-y-4">
        @forelse ($organizations as $organization)
            <article class="panel-card p-5" data-admin-organization="{{ $organization['id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-white">{{ $organization['name'] }}</h2>
                        <p class="panel-muted mt-1 text-sm">{{ $organization['billing_email'] }} · {{ $organization['slug'] }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ $types[$organization['organization_type']] ?? $organization['organization_type'] }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ $sizes[$organization['size_band']] ?? $organization['size_band'] }}</span>
                        <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-amber-700 dark:text-amber-300">{{ $plans[$organization['plan_code']] ?? $organization['plan_code'] }}</span>
                        <span class="rounded-full px-2.5 py-1 {{ $organization['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">{{ $statuses[$organization['status']] ?? $organization['status'] }}</span>
                    </div>
                </div>
                <div class="panel-muted mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs">
                    <span>{{ trans_choice('admin.organizations.members', $organization['members_count'], ['count' => $organization['members_count']]) }}</span>
                    <a class="admin-accent-text" href="{{ route('company.organization.login', $organization['slug']) }}" target="_blank" rel="noreferrer">{{ route('company.organization.login', $organization['slug']) }}</a>
                    @if (! empty($organization['website']))<a class="admin-accent-text" href="{{ $organization['website'] }}" target="_blank" rel="noreferrer">{{ $organization['website'] }}</a>@endif
                    <span>{{ __('admin.organizations.created_at', ['date' => $organization['created_at']]) }}</span>
                </div>
                <form method="post" action="{{ route('admin.organizations.owner-invite', $organization['id']) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    @csrf
                    <label class="min-w-64 flex-1 text-sm">{{ __('admin.organizations.owner_email') }}<input class="panel-input-block mt-2" name="owner_email" type="email" required></label>
                    <button class="panel-btn-secondary" type="submit">{{ __('admin.organizations.owner_invite') }}</button>
                </form>
                <details class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <summary class="cursor-pointer text-sm font-semibold admin-accent-text">{{ __('admin.organizations.edit') }}</summary>
                    <form method="post" action="{{ route('admin.organizations.update', $organization['id']) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @csrf @method('PATCH')
                        <label class="text-sm xl:col-span-2">{{ __('admin.organizations.name') }}<input class="panel-input-block mt-2" name="name" value="{{ $organization['name'] }}" required></label>
                        <label class="text-sm xl:col-span-2">{{ __('admin.organizations.slug') }}<input class="panel-input-block mt-2" name="slug" value="{{ $organization['slug'] }}" required></label>
                        <label class="text-sm">{{ __('admin.organizations.type') }}<select class="panel-input-block mt-2" name="organization_type">@foreach ($types as $value => $label)<option value="{{ $value }}" @selected($organization['organization_type'] === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm">{{ __('admin.organizations.size') }}<select class="panel-input-block mt-2" name="size_band">@foreach ($sizes as $value => $label)<option value="{{ $value }}" @selected($organization['size_band'] === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm">{{ __('admin.organizations.status') }}<select class="panel-input-block mt-2" name="status">@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($organization['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm">{{ __('admin.organizations.plan') }}<select class="panel-input-block mt-2" name="plan_code">@foreach ($plans as $value => $label)<option value="{{ $value }}" @selected($organization['plan_code'] === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm xl:col-span-2">{{ __('admin.organizations.billing_email') }}<input class="panel-input-block mt-2" name="billing_email" type="email" value="{{ $organization['billing_email'] }}" required></label>
                        <label class="text-sm xl:col-span-2">{{ __('admin.organizations.website') }}<input class="panel-input-block mt-2" name="website" type="url" value="{{ $organization['website'] ?? '' }}"></label>
                        <label class="text-sm xl:col-span-2">{{ __('admin.organizations.logo_url') }}<input class="panel-input-block mt-2" name="logo_url" type="url" value="{{ $organization['logo_url'] ?? '' }}"></label>
                        <label class="text-sm xl:col-span-4">{{ __('admin.organizations.description') }}<textarea class="panel-input-block mt-2 min-h-28" name="description" maxlength="1000">{{ $organization['description'] ?? '' }}</textarea></label>
                        <div class="xl:col-span-4"><button class="admin-btn-primary" type="submit">{{ __('admin.organizations.save') }}</button></div>
                    </form>
                </details>
            </article>
        @empty
            <p class="panel-card p-6 text-sm text-slate-500">{{ __('admin.organizations.empty') }}</p>
        @endforelse
    </section>
</div>
@endsection
