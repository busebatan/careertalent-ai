@extends('admin.layouts.app')

@section('title', __('admin.applications.title'))

@section('content')
@php
    $canWrite = $isSuperAdmin || in_array('applications.write', $adminPermissions, true);
    $canDelete = $isSuperAdmin || in_array('applications.delete', $adminPermissions, true);
    $stages = trans('admin.applications.stages');
@endphp
<div class="mx-auto max-w-7xl">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ __('admin.applications.title') }}</h1><p class="mt-1 text-slate-600 dark:text-slate-400">{{ __('admin.applications.subtitle') }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ __('admin.applications.total', ['count' => $total]) }}</span></header>
    @if (session('status'))<p class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($adminError)<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>@endif
    @if ($errors->has('applications'))<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('applications') }}</p>@endif
    @if ($canWrite && $studentOptions !== [])
        <section class="panel-card mb-8 p-6"><h2 class="mb-5 text-lg font-semibold">{{ __('admin.applications.create') }}</h2>
            <form method="post" action="{{ route('admin.applications.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">@csrf
                <label class="text-sm">{{ __('admin.applications.student') }}<select class="panel-input-block mt-2" name="user_id" required>@foreach ($studentOptions as $student)<option value="{{ $student['id'] }}">{{ $student['full_name'] }} · {{ $student['email'] }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('admin.applications.company') }}<input class="panel-input-block mt-2" name="company" value="{{ old('company') }}" required></label>
                <label class="text-sm">{{ __('admin.applications.role') }}<input class="panel-input-block mt-2" name="role" value="{{ old('role') }}" required></label>
                <label class="text-sm">{{ __('admin.applications.stage') }}<select class="panel-input-block mt-2" name="stage">@foreach ($stages as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm md:col-span-2">{{ __('admin.applications.next_action') }}<input class="panel-input-block mt-2" name="next_action" value="{{ old('next_action') }}"></label>
                <label class="text-sm md:col-span-2 xl:col-span-3">{{ __('admin.applications.note') }}<textarea class="panel-input-block mt-2" name="note" rows="2">{{ old('note') }}</textarea></label>
                <div class="md:col-span-2 xl:col-span-3"><button class="admin-btn-primary" type="submit">{{ __('admin.applications.create') }}</button></div>
            </form>
        </section>
    @endif
    <section class="space-y-4">
        @forelse ($applications as $application)
            <article class="panel-card p-5" data-admin-application="{{ $application['id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="font-semibold">{{ $application['company'] }} · {{ $application['role'] }}</h2><p class="panel-muted mt-1 text-sm">{{ $application['student_name'] }} · {{ $application['student_email'] }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ $stages[$application['stage']] ?? $application['stage'] }}</span></div>
                <div class="panel-muted mt-4 grid gap-2 text-sm md:grid-cols-2"><p>{{ __('admin.applications.next_action') }}: {{ $application['next_action'] ?: '—' }}</p><p>{{ __('admin.applications.applied_at') }}: {{ $application['applied_at'] ?: '—' }}</p>@if ($application['note'])<p class="md:col-span-2">{{ $application['note'] }}</p>@endif</div>
                @if ($canWrite)<details class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800"><summary class="cursor-pointer text-sm font-semibold admin-accent-text">{{ __('admin.applications.save') }}</summary>
                    <form method="post" action="{{ route('admin.applications.update', $application['id']) }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf @method('PATCH')
                        <label class="text-sm">{{ __('admin.applications.company') }}<input class="panel-input-block mt-2" name="company" value="{{ $application['company'] }}" required></label><label class="text-sm">{{ __('admin.applications.role') }}<input class="panel-input-block mt-2" name="role" value="{{ $application['role'] }}" required></label>
                        <label class="text-sm">{{ __('admin.applications.stage') }}<select class="panel-input-block mt-2" name="stage">@foreach ($stages as $value => $label)<option value="{{ $value }}" @selected($application['stage'] === $value)>{{ $label }}</option>@endforeach</select></label><label class="text-sm">{{ __('admin.applications.next_action') }}<input class="panel-input-block mt-2" name="next_action" value="{{ $application['next_action'] }}"></label>
                        <label class="text-sm md:col-span-2">{{ __('admin.applications.note') }}<textarea class="panel-input-block mt-2" name="note" rows="2">{{ $application['note'] }}</textarea></label><div class="md:col-span-2"><button class="admin-btn-primary" type="submit">{{ __('admin.applications.save') }}</button></div>
                    </form></details>@endif
                @if ($canDelete)<form method="post" action="{{ route('admin.applications.destroy', $application['id']) }}" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800" onsubmit="return confirm(@js(__('admin.applications.confirm_delete')))">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600 dark:text-red-400" type="submit">{{ __('admin.applications.delete') }}</button></form>@endif
            </article>
        @empty <p class="panel-card p-6 text-sm text-slate-500">{{ __('admin.applications.empty') }}</p> @endforelse
    </section>
</div>
@endsection
