@php
    $variant = match ($stage) {
        'new' => 'neutral',
        'assessment_pending', 'assessment_in_progress' => 'warning',
        'technical_review' => 'info',
        'shortlisted', 'interview', 'offer' => 'success',
        'hired' => 'positive',
        'rejected', 'withdrawn' => 'danger',
        default => 'neutral',
    };
@endphp
<span class="company-status-badge company-status-badge--{{ $variant }}">{{ $label ?? __('company.applications.stage_'.$stage) }}</span>
