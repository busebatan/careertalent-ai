@extends('company.layouts.app')

@section('title', __('company.team.title'))

@section('content')
@php
    $currentPermissions = is_array($companyMembership['permissions'] ?? null) ? $companyMembership['permissions'] : [];
    $permissionKeys = is_array($team['permission_keys'] ?? null) ? $team['permission_keys'] : [];
    $permissionLabels = trans('company.permissions');
    $canManage = in_array('members.manage', $currentPermissions, true);
    $canInvite = in_array('members.invite', $currentPermissions, true);
    $isOwner = ($companyMembership['role'] ?? null) === 'owner';
    $assignablePermissions = $isOwner ? $permissionKeys : array_values(array_intersect($permissionKeys, $currentPermissions));
    $assignableRoles = $isOwner
        ? ['owner', 'admin', 'recruiter', 'hiring_manager', 'viewer']
        : ['admin', 'recruiter', 'hiring_manager', 'viewer'];
@endphp
<div class="mx-auto max-w-6xl">
    <header class="mb-8">
        <h1 class="text-3xl font-bold">{{ __('company.team.title') }}</h1>
        <p class="panel-muted mt-2">{{ __('company.team.subtitle') }}</p>
    </header>

    @if ($companyError)
        <p class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $companyError }}</p>
    @endif
    @if (session('company_invite_url'))
        <div class="company-feedback-success mb-6 rounded-xl border p-4">
            <p class="text-sm font-semibold">{{ __('company.team.invite_link') }}</p>
            <input class="panel-input-block mt-2" readonly value="{{ session('company_invite_url') }}">
        </div>
    @endif

    @if ($canInvite)
        <section class="panel-card mb-8 p-6">
            <h2 class="mb-5 text-lg font-semibold">{{ __('company.team.invite_title') }}</h2>
            <form data-company-invite-form method="post" action="{{ route('company.team.invite') }}" class="grid gap-5 md:grid-cols-2">
                @csrf
                <label class="text-sm font-medium">
                    {{ __('company.team.email') }}
                    <input class="panel-input-block mt-2" type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label class="text-sm font-medium">
                    {{ __('company.team.role') }}
                    <select class="panel-input-block mt-2" name="role" required>
                        @foreach ($assignableRoles as $role)
                            <option value="{{ $role }}" @selected(old('role') === $role)>{{ __('company.roles.'.$role) }}</option>
                        @endforeach
                    </select>
                </label>
                <fieldset class="md:col-span-2">
                    <legend class="mb-3 text-sm font-semibold">{{ __('company.team.permissions') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($assignablePermissions as $permission)
                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800">
                                <input class="mt-0.5" type="checkbox" name="permissions[]" value="{{ $permission }}" @checked($permission === 'dashboard.view' || in_array($permission, old('permissions', []), true)) @disabled($permission === 'dashboard.view')>
                                @if ($permission === 'dashboard.view')<input type="hidden" name="permissions[]" value="dashboard.view">@endif
                                <span>{{ $permissionLabels[$permission] ?? $permission }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="md:col-span-2"><button class="company-btn-primary" type="submit">{{ __('company.team.invite') }}</button></div>
            </form>
        </section>
    @endif

    <section class="space-y-4">
        @forelse ($team['members'] as $member)
            @php($memberPermissions = is_array($member['permissions'] ?? null) ? $member['permissions'] : [])
            <article class="panel-card p-5" data-company-member="{{ $member['membership_id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold">{{ $member['full_name'] }}</h2>
                        <p class="panel-muted text-sm">{{ $member['email'] }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ __('company.roles.'.$member['role']) }}</span>
                        <span class="rounded-full px-2.5 py-1 {{ $member['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-red-500/10 text-red-700 dark:text-red-300' }}">{{ __('company.status.'.$member['status']) }}</span>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-200 pt-4 text-xs dark:border-slate-800">
                    @foreach ($memberPermissions as $permission)
                        <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:text-emerald-300">{{ $permissionLabels[$permission] ?? $permission }}</span>
                    @endforeach
                </div>
                @if ($canManage && $member['user_id'] !== ($companyUser['id'] ?? null) && ($isOwner || $member['role'] !== 'owner'))
                    <details class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <summary class="cursor-pointer text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ __('company.team.edit') }}</summary>
                        <form data-company-member-form method="post" action="{{ route('company.team.update', ['membership' => $member['membership_id']]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                            @csrf @method('PATCH')
                            <label class="text-sm">
                                {{ __('company.team.role') }}
                                <select class="panel-input-block mt-2" name="role" required>
                                    @foreach ($assignableRoles as $role)
                                        <option value="{{ $role }}" @selected($member['role'] === $role)>{{ __('company.roles.'.$role) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm">
                                {{ __('company.team.status_label') }}
                                <select class="panel-input-block mt-2" name="status" required>
                                    <option value="active" @selected($member['status'] === 'active')>{{ __('company.status.active') }}</option>
                                    <option value="suspended" @selected($member['status'] === 'suspended')>{{ __('company.status.suspended') }}</option>
                                </select>
                            </label>
                            <fieldset class="md:col-span-2">
                                <legend class="mb-3 text-sm font-semibold">{{ __('company.team.permissions') }}</legend>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($assignablePermissions as $permission)
                                        <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800">
                                            <input class="mt-0.5" type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $memberPermissions, true)) @disabled($permission === 'dashboard.view')>
                                            @if ($permission === 'dashboard.view')<input type="hidden" name="permissions[]" value="dashboard.view">@endif
                                            <span>{{ $permissionLabels[$permission] ?? $permission }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="md:col-span-2"><button class="company-btn-secondary" type="submit">{{ __('company.team.save') }}</button></div>
                        </form>
                    </details>
                @endif
            </article>
        @empty
            <p class="panel-card p-6 text-sm text-slate-500">{{ __('company.team.empty') }}</p>
        @endforelse
    </section>

    @if (count($team['pending_invitations']))
        <h2 class="mt-8 text-lg font-semibold">{{ __('company.team.pending') }}</h2>
        <div class="mt-3 space-y-3">
            @foreach ($team['pending_invitations'] as $invite)
                <article class="panel-card p-4 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="font-medium">{{ $invite['email'] }}</span>
                        <span>{{ __('company.roles.'.$invite['role']) }}</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-200 pt-3 text-xs dark:border-slate-800">
                        @foreach (($invite['permissions'] ?? []) as $permission)
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ $permissionLabels[$permission] ?? $permission }}</span>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
