<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('company.dashboard.title')) — CareerTalent Company</title>
    <script>(function(){var t=localStorage.getItem('panel-theme');if(t==='light')document.documentElement.classList.remove('dark');else document.documentElement.classList.add('dark')})();</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="company-shell min-h-screen overflow-x-hidden bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
    x-data="{sidebarOpen:false,theme:localStorage.getItem('panel-theme')||'dark',init(){this.applyTheme()},applyTheme(){document.documentElement.classList.toggle('dark',this.theme==='dark');localStorage.setItem('panel-theme',this.theme)},toggleTheme(){this.theme=this.theme==='dark'?'light':'dark';this.applyTheme()}}">
<div class="panel-shell">
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen=false" class="fixed inset-0 z-30 bg-slate-950/50 md:hidden"></div>
    <aside class="panel-sidebar panel-mobile-sidebar" :class="sidebarOpen?'panel-mobile-sidebar-open':''" aria-label="CareerTalent Company">
        <div class="flex h-full flex-col p-5">
            <a href="{{ route('company.dashboard') }}" class="company-brand mb-5 text-lg font-bold">CareerTalent <span class="text-slate-900 dark:text-white">Company</span></a>
            @if (count($companyMemberships) > 1)
                <div class="mb-5 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('company.organization.active') }}</p>
                    @foreach ($companyMemberships as $membership)
                        <form method="post" action="{{ route('company.organization.switch', $membership['organization_id']) }}">@csrf
                            <button class="company-membership-option w-full rounded-lg px-2 py-1.5 text-left text-sm {{ $membership['organization_id'] === $companyMembership['organization_id'] ? 'company-membership-active' : 'hover:bg-slate-100 dark:hover:bg-slate-800' }}">{{ $membership['organization_name'] }}</button>
                        </form>
                    @endforeach
                </div>
            @else
                <div class="company-context-card mb-5 rounded-xl border p-3"><p class="font-semibold">{{ $companyMembership['organization_name'] }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('company.roles.'.$companyMembership['role']) }}</p></div>
            @endif
            <nav class="space-y-1">
                <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('company.nav.general') }}</p>
                <a class="panel-nav-link {{ request()->routeIs('company.dashboard')?'panel-nav-link-active':'' }}" href="{{ route('company.dashboard') }}">▦ <span>{{ __('company.nav.dashboard') }}</span></a>
                <p class="px-3 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('company.nav.organization') }}</p>
                <a class="panel-nav-link {{ request()->routeIs('company.team*')?'panel-nav-link-active':'' }}" href="{{ route('company.team') }}">♙ <span>{{ __('company.nav.team') }}</span></a>
                <a class="panel-nav-link {{ request()->routeIs('company.profile*')?'panel-nav-link-active':'' }}" href="{{ route('company.profile') }}">⚙ <span>{{ __('company.nav.profile') }}</span></a>
            </nav>
            <div class="mt-auto border-t border-slate-200 pt-4 dark:border-slate-800">
                <div class="mb-3 px-3"><p class="truncate text-sm font-semibold">{{ $companyUser['full_name'] }}</p><p class="truncate text-xs text-slate-500">{{ $companyUser['email'] }}</p></div>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm text-red-500">{{ __('panel.nav.logout') }}</button></form>
            </div>
        </div>
    </aside>
    <div class="panel-main">
        <header class="panel-header"><button class="company-icon-button rounded-lg p-2 md:hidden" @click="sidebarOpen=true" aria-label="Menüyü aç">☰</button><div class="ml-auto flex items-center gap-3"><span class="company-accent-text text-xs font-medium">{{ __('company.header.secure_context') }}</span><div class="flex rounded-lg border border-slate-200 p-0.5 text-xs dark:border-slate-700"><a href="{{ route('company.locale', 'tr') }}" class="company-lang-link rounded px-2 py-1 {{ app()->getLocale() === 'tr' ? 'company-lang-active' : '' }}">TR</a><a href="{{ route('company.locale', 'en') }}" class="company-lang-link rounded px-2 py-1 {{ app()->getLocale() === 'en' ? 'company-lang-active' : '' }}">EN</a></div><button @click="toggleTheme()" class="company-icon-button rounded-lg p-2" aria-label="Tema">◐</button></div></header>
        <main class="flex-1 p-6 md:p-10">
            @if (session('status'))<div class="company-feedback-success mb-5 rounded-xl border p-4 text-sm">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="mb-5 rounded-xl bg-red-500/10 p-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
            @yield('content')
        </main>
    </div>
</div>
@livewireScripts
</body></html>
