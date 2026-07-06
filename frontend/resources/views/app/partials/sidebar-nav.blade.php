@php
    $navItems = [
        ['route' => 'panel.dashboard', 'match' => 'panel.dashboard', 'label' => 'panel.nav.dashboard', 'icon' => 'dashboard'],
        ['route' => 'panel.cv-builder', 'match' => 'panel.cv-builder', 'label' => 'panel.nav.cv_builder', 'icon' => 'cv'],
        ['route' => 'panel.career-ladder', 'match' => 'panel.career-ladder', 'label' => 'panel.nav.career_ladder', 'icon' => 'ladder'],
        ['route' => 'panel.roadmap', 'match' => 'panel.roadmap', 'label' => 'panel.nav.roadmap', 'icon' => 'roadmap'],
        ['route' => 'panel.job-matches', 'match' => 'panel.job-matches', 'label' => 'panel.nav.job_matches', 'icon' => 'jobs'],
        ['route' => 'panel.job-radar', 'match' => 'panel.job-radar', 'label' => 'panel.nav.job_radar', 'icon' => 'radar'],
        ['route' => 'panel.applications', 'match' => 'panel.applications', 'label' => 'panel.nav.applications', 'icon' => 'applications'],
        ['route' => 'panel.skill-passport', 'match' => 'panel.skill-passport', 'label' => 'panel.nav.skill_passport', 'icon' => 'passport'],
        ['route' => 'panel.interview', 'match' => 'panel.interview', 'label' => 'panel.nav.interview', 'icon' => 'interview'],
        ['route' => 'panel.mentors', 'match' => 'panel.mentors', 'label' => 'panel.nav.mentors', 'icon' => 'mentors'],
        ['route' => 'panel.learning', 'match' => 'panel.learning', 'label' => 'panel.nav.learning', 'icon' => 'learning'],
        ['route' => 'panel.tasks', 'match' => 'panel.tasks', 'label' => 'panel.nav.tasks', 'icon' => 'tasks'],
        ['route' => 'panel.chat', 'match' => 'panel.chat', 'label' => 'panel.nav.chat', 'icon' => 'chat'],
        ['route' => 'panel.profile', 'match' => 'panel.profile', 'label' => 'panel.nav.profile', 'icon' => 'profile'],
    ];
@endphp

@foreach ($navItems as $item)
    <a href="{{ route($item['route']) }}"
        class="panel-nav-link {{ request()->routeIs($item['match']) ? 'panel-nav-link-active' : '' }}">
        @include('app.partials.sidebar-nav-icon', ['icon' => $item['icon']])
        <span class="truncate">{{ __($item['label']) }}</span>
    </a>
@endforeach
