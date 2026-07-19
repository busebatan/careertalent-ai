@extends('admin.layouts.app')

@section('title', __('admin.students.title'))

@section('content')
@php
    $canWrite = $isSuperAdmin || in_array('students.write', $adminPermissions, true);
    $canDelete = $isSuperAdmin || in_array('students.delete', $adminPermissions, true);
@endphp
<div class="mx-auto max-w-7xl">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('admin.students.title') }}</h1><p class="mt-1 text-slate-600 dark:text-slate-400">{{ __('admin.students.subtitle') }}</p></div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ __('admin.students.total', ['count' => $total]) }}</span>
    </header>
    @if (session('status'))<p class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($adminError)<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>@endif
    @if ($errors->has('students'))<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('students') }}</p>@endif

    @if ($canWrite)
        <section class="panel-card mb-8 p-6">
            <h2 class="mb-5 text-lg font-semibold">{{ __('admin.students.create') }}</h2>
            <form method="post" action="{{ route('admin.students.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <label class="text-sm">{{ __('admin.students.full_name') }}<input class="panel-input-block mt-2" name="full_name" value="{{ old('full_name') }}" required></label>
                <label class="text-sm">{{ __('admin.students.email') }}<input class="panel-input-block mt-2" name="email" type="email" value="{{ old('email') }}" required></label>
                <label class="text-sm">{{ __('admin.students.locale') }}<select class="panel-input-block mt-2" name="preferred_locale"><option value="tr">Türkçe</option><option value="en" @selected(old('preferred_locale') === 'en')>English</option></select></label>
                <label class="text-sm">{{ __('admin.students.temporary_password') }}<input class="panel-input-block mt-2" name="temporary_password" type="password" minlength="8" required></label>
                <label class="text-sm">{{ __('admin.students.temporary_password_confirmation') }}<input class="panel-input-block mt-2" name="temporary_password_confirmation" type="password" minlength="8" required></label>
                <label class="text-sm">{{ __('admin.students.status') }}<select class="panel-input-block mt-2" name="is_active"><option value="1">{{ __('admin.students.active') }}</option><option value="0">{{ __('admin.students.inactive') }}</option></select></label>
                <div class="md:col-span-2 xl:col-span-3"><button class="admin-btn-primary" type="submit">{{ __('admin.students.create') }}</button></div>
            </form>
        </section>
    @endif

    <section class="space-y-4">
        @forelse ($students as $student)
            <article class="panel-card p-5" data-admin-student="{{ $student['id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 class="font-semibold">{{ $student['full_name'] }}</h2><p class="panel-muted mt-1 text-sm">{{ $student['email'] }} · {{ strtoupper($student['preferred_locale']) }}</p></div>
                    <span class="rounded-full px-3 py-1 text-xs {{ $student['is_active'] ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-red-500/10 text-red-700 dark:text-red-300' }}">{{ $student['is_active'] ? __('admin.students.active') : __('admin.students.inactive') }}</span>
                </div>
                @if ($canWrite)
                    <details class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <summary class="cursor-pointer text-sm font-semibold admin-accent-text">{{ __('admin.students.save') }}</summary>
                        <form method="post" action="{{ route('admin.students.update', $student['id']) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @csrf @method('PATCH')
                            <label class="text-sm">{{ __('admin.students.full_name') }}<input class="panel-input-block mt-2" name="full_name" value="{{ $student['full_name'] }}" required></label>
                            <label class="text-sm">{{ __('admin.students.email') }}<input class="panel-input-block mt-2" name="email" type="email" value="{{ $student['email'] }}" required></label>
                            <label class="text-sm">{{ __('admin.students.locale') }}<select class="panel-input-block mt-2" name="preferred_locale"><option value="tr" @selected($student['preferred_locale'] === 'tr')>Türkçe</option><option value="en" @selected($student['preferred_locale'] === 'en')>English</option></select></label>
                            <label class="text-sm">{{ __('admin.students.status') }}<select class="panel-input-block mt-2" name="is_active"><option value="1" @selected($student['is_active'])>{{ __('admin.students.active') }}</option><option value="0" @selected(! $student['is_active'])>{{ __('admin.students.inactive') }}</option></select></label>
                            <label class="text-sm md:col-span-2">{{ __('admin.students.reset_password') }}<input class="panel-input-block mt-2" name="temporary_password" type="password" minlength="8"></label>
                            <div class="md:col-span-2 xl:col-span-3"><button class="admin-btn-primary" type="submit">{{ __('admin.students.save') }}</button></div>
                        </form>
                    </details>
                @endif
                @if ($canDelete && $student['is_active'])
                    <form method="post" action="{{ route('admin.students.destroy', $student['id']) }}" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800" onsubmit="return confirm(@js(__('admin.students.confirm_delete')))">
                        @csrf @method('DELETE')
                        <button class="text-sm font-semibold text-red-600 dark:text-red-400" type="submit">{{ __('admin.students.delete') }}</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="panel-card p-6 text-sm text-slate-500">{{ __('admin.students.empty') }}</p>
        @endforelse
    </section>
</div>
@endsection
