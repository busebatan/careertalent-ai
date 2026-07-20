@php
    $variant = match ($status) {
        'assigned' => 'info',
        'in_progress' => 'warning',
        'completed' => 'success',
        'expired', 'cancelled' => 'danger',
        default => 'neutral',
    };
@endphp
<span class="company-status-badge company-status-badge--{{ $variant }}">{{ $label ?? __('company.assessments.status_'.$status) }}</span>
