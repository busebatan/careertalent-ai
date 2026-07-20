@php
    $currentStatus = $position['status'] ?? 'draft';
    $statusOptions = match ($currentStatus) {
        'draft' => ['draft', 'published'],
        'published' => ['published', 'paused', 'closed'],
        'paused' => ['paused', 'published', 'closed'],
        'closed' => ['closed'],
        default => [],
    };
    $members = collect($positionDetail['members'] ?? []);
    $recruiters = $members->whereIn('role', ['owner', 'admin', 'recruiter']);
    $technicalManagers = $members->whereIn('role', ['owner', 'admin', 'hiring_manager']);
    $evaluation = $position['evaluation_config'] ?? [];
    $editable = $canWrite && $currentStatus !== 'archived';
@endphp
<div class="space-y-6">
    <section class="panel-card p-6">
        <h2 class="text-lg font-semibold">{{ __('company_positions.tabs.settings') }}</h2>
        @if($editable)
            <form class="mt-5 space-y-6" method="post" action="{{ route('company.positions.update', ['position' => $position['id']]) }}">
                @csrf @method('PATCH')
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.title') }}<input class="panel-input-block mt-2" name="title" value="{{ old('title', $position['title']) }}" required></label>
                    <label class="text-sm">{{ __('company_positions.fields.department') }}<input class="panel-input-block mt-2" name="department" value="{{ old('department', $position['department'] ?? '') }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.level') }}<select class="panel-input-block mt-2" name="level"><option value="">—</option>@foreach(['intern','junior','mid','senior','lead','manager','director'] as $value)<option value="{{ $value }}" @selected(old('level', $position['level'] ?? '')===$value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
                    <label class="text-sm">{{ __('company_positions.fields.employment_type') }}<select class="panel-input-block mt-2" name="employment_type"><option value="">—</option>@foreach(['full_time','part_time','contract','internship'] as $value)<option value="{{ $value }}" @selected(old('employment_type', $position['employment_type'] ?? '')===$value)>{{ __('company.positions.employment_'.$value) }}</option>@endforeach</select></label>
                    <label class="text-sm">{{ __('company_positions.fields.workplace_type') }}<select class="panel-input-block mt-2" name="workplace_type"><option value="">—</option>@foreach(['onsite','hybrid','remote'] as $value)<option value="{{ $value }}" @selected(old('workplace_type', $position['workplace_type'] ?? '')===$value)>{{ __('company.positions.workplace_'.$value) }}</option>@endforeach</select></label>
                    <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.location') }}<input class="panel-input-block mt-2" name="location" value="{{ old('location', $position['location'] ?? '') }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.status') }}<select class="panel-input-block mt-2" name="status">@foreach($statusOptions as $status)<option value="{{ $status }}" @selected(old('status', $currentStatus)===$status)>{{ __('company_positions.status.'.$status) }}</option>@endforeach</select></label>
                    <label class="text-sm">{{ __('company_positions.fields.salary') }} Min<input class="panel-input-block mt-2" type="number" min="0" name="salary_min" value="{{ old('salary_min', $position['salary_min'] ?? '') }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.salary') }} Max<input class="panel-input-block mt-2" type="number" min="0" name="salary_max" value="{{ old('salary_max', $position['salary_max'] ?? '') }}"></label>
                    <label class="text-sm">Para birimi<input class="panel-input-block mt-2 uppercase" name="salary_currency" maxlength="3" value="{{ old('salary_currency', $position['salary_currency'] ?? 'TRY') }}"></label>
                    <label class="text-sm lg:col-span-3">{{ __('company_positions.fields.description') }}<textarea class="panel-input-block mt-2 min-h-24" name="description">{{ old('description', $position['description'] ?? '') }}</textarea></label>
                    <label class="text-sm lg:col-span-3">{{ __('company_positions.fields.responsibilities') }}<textarea class="panel-input-block mt-2 min-h-32" name="responsibilities">{{ old('responsibilities', $position['responsibilities'] ?? '') }}</textarea></label>
                    @foreach(['must_have_skills','preferred_skills','learnable_skills'] as $field)
                        <label class="text-sm">{{ __('company_positions.fields.'.$field) }}<textarea class="panel-input-block mt-2 min-h-28" name="{{ $field }}">{{ old($field, implode("\n", $position[$field] ?? [])) }}</textarea></label>
                    @endforeach
                    <label class="text-sm">{{ __('company_positions.fields.experience_expectation') }}<textarea class="panel-input-block mt-2 min-h-28" name="experience_expectation">{{ old('experience_expectation', $position['experience_expectation'] ?? '') }}</textarea></label>
                    <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.language_work_authorization') }}<textarea class="panel-input-block mt-2 min-h-28" name="language_work_authorization">{{ old('language_work_authorization', $position['language_work_authorization'] ?? '') }}</textarea></label>
                    <label class="text-sm lg:col-span-3">{{ __('company_positions.fields.source_text') }}<textarea class="panel-input-block mt-2 min-h-32" name="source_text">{{ old('source_text', $position['source_text'] ?? '') }}</textarea></label>
                    <label class="text-sm">{{ __('company_positions.fields.application_deadline') }}<input class="panel-input-block mt-2" type="date" name="application_deadline" value="{{ old('application_deadline', !empty($position['application_deadline']) ? \Carbon\Carbon::parse($position['application_deadline'])->format('Y-m-d') : '') }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.target_start_date') }}<input class="panel-input-block mt-2" type="date" name="target_start_date" value="{{ old('target_start_date', $position['target_start_date'] ?? '') }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.retention_days') }}<input class="panel-input-block mt-2" type="number" min="1" max="3650" name="retention_days" value="{{ old('retention_days', $position['retention_days'] ?? 180) }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.recruiter') }}<select class="panel-input-block mt-2" name="recruiter_membership_id"><option value="">—</option>@foreach($recruiters as $member)<option value="{{ $member['membership_id'] }}" @selected(old('recruiter_membership_id', $position['recruiter_membership_id'] ?? '')===$member['membership_id'])>{{ $member['full_name'] }}</option>@endforeach</select></label>
                    <label class="text-sm">{{ __('company_positions.fields.technical_manager') }}<select class="panel-input-block mt-2" name="technical_manager_membership_id"><option value="">—</option>@foreach($technicalManagers as $member)<option value="{{ $member['membership_id'] }}" @selected(old('technical_manager_membership_id', $position['technical_manager_membership_id'] ?? '')===$member['membership_id'])>{{ $member['full_name'] }}</option>@endforeach</select></label>
                    <label class="text-sm">{{ __('company_positions.fields.ats_terms') }}<textarea class="panel-input-block mt-2 min-h-28" name="ats_terms">{{ old('ats_terms', implode("\n", $position['ats_terms'] ?? [])) }}</textarea></label>
                    <label class="text-sm lg:col-span-2">{{ __('company_positions.fields.ats_notes') }}<textarea class="panel-input-block mt-2 min-h-28" name="ats_notes">{{ old('ats_notes', $position['ats_notes'] ?? '') }}</textarea></label>
                    <label class="text-sm">{{ __('company_positions.fields.application_form_id') }}<input class="panel-input-block mt-2" name="application_form_id" value="{{ old('application_form_id', $position['application_form_id'] ?? '') }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.assessment_template_id') }}<input class="panel-input-block mt-2" name="assessment_template_id" value="{{ old('assessment_template_id', $position['assessment_template_id'] ?? '') }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.estimated_application_minutes') }}<input class="panel-input-block mt-2" type="number" min="1" max="180" name="estimated_application_minutes" value="{{ old('estimated_application_minutes', $evaluation['estimated_application_minutes'] ?? 8) }}"></label>
                    <label class="text-sm">{{ __('company_positions.fields.estimated_assessment_minutes') }}<input class="panel-input-block mt-2" type="number" min="1" max="600" name="estimated_assessment_minutes" value="{{ old('estimated_assessment_minutes', $evaluation['estimated_assessment_minutes'] ?? 35) }}"></label>
                    <label class="text-sm">Değerlendirme süresi (dk)<input class="panel-input-block mt-2" type="number" min="1" max="600" name="assessment_duration_minutes" value="{{ old('assessment_duration_minutes', $evaluation['duration_minutes'] ?? 35) }}"></label>
                    <label class="text-sm">Başarı eşiği (%)<input class="panel-input-block mt-2" type="number" min="0" max="100" name="success_threshold" value="{{ old('success_threshold', $evaluation['success_threshold'] ?? 70) }}"></label>
                    <label class="text-sm lg:col-span-2">Kullanılan görevler<textarea class="panel-input-block mt-2 min-h-24" name="assessment_tasks">{{ old('assessment_tasks', implode("\n", $evaluation['tasks'] ?? [])) }}</textarea></label>
                    <label class="text-sm">İzin verilen araçlar<textarea class="panel-input-block mt-2 min-h-24" name="allowed_tools">{{ old('allowed_tools', implode("\n", $evaluation['allowed_tools'] ?? [])) }}</textarea></label>
                    <label class="text-sm lg:col-span-3">Puanlama anahtarı<textarea class="panel-input-block mt-2 min-h-24" name="scoring_rubric">{{ old('scoring_rubric', $evaluation['rubric'] ?? '') }}</textarea></label>
                    <label class="flex items-center gap-3 text-sm lg:col-span-3"><input type="hidden" name="human_review_required" value="0"><input type="checkbox" name="human_review_required" value="1" @checked(old('human_review_required', $evaluation['human_review_required'] ?? true))>İnsan incelemesi zorunlu</label>
                </div>
                <div class="flex justify-end"><button class="company-btn-primary" type="submit">{{ __('company_positions.actions.save') }}</button></div>
            </form>
        @else
            <p class="panel-muted mt-4">Arşivlenmiş pozisyon salt okunur durumdadır.</p>
        @endif
    </section>

    <section class="panel-card border-red-500/20 p-6">
        <h2 class="text-lg font-semibold">İlan işlemleri</h2>
        <p class="panel-muted mt-2 text-sm">Durdurma ve kapatma bağlantıyı silmez. Arşiv geçmişi korur.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            @if($canWrite)<form method="post" action="{{ route('company.positions.copy', ['position' => $position['id']]) }}">@csrf<button class="company-btn-secondary" type="submit">Pozisyonu kopyala</button></form>@endif
            @if(in_array('positions.delete', $permissions, true) && $currentStatus !== 'archived')<form method="post" action="{{ route('company.positions.delete', ['position' => $position['id']]) }}" onsubmit="return confirm('{{ __('company_positions.actions.archive') }}?')">@csrf @method('DELETE')<button class="company-btn-secondary text-red-600" type="submit">{{ __('company_positions.actions.archive') }}</button></form>@endif
        </div>
    </section>
</div>
