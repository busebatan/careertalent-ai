@php
    $lucideIcon = [
        'dashboard' => 'layout-dashboard',
        'cv' => 'file-text',
        'ladder' => 'chart-no-axes-column-increasing',
        'roadmap' => 'map',
        'learning' => 'book-open',
        'jobs' => 'briefcase-business',
        'job-analysis' => 'search',
        'radar' => 'radar',
        'applications' => 'files',
        'passport' => 'book-user',
        'interview' => 'messages-square',
        'mentors' => 'users-round',
        'cohorts' => 'graduation-cap',
        'settings' => 'settings',
        'tasks' => 'list-checks',
        'chat' => 'message-circle',
        'profile' => 'user-round',
        'admins' => 'users-round',
        'organizations' => 'building-2',
    ][$icon ?? 'dashboard'] ?? 'menu';
@endphp

<i data-lucide="{{ $lucideIcon }}" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
