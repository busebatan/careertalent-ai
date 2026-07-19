@extends('admin.layouts.app')

@section('title', __('admin.interviews.title'))

@section('content')
@php
    $canWrite = $isSuperAdmin || in_array('interviews.write', $adminPermissions, true);
    $canDelete = $isSuperAdmin || in_array('interviews.delete', $adminPermissions, true);
    $statuses = trans('admin.interviews.statuses');
    $languages = trans('admin.interviews.languages');
@endphp
<div class="mx-auto max-w-7xl">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold">{{ __('admin.interviews.title') }}</h1><p class="mt-1 text-slate-600 dark:text-slate-400">{{ __('admin.interviews.subtitle') }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ __('admin.interviews.total', ['count' => $total]) }}</span></header>
    @if (session('status'))<p class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($adminError)<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>@endif
    @if ($errors->has('interviews'))<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('interviews') }}</p>@endif
    @if ($canWrite && $studentOptions !== [])
        <section class="panel-card mb-8 p-6"><h2 class="text-lg font-semibold">{{ __('admin.interviews.create') }}</h2><p class="panel-muted mt-1 text-sm">{{ __('admin.interviews.ai_hint') }}</p>
            <form method="post" action="{{ route('admin.interviews.store') }}" class="mt-5 grid gap-4 md:grid-cols-[1fr_14rem_auto] md:items-end">@csrf
                <label class="text-sm">{{ __('admin.interviews.student') }}<select class="panel-input-block mt-2" name="user_id" required>@foreach ($studentOptions as $student)<option value="{{ $student['id'] }}">{{ $student['full_name'] }} · {{ $student['email'] }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('admin.interviews.language') }}<select class="panel-input-block mt-2" name="language">@foreach ($languages as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <button class="admin-btn-primary" type="submit">{{ __('admin.interviews.create') }}</button>
            </form>
        </section>
    @endif
    <section class="space-y-4">
        @forelse ($interviews as $interview)
            <article class="panel-card p-5" data-admin-interview="{{ $interview['id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="font-semibold">{{ $interview['target_role'] }}</h2><p class="panel-muted mt-1 text-sm">{{ $interview['student_name'] }} · {{ $interview['student_email'] }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ $statuses[$interview['status']] ?? $interview['status'] }}</span></div>
                <div class="panel-muted mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm"><span>{{ $languages[$interview['language']] ?? strtoupper($interview['language']) }}</span><span>{{ __('admin.interviews.questions', ['count' => $interview['question_count']]) }}</span><span>{{ __('admin.interviews.answers', ['count' => $interview['answer_count']]) }}</span><span>{{ __('admin.interviews.created_at', ['date' => $interview['created_at'] ?: '—']) }}</span></div>
                @if ($canWrite)<details class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800"><summary class="cursor-pointer text-sm font-semibold admin-accent-text">{{ __('admin.interviews.save') }}</summary><form method="post" action="{{ route('admin.interviews.update', $interview['id']) }}" class="mt-5 flex flex-wrap items-end gap-4">@csrf @method('PATCH')<label class="min-w-64 flex-1 text-sm">{{ __('admin.interviews.status') }}<select class="panel-input-block mt-2" name="status">@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($interview['status'] === $value)>{{ $label }}</option>@endforeach</select></label><button class="admin-btn-primary" type="submit">{{ __('admin.interviews.save') }}</button></form></details>@endif
                @if ($canDelete)<form method="post" action="{{ route('admin.interviews.destroy', $interview['id']) }}" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800" onsubmit="return confirm(@js(__('admin.interviews.confirm_delete')))">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600 dark:text-red-400" type="submit">{{ __('admin.interviews.delete') }}</button></form>@endif
            </article>
        @empty <p class="panel-card p-6 text-sm text-slate-500">{{ __('admin.interviews.empty') }}</p> @endforelse
    </section>
</div>
@endsection
