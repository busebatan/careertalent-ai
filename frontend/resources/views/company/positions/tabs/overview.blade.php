@php($activities = array_slice($positionDetail['activities'] ?? [], 0, 5))
<div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
    <div class="space-y-6">
        <section class="panel-card p-6">
            <h2 class="text-lg font-semibold">{{ __('company_positions.fields.responsibilities') }}</h2>
            <p class="panel-muted mt-4 whitespace-pre-line leading-7">{{ $position['responsibilities'] ?? '—' }}</p>
        </section>
        <section class="panel-card p-6">
            <h2 class="text-lg font-semibold">{{ __('company_positions.sections.process') }}</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                @foreach(['application_count' => 'applications', 'assessment_completed_count' => 'assessments', 'shortlisted_count' => 'shortlisted'] as $key => $label)
                    <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900"><dt class="panel-muted text-xs font-semibold uppercase tracking-wide">{{ __('company_positions.metrics.'.$label) }}</dt><dd class="mt-2 text-2xl font-bold">{{ $position[$key] ?? 0 }}</dd></div>
                @endforeach
            </dl>
        </section>
    </div>
    <div class="space-y-6">
        <section class="panel-card p-6">
            <h2 class="text-lg font-semibold">{{ __('company_positions.fields.status') }}</h2>
            <dl class="mt-5 space-y-4 text-sm">
                <div class="flex justify-between gap-4"><dt class="panel-muted">{{ __('company_positions.fields.recruiter') }}</dt><dd class="text-right font-semibold">{{ $position['recruiter_name'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="panel-muted">{{ __('company_positions.fields.technical_manager') }}</dt><dd class="text-right font-semibold">{{ $position['technical_manager_name'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="panel-muted">{{ __('company_positions.fields.application_deadline') }}</dt><dd class="text-right font-semibold">{{ !empty($position['application_deadline']) ? \Carbon\Carbon::parse($position['application_deadline'])->format('d.m.Y') : '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="panel-muted">{{ __('company_positions.fields.retention_days') }}</dt><dd class="text-right font-semibold">{{ $position['retention_days'] ?? 180 }} gün</dd></div>
            </dl>
        </section>
        <section class="panel-card p-6">
            <h2 class="text-lg font-semibold">{{ __('company_positions.sections.recent') }}</h2>
            @if($activities === [])<p class="panel-muted mt-4 text-sm">—</p>@else<div class="mt-4 space-y-4">@foreach($activities as $activity)<div class="border-l-2 border-emerald-500/30 pl-4"><p class="text-sm font-medium">{{ str_replace(['.', '_'], ' ', $activity['event_type'] ?? '—') }}</p><p class="panel-muted mt-1 text-xs">{{ $activity['actor_name'] ?? 'Sistem' }} · {{ !empty($activity['occurred_at']) ? \Carbon\Carbon::parse($activity['occurred_at'])->format('d.m.Y H:i') : '' }}</p></div>@endforeach</div>@endif
        </section>
    </div>
</div>
