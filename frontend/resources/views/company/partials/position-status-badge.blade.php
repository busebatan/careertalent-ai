@php
    $variant = match ($status) {
        'draft' => 'neutral',
        'published' => 'success',
        'paused' => 'warning',
        'closed' => 'danger',
        'archived' => 'archived',
        default => 'neutral',
    };
@endphp
<span class="company-status-badge company-status-badge--{{ $variant }}">{{ $label ?? __('company_positions.status.'.$status) }}</span>
