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
                    @include('company.partials.permission-selector', [
                        'permissionSelectorId' => 'company-invite-permissions',
                        'permissionKeys' => $assignablePermissions,
                        'selectedPermissions' => array_values(array_unique(array_merge(['dashboard.view'], old('permissions', [])))),
                        'permissionLabels' => $permissionLabels,
                    ])
                </fieldset>
                <div class="md:col-span-2"><button class="company-btn-primary" type="submit">{{ __('company.team.invite') }}</button></div>
            </form>
        </section>
    @endif

    <section class="space-y-4">
        @forelse ($team['members'] as $member)
            @php($memberPermissions = is_array($member['permissions'] ?? null) ? $member['permissions'] : [])
            <article class="panel-card p-5" data-company-member="{{ $member['membership_id'] }}">
                <details class="group">
                    <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-4 [&::-webkit-details-marker]:hidden">
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold">{{ $member['full_name'] }}</h2>
                            <p class="panel-muted text-sm">{{ $member['email'] }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ __('company.roles.'.$member['role']) }}</span>
                            <span class="rounded-full px-2.5 py-1 {{ $member['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-red-500/10 text-red-700 dark:text-red-300' }}">{{ __('company.status.'.$member['status']) }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600 group-open:hidden dark:bg-slate-800 dark:text-slate-300">{{ trans_choice('company.team.permission_count', count($memberPermissions), ['count' => count($memberPermissions)]) }}</span>
                            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                            <span class="sr-only">{{ __('company.team.show_permissions') }}</span>
                        </div>
                    </summary>
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-200 pt-4 text-xs dark:border-slate-800">
                        @foreach ($memberPermissions as $permission)
                            <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:text-emerald-300">{{ $permissionLabels[$permission] ?? $permission }}</span>
                        @endforeach
                    </div>
                </details>
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
                                @include('company.partials.permission-selector', [
                                    'permissionSelectorId' => 'company-member-'.$member['membership_id'].'-permissions',
                                    'permissionKeys' => $assignablePermissions,
                                    'selectedPermissions' => $memberPermissions,
                                    'permissionLabels' => $permissionLabels,
                                ])
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
                @php($invitePermissions = is_array($invite['permissions'] ?? null) ? $invite['permissions'] : [])
                <article class="panel-card p-4 text-sm">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                            <span class="font-medium">{{ $invite['email'] }}</span>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span>{{ __('company.roles.'.$invite['role']) }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600 group-open:hidden dark:bg-slate-800 dark:text-slate-300">{{ trans_choice('company.team.permission_count', count($invitePermissions), ['count' => count($invitePermissions)]) }}</span>
                                <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                                <span class="sr-only">{{ __('company.team.show_permissions') }}</span>
                            </div>
                        </summary>
                        <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-200 pt-3 text-xs dark:border-slate-800">
                            @foreach ($invitePermissions as $permission)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ $permissionLabels[$permission] ?? $permission }}</span>
                            @endforeach
                        </div>
                    </details>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
