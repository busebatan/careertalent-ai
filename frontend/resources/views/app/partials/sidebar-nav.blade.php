@php
    $navGroups = [
        ['label' => null, 'items' => [
            ['route' => 'panel.dashboard', 'match' => 'panel.dashboard', 'label' => 'panel.nav.dashboard', 'icon' => 'dashboard'],
            ['route' => 'panel.chat', 'match' => 'panel.chat', 'label' => 'panel.nav.chat', 'icon' => 'chat'],
        ]],
        ['label' => 'panel.nav_groups.career', 'items' => [
            ['route' => 'panel.cv-builder', 'match' => 'panel.cv-builder', 'label' => 'panel.nav.cv_builder', 'icon' => 'cv'],
            ['route' => 'panel.roadmap', 'match' => 'panel.roadmap', 'label' => 'panel.nav.roadmap', 'icon' => 'roadmap'],
            ['route' => 'panel.tasks', 'match' => 'panel.tasks*', 'label' => 'panel.nav.tasks', 'icon' => 'tasks'],
            ['route' => 'panel.skill-passport', 'match' => 'panel.skill-passport', 'label' => 'panel.nav.skill_passport', 'icon' => 'passport'],
        ]],
        ['label' => 'panel.nav_groups.opportunities', 'items' => [
            ['route' => 'panel.job-matches', 'match' => 'panel.job-matches', 'label' => 'panel.nav.job_matches', 'icon' => 'jobs'],
            ['route' => 'panel.applications', 'match' => 'panel.applications', 'label' => 'panel.nav.applications', 'icon' => 'applications'],
        ]],
        ['label' => 'panel.nav_groups.support', 'items' => [
            ['route' => 'panel.interview', 'match' => 'panel.interview', 'label' => 'panel.nav.interview', 'icon' => 'interview'],
            ['route' => 'panel.mentors', 'match' => 'panel.mentors', 'label' => 'panel.nav.mentors', 'icon' => 'mentors'],
        ]],
        ['label' => 'panel.nav_groups.account', 'items' => [
            ['route' => 'panel.account', 'match' => 'panel.account', 'label' => 'panel.nav.account', 'icon' => 'profile'],
        ]],
    ];
@endphp

@foreach ($navGroups as $group)
    @if ($group['label'])
        <p class="mb-1 mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ __($group['label']) }}
        </p>
    @endif
    @foreach ($group['items'] as $item)
        <a href="{{ route($item['route']) }}" @click="sidebarOpen = false"
            class="panel-nav-link {{ request()->routeIs($item['match']) ? 'panel-nav-link-active' : '' }}">
            @include('app.partials.sidebar-nav-icon', ['icon' => $item['icon']])
            <span class="truncate">{{ __($item['label']) }}</span>
        </a>
    @endforeach
@endforeach
