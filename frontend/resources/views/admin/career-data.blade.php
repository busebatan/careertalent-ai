@extends('admin.layouts.app')

@section('title', __('career-data.title'))

@section('content')
@php
    $inputClass = 'mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white';
    $labelClass = 'text-sm font-medium text-slate-700 dark:text-slate-200';
    $buttonClass = 'rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500';
    $deleteClass = 'rounded-lg border border-red-500/30 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-500/10 dark:text-red-200';
    $types = ['technical' => 'Technical', 'domain' => 'Domain', 'soft' => 'Soft', 'tool' => 'Tool'];
    $sourceTypes = ['official' => 'Official', 'report' => 'Report', 'market' => 'Market', 'manual' => 'Manual'];
@endphp
<div class="mx-auto max-w-7xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('career-data.title') }}</h1>
        <p class="max-w-3xl text-slate-600 dark:text-slate-400">{{ __('career-data.subtitle') }}</p>
    </header>

    <nav class="panel-card mb-6 flex flex-wrap gap-1 p-2" aria-label="{{ __('career-data.title') }}">
        @foreach ($tabs as $tab)
            <a href="{{ route('admin.career-data', ['tab' => $tab['key']]) }}"
                class="rounded-lg px-3 py-2 text-sm font-medium {{ $activeTab === $tab['key'] ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    @if ($adminError)
        <p class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>
    @elseif ($errors->any())
        <p class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first() }}</p>
    @endif

    @if (! $adminError)
    @if ($activeTab === 'roles')
        <section class="panel-card mb-6 p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.create') }}</h2>
            <form method="POST" action="{{ route('admin.career-data.store', 'roles') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="{{ $labelClass }}">{{ __('career-data.role.slug') }}<input class="{{ $inputClass }}" name="slug" value="{{ old('slug') }}" required></label>
                <label class="{{ $labelClass }}">{{ __('career-data.role.title') }}<input class="{{ $inputClass }}" name="title" value="{{ old('title') }}" required></label>
                <label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.role.description') }}<textarea class="{{ $inputClass }}" name="description" rows="2">{{ old('description') }}</textarea></label>
                <label class="{{ $labelClass }}">{{ __('career-data.role.weeks') }}<input class="{{ $inputClass }}" type="number" min="1" max="104" name="weeks_template" value="{{ old('weeks_template', 12) }}" required></label>
                <div class="flex items-end"><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div>
            </form>
        </section>
        <section class="panel-card overflow-hidden">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.records') }}</h2></div>
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($records['roles'] as $role)
                    <article class="p-5">
                        <div class="grid gap-3 md:grid-cols-[1fr_8rem_9rem_auto] md:items-center">
                            <div><p class="font-semibold text-slate-900 dark:text-white">{{ $role['title'] }}</p><p class="panel-muted mt-1 text-sm">{{ $role['slug'] }} · {{ $role['description'] ?: '—' }}</p></div>
                            <p class="text-sm font-semibold admin-accent-text">{{ $role['weeks_template'] }} hafta</p>
                            <p class="panel-muted text-sm">{{ $role['requirement_count'] }} {{ __('career-data.role.skills') }}</p>
                            <form method="POST" action="{{ route('admin.career-data.destroy', ['resource' => 'roles', 'record' => $role['id']]) }}">@csrf @method('DELETE')<button class="{{ $deleteClass }}" type="submit">{{ __('career-data.delete') }}</button></form>
                        </div>
                        <p class="panel-muted mt-3 text-sm">{{ implode(', ', $role['required_skills']) ?: '—' }}</p>
                        <details class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-800"><summary class="cursor-pointer text-sm font-medium admin-accent-text">{{ __('career-data.edit') }}</summary>
                            <form method="POST" action="{{ route('admin.career-data.update', ['resource' => 'roles', 'record' => $role['id']]) }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf @method('PUT')
                                <label class="{{ $labelClass }}">{{ __('career-data.role.slug') }}<input class="{{ $inputClass }}" name="slug" value="{{ $role['slug'] }}" required></label>
                                <label class="{{ $labelClass }}">{{ __('career-data.role.title') }}<input class="{{ $inputClass }}" name="title" value="{{ $role['title'] }}" required></label>
                                <label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.role.description') }}<textarea class="{{ $inputClass }}" name="description" rows="2">{{ $role['description'] }}</textarea></label>
                                <label class="{{ $labelClass }}">{{ __('career-data.role.weeks') }}<input class="{{ $inputClass }}" type="number" min="1" max="104" name="weeks_template" value="{{ $role['weeks_template'] }}" required></label>
                                <div class="flex items-end"><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div>
                            </form>
                        </details>
                    </article>
                @empty
                    <p class="panel-muted p-5 text-sm">{{ __('career-data.empty') }}</p>
                @endforelse
            </div>
        </section>
    @elseif ($activeTab === 'skills')
        <section class="panel-card mb-6 p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.create') }}</h2>
            <form method="POST" action="{{ route('admin.career-data.store', 'skills') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="{{ $labelClass }}">{{ __('career-data.skill.slug') }}<input class="{{ $inputClass }}" name="slug" value="{{ old('slug') }}" required></label>
                <label class="{{ $labelClass }}">{{ __('career-data.skill.name') }}<input class="{{ $inputClass }}" name="name" value="{{ old('name') }}" required></label>
                <label class="{{ $labelClass }}">{{ __('career-data.skill.type') }}<select class="{{ $inputClass }}" name="skill_type">@foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="{{ $labelClass }} flex items-end gap-2"><input type="checkbox" name="is_active" value="1" checked> {{ __('career-data.skill.active') }}</label>
                <label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.skill.description') }}<textarea class="{{ $inputClass }}" name="description" rows="2">{{ old('description') }}</textarea></label>
                <div><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div>
            </form>
        </section>
        <section class="panel-card overflow-hidden"><div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.records') }}</h2></div><div class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse ($records['skills'] as $skill)
                <article class="p-5"><div class="grid gap-3 md:grid-cols-[1fr_8rem_8rem_auto] md:items-center"><div><p class="font-semibold text-slate-900 dark:text-white">{{ $skill['name'] }}</p><p class="panel-muted mt-1 text-sm">{{ $skill['slug'] }} · {{ $skill['description'] ?: '—' }}</p></div><p class="text-sm font-semibold admin-accent-text">{{ $types[$skill['skill_type']] }}</p><p class="panel-muted text-sm">{{ $skill['requirement_count'] }} {{ __('career-data.skill.usage') }}</p><form method="POST" action="{{ route('admin.career-data.destroy', ['resource' => 'skills', 'record' => $skill['id']]) }}">@csrf @method('DELETE')<button class="{{ $deleteClass }}" type="submit">{{ __('career-data.delete') }}</button></form></div>
                    <details class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-800"><summary class="cursor-pointer text-sm font-medium admin-accent-text">{{ __('career-data.edit') }}</summary><form method="POST" action="{{ route('admin.career-data.update', ['resource' => 'skills', 'record' => $skill['id']]) }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf @method('PUT')<label class="{{ $labelClass }}">{{ __('career-data.skill.slug') }}<input class="{{ $inputClass }}" name="slug" value="{{ $skill['slug'] }}" required></label><label class="{{ $labelClass }}">{{ __('career-data.skill.name') }}<input class="{{ $inputClass }}" name="name" value="{{ $skill['name'] }}" required></label><label class="{{ $labelClass }}">{{ __('career-data.skill.type') }}<select class="{{ $inputClass }}" name="skill_type">@foreach ($types as $value => $label)<option value="{{ $value }}" @selected($skill['skill_type'] === $value)>{{ $label }}</option>@endforeach</select></label><label class="{{ $labelClass }} flex items-end gap-2"><input type="checkbox" name="is_active" value="1" @checked($skill['is_active'])> {{ __('career-data.skill.active') }}</label><label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.skill.description') }}<textarea class="{{ $inputClass }}" name="description" rows="2">{{ $skill['description'] }}</textarea></label><div><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div></form></details>
                </article>
            @empty <p class="panel-muted p-5 text-sm">{{ __('career-data.empty') }}</p> @endforelse
        </div></section>
    @elseif ($activeTab === 'requirements')
        <section class="panel-card mb-6 p-6"><h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.create') }}</h2>
            @if (count($records['roles']) && count($records['skills']))
                <form method="POST" action="{{ route('admin.career-data.store', 'requirements') }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf
                    <label class="{{ $labelClass }}">{{ __('career-data.requirement.role') }}<select class="{{ $inputClass }}" name="career_role_id" required>@foreach ($records['roles'] as $role)<option value="{{ $role['id'] }}">{{ $role['title'] }}</option>@endforeach</select></label>
                    <label class="{{ $labelClass }}">{{ __('career-data.requirement.skill') }}<select class="{{ $inputClass }}" name="career_skill_id" required>@foreach ($records['skills'] as $skill)<option value="{{ $skill['id'] }}">{{ $skill['name'] }}</option>@endforeach</select></label>
                    <label class="{{ $labelClass }}">{{ __('career-data.requirement.source') }}<select class="{{ $inputClass }}" name="data_source_id"><option value="">—</option>@foreach ($records['sources'] as $source)<option value="{{ $source['id'] }}">{{ $source['name'] }}</option>@endforeach</select></label>
                    <label class="{{ $labelClass }}">{{ __('career-data.requirement.type') }}<select class="{{ $inputClass }}" name="requirement_type"><option value="required">Required</option><option value="preferred">Preferred</option></select></label>
                    <label class="{{ $labelClass }}">{{ __('career-data.requirement.level') }}<select class="{{ $inputClass }}" name="expected_level"><option value="basic">Basic</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option><option value="expert">Expert</option></select></label>
                    <label class="{{ $labelClass }}">{{ __('career-data.requirement.weight') }}<input class="{{ $inputClass }}" type="number" name="weight" min="1" max="100" value="100" required></label>
                    <label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.requirement.notes') }}<textarea class="{{ $inputClass }}" name="notes" rows="2"></textarea></label><div><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div>
                </form>
            @else <p class="panel-muted mt-3 text-sm">{{ __('career-data.tabs.roles') }} ve {{ __('career-data.tabs.skills') }} kayıtları gerekir.</p> @endif
        </section>
        <section class="panel-card overflow-hidden"><div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.records') }}</h2></div><div class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse ($records['requirements'] as $requirement)
                <article class="p-5"><div class="grid gap-3 md:grid-cols-[1fr_1fr_8rem_8rem_auto] md:items-center"><div><p class="font-semibold text-slate-900 dark:text-white">{{ $requirement['career_role_title'] }}</p><p class="panel-muted mt-1 text-sm">{{ $requirement['career_skill_name'] }}</p></div><p class="panel-muted text-sm">{{ $requirement['data_source_name'] ?: '—' }}</p><p class="text-sm font-semibold admin-accent-text">{{ $requirement['weight'] }}</p><p class="panel-muted text-sm">{{ $requirement['requirement_type'] }} · {{ $requirement['expected_level'] }}</p><form method="POST" action="{{ route('admin.career-data.destroy', ['resource' => 'requirements', 'record' => $requirement['id']]) }}">@csrf @method('DELETE')<button class="{{ $deleteClass }}" type="submit">{{ __('career-data.delete') }}</button></form></div><details class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-800"><summary class="cursor-pointer text-sm font-medium admin-accent-text">{{ __('career-data.edit') }}</summary><form method="POST" action="{{ route('admin.career-data.update', ['resource' => 'requirements', 'record' => $requirement['id']]) }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf @method('PUT')<label class="{{ $labelClass }}">{{ __('career-data.requirement.role') }}<select class="{{ $inputClass }}" name="career_role_id">@foreach ($records['roles'] as $role)<option value="{{ $role['id'] }}" @selected($role['id'] === $requirement['career_role_id'])>{{ $role['title'] }}</option>@endforeach</select></label><label class="{{ $labelClass }}">{{ __('career-data.requirement.skill') }}<select class="{{ $inputClass }}" name="career_skill_id">@foreach ($records['skills'] as $skill)<option value="{{ $skill['id'] }}" @selected($skill['id'] === $requirement['career_skill_id'])>{{ $skill['name'] }}</option>@endforeach</select></label><label class="{{ $labelClass }}">{{ __('career-data.requirement.source') }}<select class="{{ $inputClass }}" name="data_source_id"><option value="">—</option>@foreach ($records['sources'] as $source)<option value="{{ $source['id'] }}" @selected($source['id'] === $requirement['data_source_id'])>{{ $source['name'] }}</option>@endforeach</select></label><label class="{{ $labelClass }}">{{ __('career-data.requirement.type') }}<select class="{{ $inputClass }}" name="requirement_type"><option value="required" @selected($requirement['requirement_type'] === 'required')>Required</option><option value="preferred" @selected($requirement['requirement_type'] === 'preferred')>Preferred</option></select></label><label class="{{ $labelClass }}">{{ __('career-data.requirement.level') }}<select class="{{ $inputClass }}" name="expected_level">@foreach (['basic', 'intermediate', 'advanced', 'expert'] as $level)<option value="{{ $level }}" @selected($requirement['expected_level'] === $level)>{{ ucfirst($level) }}</option>@endforeach</select></label><label class="{{ $labelClass }}">{{ __('career-data.requirement.weight') }}<input class="{{ $inputClass }}" type="number" name="weight" min="1" max="100" value="{{ $requirement['weight'] }}" required></label><label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.requirement.notes') }}<textarea class="{{ $inputClass }}" name="notes" rows="2">{{ $requirement['notes'] }}</textarea></label><div><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div></form></details></article>
            @empty <p class="panel-muted p-5 text-sm">{{ __('career-data.empty') }}</p> @endforelse
        </div></section>
    @else
        <section class="panel-card mb-6 p-6"><h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.create') }}</h2><form method="POST" action="{{ route('admin.career-data.store', 'sources') }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf
            <label class="{{ $labelClass }}">{{ __('career-data.source.slug') }}<input class="{{ $inputClass }}" name="slug" required></label><label class="{{ $labelClass }}">{{ __('career-data.source.name') }}<input class="{{ $inputClass }}" name="name" required></label><label class="{{ $labelClass }}">{{ __('career-data.source.type') }}<select class="{{ $inputClass }}" name="source_type">@foreach ($sourceTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label class="{{ $labelClass }}">{{ __('career-data.source.status') }}<select class="{{ $inputClass }}" name="status"><option value="active">Active</option><option value="archived">Archived</option></select></label><label class="{{ $labelClass }}">{{ __('career-data.source.reference_uri') }}<input class="{{ $inputClass }}" name="reference_uri"></label><label class="{{ $labelClass }}">{{ __('career-data.source.version') }}<input class="{{ $inputClass }}" name="version"></label><label class="{{ $labelClass }}">{{ __('career-data.source.checksum') }}<input class="{{ $inputClass }}" name="checksum_sha256" minlength="64" maxlength="64"></label><label class="{{ $labelClass }}">{{ __('career-data.source.license') }}<input class="{{ $inputClass }}" name="license"></label><label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.source.url') }}<input class="{{ $inputClass }}" name="url"></label><label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.source.description') }}<textarea class="{{ $inputClass }}" name="description" rows="2"></textarea></label><div><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div>
        </form></section>
        <section class="panel-card overflow-hidden"><div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('career-data.records') }}</h2></div><div class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse ($records['sources'] as $source)
                <article class="p-5"><div class="grid gap-3 md:grid-cols-[1fr_8rem_8rem_auto] md:items-center"><div><p class="font-semibold text-slate-900 dark:text-white">{{ $source['name'] }}</p><p class="panel-muted mt-1 text-sm">{{ $source['reference_uri'] ?: $source['url'] ?: '—' }}</p></div><p class="text-sm font-semibold admin-accent-text">{{ $source['source_type'] }}</p><p class="panel-muted text-sm">{{ $source['requirement_count'] }} kullanım</p><form method="POST" action="{{ route('admin.career-data.destroy', ['resource' => 'sources', 'record' => $source['id']]) }}">@csrf @method('DELETE')<button class="{{ $deleteClass }}" type="submit">{{ __('career-data.delete') }}</button></form></div><p class="panel-muted mt-3 text-sm">{{ $source['version'] ?: '—' }} · {{ $source['status'] }} · {{ $source['checksum_sha256'] ?: '—' }}</p><details class="mt-4 border-t border-slate-200 pt-3 dark:border-slate-800"><summary class="cursor-pointer text-sm font-medium admin-accent-text">{{ __('career-data.edit') }}</summary><form method="POST" action="{{ route('admin.career-data.update', ['resource' => 'sources', 'record' => $source['id']]) }}" class="mt-4 grid gap-4 md:grid-cols-2">@csrf @method('PUT')<label class="{{ $labelClass }}">{{ __('career-data.source.slug') }}<input class="{{ $inputClass }}" name="slug" value="{{ $source['slug'] }}" required></label><label class="{{ $labelClass }}">{{ __('career-data.source.name') }}<input class="{{ $inputClass }}" name="name" value="{{ $source['name'] }}" required></label><label class="{{ $labelClass }}">{{ __('career-data.source.type') }}<select class="{{ $inputClass }}" name="source_type">@foreach ($sourceTypes as $value => $label)<option value="{{ $value }}" @selected($source['source_type'] === $value)>{{ $label }}</option>@endforeach</select></label><label class="{{ $labelClass }}">{{ __('career-data.source.status') }}<select class="{{ $inputClass }}" name="status"><option value="active" @selected($source['status'] === 'active')>Active</option><option value="archived" @selected($source['status'] === 'archived')>Archived</option></select></label><label class="{{ $labelClass }}">{{ __('career-data.source.reference_uri') }}<input class="{{ $inputClass }}" name="reference_uri" value="{{ $source['reference_uri'] }}"></label><label class="{{ $labelClass }}">{{ __('career-data.source.version') }}<input class="{{ $inputClass }}" name="version" value="{{ $source['version'] }}"></label><label class="{{ $labelClass }}">{{ __('career-data.source.checksum') }}<input class="{{ $inputClass }}" name="checksum_sha256" minlength="64" maxlength="64" value="{{ $source['checksum_sha256'] }}"></label><label class="{{ $labelClass }}">{{ __('career-data.source.license') }}<input class="{{ $inputClass }}" name="license" value="{{ $source['license'] }}"></label><label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.source.url') }}<input class="{{ $inputClass }}" name="url" value="{{ $source['url'] }}"></label><label class="{{ $labelClass }} md:col-span-2">{{ __('career-data.source.description') }}<textarea class="{{ $inputClass }}" name="description" rows="2">{{ $source['description'] }}</textarea></label><div><button class="{{ $buttonClass }}" type="submit">{{ __('career-data.save') }}</button></div></form></details></article>
            @empty <p class="panel-muted p-5 text-sm">{{ __('career-data.empty') }}</p> @endforelse
        </div></section>
    @endif
    @endif
</div>
@endsection
