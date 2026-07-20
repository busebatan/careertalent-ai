@extends('company.layouts.app')
@section('title', __('company_positions.new'))
@section('content')
@php
    $activeMembers = collect($members)->where('status', 'active');
    $recruiters = $activeMembers->whereIn('role', ['owner', 'admin', 'recruiter']);
    $technicalManagers = $activeMembers->whereIn('role', ['owner', 'admin', 'hiring_manager']);
    $organizationTerms = collect($atsConfig['terms'] ?? [])->map(fn($value, $key) => is_int($key) ? $value : $key.'='.$value)->implode("\n");
@endphp
<div class="mx-auto max-w-6xl">
    <header class="mb-8">
        <a class="company-accent-text inline-flex items-center gap-2 text-sm font-semibold" href="{{ route('company.positions') }}"><i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>{{ __('company_positions.title') }}</a>
        <h1 class="mt-4 text-3xl font-bold tracking-tight">{{ __('company_positions.new') }}</h1>
        <p class="panel-muted mt-2">{{ __('company_positions.fields.source_hint') }}</p>
    </header>

    <form method="post" action="{{ route('company.positions.create') }}" class="space-y-6">
        @csrf
        <section class="panel-card p-6">
            <div class="flex items-center gap-3"><span class="company-dashboard-icon"><i data-lucide="clipboard-paste" class="h-5 w-5" aria-hidden="true"></i></span><div><h2 class="text-lg font-semibold">{{ __('company_positions.fields.source_text') }}</h2><p class="panel-muted text-sm">{{ __('company_positions.fields.source_hint') }}</p></div></div>
            <textarea class="panel-input-block mt-5 min-h-44" name="source_text" placeholder="Backend Developer…">{{ old('source_text') }}</textarea>
        </section>

        <section class="panel-card p-6">
            <h2 class="text-lg font-semibold">{{ __('company.positions.create_section_basic') }}</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.title') }}<input class="panel-input-block mt-2" name="title" value="{{ old('title') }}" required></label>
                <label class="text-sm">{{ __('company_positions.fields.department') }}<input class="panel-input-block mt-2" name="department" value="{{ old('department') }}"></label>
                <label class="text-sm">{{ __('company_positions.fields.level') }}<select class="panel-input-block mt-2" name="level"><option value="">—</option>@foreach(['intern','junior','mid','senior','lead','manager','director'] as $value)<option value="{{ $value }}" @selected(old('level')===$value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('company_positions.fields.employment_type') }}<select class="panel-input-block mt-2" name="employment_type"><option value="">—</option>@foreach(['full_time','part_time','contract','internship'] as $value)<option value="{{ $value }}" @selected(old('employment_type')===$value)>{{ __('company.positions.employment_'.$value) }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('company_positions.fields.workplace_type') }}<select class="panel-input-block mt-2" name="workplace_type"><option value="">—</option>@foreach(['onsite','hybrid','remote'] as $value)<option value="{{ $value }}" @selected(old('workplace_type')===$value)>{{ __('company.positions.workplace_'.$value) }}</option>@endforeach</select></label>
                <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.location') }}<input class="panel-input-block mt-2" name="location" value="{{ old('location') }}"></label>
                <fieldset class="lg:col-span-3"><legend class="text-sm">{{ __('company_positions.fields.salary') }} <span class="panel-muted">({{ __('company_positions.fields.salary_optional') }})</span></legend><div class="mt-2 grid gap-3 sm:grid-cols-[1fr_1fr_120px]"><input class="panel-input-block" type="number" min="0" name="salary_min" value="{{ old('salary_min') }}" placeholder="Min"><input class="panel-input-block" type="number" min="0" name="salary_max" value="{{ old('salary_max') }}" placeholder="Max"><input class="panel-input-block uppercase" name="salary_currency" value="{{ old('salary_currency','TRY') }}" maxlength="3"></div></fieldset>
            </div>
        </section>

        <section class="panel-card p-6">
            <h2 class="text-lg font-semibold">{{ __('company.positions.create_section_details') }}</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="text-sm md:col-span-2">{{ __('company_positions.fields.description') }}<textarea class="panel-input-block mt-2 min-h-28" name="description">{{ old('description') }}</textarea></label>
                <label class="text-sm md:col-span-2">{{ __('company_positions.fields.responsibilities') }}<textarea class="panel-input-block mt-2 min-h-32" name="responsibilities">{{ old('responsibilities') }}</textarea></label>
                <label class="text-sm">{{ __('company_positions.fields.must_have_skills') }}<textarea class="panel-input-block mt-2 min-h-32" name="must_have_skills" placeholder="Her satıra bir yetenek">{{ old('must_have_skills') }}</textarea></label>
                <label class="text-sm">{{ __('company_positions.fields.preferred_skills') }}<textarea class="panel-input-block mt-2 min-h-32" name="preferred_skills">{{ old('preferred_skills') }}</textarea></label>
                <label class="text-sm">{{ __('company_positions.fields.learnable_skills') }}<textarea class="panel-input-block mt-2 min-h-32" name="learnable_skills">{{ old('learnable_skills') }}</textarea></label>
                <label class="text-sm">{{ __('company_positions.fields.experience_expectation') }}<textarea class="panel-input-block mt-2 min-h-32" name="experience_expectation">{{ old('experience_expectation') }}</textarea></label>
                <label class="text-sm md:col-span-2">{{ __('company_positions.fields.language_work_authorization') }}<textarea class="panel-input-block mt-2 min-h-24" name="language_work_authorization">{{ old('language_work_authorization') }}</textarea></label>
                <label class="text-sm">{{ __('company_positions.fields.application_deadline') }}<input class="panel-input-block mt-2" type="date" name="application_deadline" value="{{ old('application_deadline') }}"></label>
                <label class="text-sm">{{ __('company_positions.fields.target_start_date') }}<input class="panel-input-block mt-2" type="date" name="target_start_date" value="{{ old('target_start_date') }}"></label>
            </div>
        </section>

        <section class="panel-card p-6">
            <h2 class="text-lg font-semibold">{{ __('company_positions.ats.title') }}</h2>
            <p class="panel-muted mt-1 text-sm">{{ __('company_positions.ats.rule') }}</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="text-sm">{{ __('company_positions.fields.ats_terms') }}<textarea class="panel-input-block mt-2 min-h-36" name="ats_terms" placeholder="{{ __('company_positions.fields.ats_terms_hint') }}">{{ old('ats_terms') }}</textarea><span class="panel-muted mt-2 block text-xs">{{ __('company_positions.fields.ats_terms_hint') }}</span></label>
                <label class="text-sm">{{ __('company_positions.fields.ats_notes') }}<textarea class="panel-input-block mt-2 min-h-36" name="ats_notes">{{ old('ats_notes') }}</textarea></label>
                @if($organizationTerms !== '')<details class="md:col-span-2 rounded-xl border border-emerald-500/20 p-4"><summary class="company-accent-text cursor-pointer text-sm font-semibold">{{ __('company_positions.ats.nav') }}</summary><pre class="mt-3 whitespace-pre-wrap text-sm">{{ $organizationTerms }}</pre></details>@endif
            </div>
        </section>

        <section class="panel-card p-6">
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm">{{ __('company_positions.fields.recruiter') }}<select class="panel-input-block mt-2" name="recruiter_membership_id"><option value="">—</option>@foreach($recruiters as $member)<option value="{{ $member['membership_id'] }}" @selected(old('recruiter_membership_id')===$member['membership_id'])>{{ $member['full_name'] }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('company_positions.fields.technical_manager') }}<select class="panel-input-block mt-2" name="technical_manager_membership_id"><option value="">—</option>@foreach($technicalManagers as $member)<option value="{{ $member['membership_id'] }}" @selected(old('technical_manager_membership_id')===$member['membership_id'])>{{ $member['full_name'] }}</option>@endforeach</select></label>
                <label class="text-sm">{{ __('company_positions.fields.retention_days') }}<input class="panel-input-block mt-2" type="number" min="1" max="3650" name="retention_days" value="{{ old('retention_days',180) }}"></label>
                <label class="text-sm">{{ __('company_positions.fields.status') }}<select class="panel-input-block mt-2" name="status"><option value="draft" @selected(old('status','draft')==='draft')>{{ __('company_positions.status.draft') }}</option><option value="published" @selected(old('status')==='published')>{{ __('company_positions.status.published') }}</option></select></label>
                <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.application_form_id') }}<input class="panel-input-block mt-2" name="application_form_id" value="{{ old('application_form_id') }}"></label>
                <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.assessment_template_id') }}<input class="panel-input-block mt-2" name="assessment_template_id" value="{{ old('assessment_template_id') }}"></label>
                <label class="text-sm">{{ __('company_positions.fields.estimated_application_minutes') }}<input class="panel-input-block mt-2" type="number" min="1" max="180" name="estimated_application_minutes" value="{{ old('estimated_application_minutes', 8) }}"></label>
                <label class="text-sm">{{ __('company_positions.fields.estimated_assessment_minutes') }}<input class="panel-input-block mt-2" type="number" min="1" max="600" name="estimated_assessment_minutes" value="{{ old('estimated_assessment_minutes', 35) }}"></label>
                <label class="text-sm">Değerlendirme süresi (dk)<input class="panel-input-block mt-2" type="number" min="1" max="600" name="assessment_duration_minutes" value="{{ old('assessment_duration_minutes', 35) }}"></label>
                <label class="text-sm">Başarı eşiği (%)<input class="panel-input-block mt-2" type="number" min="0" max="100" name="success_threshold" value="{{ old('success_threshold', 70) }}"></label>
                <label class="text-sm lg:col-span-2">Kullanılan görevler<textarea class="panel-input-block mt-2 min-h-24" name="assessment_tasks">{{ old('assessment_tasks') }}</textarea></label>
                <label class="text-sm lg:col-span-2">İzin verilen araçlar<textarea class="panel-input-block mt-2 min-h-24" name="allowed_tools">{{ old('allowed_tools') }}</textarea></label>
                <label class="text-sm lg:col-span-4">Puanlama anahtarı<textarea class="panel-input-block mt-2 min-h-24" name="scoring_rubric">{{ old('scoring_rubric') }}</textarea></label>
                <label class="flex items-center gap-3 text-sm lg:col-span-4"><input type="hidden" name="human_review_required" value="0"><input type="checkbox" name="human_review_required" value="1" @checked(old('human_review_required', true))>İnsan incelemesi zorunlu</label>
            </div>
            <div class="mt-6 flex flex-wrap justify-end gap-3"><a class="company-btn-secondary" href="{{ route('company.positions') }}">{{ __('company_positions.actions.cancel') }}</a><button class="company-btn-primary" type="submit">{{ __('company_positions.actions.save') }}</button></div>
        </section>
    </form>
</div>
@endsection
