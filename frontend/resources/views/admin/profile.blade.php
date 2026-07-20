@extends('admin.layouts.app')

@section('title', __('admin.profile.title'))

@section('content')
<div class="mx-auto max-w-4xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('admin.profile.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('admin.profile.subtitle') }}</p>
    </header>

    @if (($profile['must_change_password'] ?? false) === true)
        <p class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">{{ __('admin.profile.password_required') }}</p>
    @endif
    @if ($adminError)<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>@endif
    @if ($errors->has('profile'))<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('profile') }}</p>@endif

    <section class="panel-card p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
            <div>
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('admin.profile.identity') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('admin.roles.'.($profile['role'] ?? 'admin')) }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ ($profile['is_active'] ?? false) ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-red-500/10 text-red-700 dark:text-red-300' }}">
                {{ ($profile['is_active'] ?? false) ? __('admin.profile.active') : __('admin.profile.inactive') }}
            </span>
        </div>

        <form method="post" action="{{ route('admin.profile.update') }}" class="grid gap-5 md:grid-cols-2">
            @csrf @method('PATCH')
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('admin.profile.full_name') }}
                <input class="panel-input-block mt-2" name="full_name" value="{{ old('full_name', $profile['full_name'] ?? '') }}" required minlength="2" maxlength="100">
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('admin.profile.email') }}
                <input class="panel-input-block mt-2" name="email" type="email" value="{{ old('email', $profile['email'] ?? '') }}" required>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('admin.profile.current_password') }}
                <input class="panel-input-block mt-2" name="current_password" type="password" autocomplete="current-password" required minlength="8" maxlength="128">
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('admin.profile.new_password') }}
                <input class="panel-input-block mt-2" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="128" @required(($profile['must_change_password'] ?? false) === true)>
                <span class="mt-1 block text-xs text-slate-500">{{ __('admin.profile.new_password_hint') }}</span>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-start-2">{{ __('admin.accounts.temporary_password_confirmation') }}
                <input class="panel-input-block mt-2" name="new_password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="128" @required(($profile['must_change_password'] ?? false) === true)>
            </label>
            <div class="md:col-span-2"><button class="admin-btn-primary" type="submit">{{ __('admin.profile.save') }}</button></div>
        </form>
    </section>
</div>
@endsection
