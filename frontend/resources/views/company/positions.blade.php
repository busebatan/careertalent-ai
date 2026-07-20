@extends('company.layouts.app')
@section('title', __('company.positions.title'))
@section('content')
@php $permissions = $companyMembership['permissions'] ?? []; @endphp
<div class="mx-auto max-w-7xl">
    <div class="mb-8">
        <p class="company-accent-text text-sm font-semibold">{{ $companyMembership['organization_name'] }}</p>
        <h1 class="mt-1 text-3xl font-bold">{{ __('company.positions.title') }}</h1>
        <p class="panel-muted mt-2">{{ __('company.positions.subtitle') }}</p>
    </div>

    @if (session('status'))<div class="company-feedback-success mb-6 rounded-xl border p-4 text-sm font-semibold">{{ session('status') }}</div>@endif
    @if ($companyError)<div class="panel-card mb-6 border-red-500/30 p-5 text-red-500">{{ $companyError }}</div>@endif
    @if ($errors->any())<div class="panel-card mb-6 border-red-500/30 p-5 text-sm text-red-500">{{ $errors->first() }}</div>@endif

    @if (in_array('positions.write', $permissions, true))
        <details class="panel-card mb-6 p-6" @if($errors->any()) open @endif>
            <summary class="cursor-pointer list-none text-lg font-semibold">{{ __('company.positions.create') }}</summary>
            <form method="post" action="{{ route('company.positions.create') }}" class="mt-6 grid gap-5 md:grid-cols-2">
                @csrf
                <label class="text-sm md:col-span-2">{{ __('company.positions.title_label') }}<input class="panel-input-block mt-2" name="title" value="{{ old('title') }}" required></label>
                <label class="text-sm">{{ __('company.positions.department') }}<input class="panel-input-block mt-2" name="department" value="{{ old('department') }}"></label>
                <label class="text-sm">{{ __('company.positions.status') }}<select class="panel-input-block mt-2" name="status" required>@foreach(['draft','open','paused','closed'] as $value)<option value="{{ $value }}" @selected(old('status','draft')===$value)>{{ __('company.positions.status_'.$value) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('company.positions.employment_type') }}<select class="panel-input-block mt-2" name="employment_type"><option value="">—</option>@foreach(['full_time','part_time','contract','internship'] as $value)<option value="{{ $value }}" @selected(old('employment_type')===$value)>{{ __('company.positions.employment_'.$value) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('company.positions.workplace_type') }}<select class="panel-input-block mt-2" name="workplace_type"><option value="">—</option>@foreach(['onsite','hybrid','remote'] as $value)<option value="{{ $value }}" @selected(old('workplace_type')===$value)>{{ __('company.positions.workplace_'.$value) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('company.positions.deadline') }}<input class="panel-input-block mt-2" type="datetime-local" name="application_deadline" value="{{ old('application_deadline') }}"></label>
                <label class="text-sm md:col-span-2">{{ __('company.positions.description') }}<textarea class="panel-input-block mt-2 min-h-28" name="description">{{ old('description') }}</textarea></label>
                <div class="md:col-span-2"><button class="company-btn-primary" type="submit">{{ __('company.positions.save') }}</button></div>
            </form>
        </details>
    @endif

    <nav class="mb-5 flex flex-wrap gap-2" aria-label="{{ __('company.positions.status') }}">
        <a class="{{ $positionStatus === null ? 'company-btn-primary' : 'company-btn-secondary' }}" href="{{ route('company.positions') }}">{{ __('company.positions.all') }}</a>
        @foreach(['open','draft','paused','closed','archived'] as $status)
            <a class="{{ $positionStatus === $status ? 'company-btn-primary' : 'company-btn-secondary' }}" href="{{ route('company.positions', ['status' => $status]) }}">{{ __('company.positions.status_'.$status) }}</a>
        @endforeach
    </nav>

    @if ($positions === [])
        <section class="panel-card border-dashed p-12 text-center"><p class="panel-muted">{{ __('company.positions.empty') }}</p></section>
    @else
        <div class="space-y-4">
            @foreach ($positions as $position)
                <article id="position-{{ $position['id'] }}" class="panel-card p-6">
                    <div class="flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <div class="flex flex-wrap items-center gap-3"><h2 class="text-lg font-semibold">{{ $position['title'] }}</h2><span class="rounded-full px-2.5 py-1 text-xs font-semibold company-context-card">{{ __('company.positions.status_'.$position['status']) }}</span></div>
                            <p class="panel-muted mt-2 text-sm">{{ $position['department'] ?: '—' }} · {{ __('company.positions.candidate_count', ['count' => $position['application_count'] ?? 0]) }}</p>
                            @if($position['application_deadline'])<p class="panel-muted mt-1 text-xs">{{ __('company.positions.deadline') }}: {{ \Carbon\Carbon::parse($position['application_deadline'])->format('d.m.Y H:i') }}</p>@endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if(in_array('applications.view', $permissions, true))<a class="company-btn-secondary" href="{{ route('company.positions.applications', ['position' => $position['id']]) }}">{{ __('company.positions.applications') }}</a>@endif
                            @if(in_array('positions.delete', $permissions, true) && $position['status'] !== 'archived')
                                <form method="post" action="{{ route('company.positions.delete', ['position' => $position['id']]) }}" onsubmit="return confirm('{{ __('company.positions.archive') }}?')">@csrf @method('DELETE')<button class="company-btn-secondary" type="submit">{{ __('company.positions.archive') }}</button></form>
                            @endif
                        </div>
                    </div>
                    @if(in_array('positions.write', $permissions, true) && $position['status'] !== 'archived')
                        <details class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                            <summary class="company-accent-text cursor-pointer text-sm font-semibold">{{ __('company.positions.edit') }}</summary>
                            <form method="post" action="{{ route('company.positions.update', ['position' => $position['id']]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                                @csrf @method('PATCH')
                                <label class="text-sm md:col-span-2">{{ __('company.positions.title_label') }}<input class="panel-input-block mt-2" name="title" value="{{ $position['title'] }}" required></label>
                                <label class="text-sm">{{ __('company.positions.department') }}<input class="panel-input-block mt-2" name="department" value="{{ $position['department'] }}"></label>
                                <label class="text-sm">{{ __('company.positions.status') }}<select class="panel-input-block mt-2" name="status">@foreach(['draft','open','paused','closed'] as $value)<option value="{{ $value }}" @selected($position['status']===$value)>{{ __('company.positions.status_'.$value) }}</option>@endforeach</select></label>
                                <label class="text-sm">{{ __('company.positions.employment_type') }}<select class="panel-input-block mt-2" name="employment_type"><option value="">—</option>@foreach(['full_time','part_time','contract','internship'] as $value)<option value="{{ $value }}" @selected(($position['employment_type']??null)===$value)>{{ __('company.positions.employment_'.$value) }}</option>@endforeach</select></label>
                                <label class="text-sm">{{ __('company.positions.workplace_type') }}<select class="panel-input-block mt-2" name="workplace_type"><option value="">—</option>@foreach(['onsite','hybrid','remote'] as $value)<option value="{{ $value }}" @selected(($position['workplace_type']??null)===$value)>{{ __('company.positions.workplace_'.$value) }}</option>@endforeach</select></label>
                                <label class="text-sm">{{ __('company.positions.deadline') }}<input class="panel-input-block mt-2" type="datetime-local" name="application_deadline" value="{{ $position['application_deadline'] ? \Carbon\Carbon::parse($position['application_deadline'])->format('Y-m-d\TH:i') : '' }}"></label>
                                <label class="text-sm md:col-span-2">{{ __('company.positions.description') }}<textarea class="panel-input-block mt-2 min-h-28" name="description">{{ $position['description'] }}</textarea></label>
                                <div class="md:col-span-2"><button class="company-btn-primary" type="submit">{{ __('company.positions.update') }}</button></div>
                            </form>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
