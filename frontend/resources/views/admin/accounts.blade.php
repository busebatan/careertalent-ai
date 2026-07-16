@extends('admin.layouts.app')

@section('title', __('admin.accounts.title'))

@section('content')
@php($permissionLabels = trans('admin.permissions'))
<div class="mx-auto max-w-7xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('admin.accounts.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('admin.accounts.subtitle') }}</p>
    </header>

    @if (session('status'))<p class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($adminError)<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>@endif
    @if ($errors->has('accounts'))<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('accounts') }}</p>@endif

    <section class="panel-card mb-8 p-6">
        <h2 class="mb-5 text-lg font-semibold text-slate-900 dark:text-white">{{ __('admin.accounts.create') }}</h2>
        <form method="post" action="{{ route('admin.accounts.store') }}" class="grid gap-5 md:grid-cols-2">
            @csrf
            <label class="text-sm font-medium">{{ __('admin.accounts.full_name') }}<input class="panel-input-block mt-2" name="full_name" value="{{ old('full_name') }}" required></label>
            <label class="text-sm font-medium">{{ __('admin.accounts.email') }}<input class="panel-input-block mt-2" name="email" type="email" value="{{ old('email') }}" required></label>
            <label class="text-sm font-medium">{{ __('admin.accounts.temporary_password') }}<input class="panel-input-block mt-2" name="temporary_password" type="password" autocomplete="new-password" required minlength="8"></label>
            <label class="text-sm font-medium">{{ __('admin.accounts.temporary_password_confirmation') }}<input class="panel-input-block mt-2" name="temporary_password_confirmation" type="password" autocomplete="new-password" required minlength="8"></label>
            <fieldset class="md:col-span-2">
                <legend class="mb-3 text-sm font-semibold">{{ __('admin.accounts.permissions') }}</legend>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissionKeys as $permission)
                        <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800">
                            <input class="mt-0.5" type="checkbox" name="permissions[]" value="{{ $permission }}" @checked($permission === 'dashboard.view' || in_array($permission, old('permissions', []), true)) @disabled($permission === 'dashboard.view')>
                            @if ($permission === 'dashboard.view')<input type="hidden" name="permissions[]" value="dashboard.view">@endif
                            <span>{{ $permissionLabels[$permission] ?? $permission }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="md:col-span-2"><button class="admin-btn-primary" type="submit">{{ __('admin.accounts.create') }}</button></div>
        </form>
    </section>

    <section class="space-y-4">
        @forelse ($accounts as $account)
            <article class="panel-card p-5" data-admin-account="{{ $account['id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 class="font-semibold text-slate-900 dark:text-white">{{ $account['full_name'] }}</h2><p class="text-sm text-slate-500">{{ $account['email'] }}</p></div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ __('admin.roles.'.$account['role']) }}</span>
                        <span class="rounded-full px-2.5 py-1 {{ $account['is_active'] ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-red-500/10 text-red-700 dark:text-red-300' }}">{{ $account['is_active'] ? __('admin.accounts.active') : __('admin.accounts.inactive') }}</span>
                    </div>
                </div>
                @if ($account['role'] === 'admin')
                    <details class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <summary class="cursor-pointer text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ __('admin.accounts.save') }}</summary>
                        <form method="post" action="{{ route('admin.accounts.update', $account['id']) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                            @csrf @method('PATCH')
                            <label class="text-sm">{{ __('admin.accounts.full_name') }}<input class="panel-input-block mt-2" name="full_name" value="{{ $account['full_name'] }}" required></label>
                            <label class="text-sm">{{ __('admin.accounts.email') }}<input class="panel-input-block mt-2" name="email" type="email" value="{{ $account['email'] }}" required></label>
                            <label class="text-sm">{{ __('admin.accounts.status') }}<select class="panel-input-block mt-2" name="is_active"><option value="1" @selected($account['is_active'])>{{ __('admin.accounts.active') }}</option><option value="0" @selected(! $account['is_active'])>{{ __('admin.accounts.inactive') }}</option></select></label>
                            <label class="text-sm">{{ __('admin.accounts.reset_password') }}<input class="panel-input-block mt-2" name="temporary_password" type="password" autocomplete="new-password" minlength="8"></label>
                            <fieldset class="md:col-span-2"><legend class="mb-3 text-sm font-semibold">{{ __('admin.accounts.permissions') }}</legend><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($permissionKeys as $permission)
                                    <label class="flex gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $account['admin_permissions'], true)) @disabled($permission === 'dashboard.view')>@if ($permission === 'dashboard.view')<input type="hidden" name="permissions[]" value="dashboard.view">@endif<span>{{ $permissionLabels[$permission] ?? $permission }}</span></label>
                                @endforeach
                            </div></fieldset>
                            <div class="md:col-span-2"><button class="admin-btn-primary" type="submit">{{ __('admin.accounts.save') }}</button></div>
                        </form>
                    </details>
                @endif
            </article>
        @empty
            <p class="panel-card p-6 text-sm text-slate-500">{{ __('admin.accounts.empty') }}</p>
        @endforelse
    </section>
</div>
@endsection
