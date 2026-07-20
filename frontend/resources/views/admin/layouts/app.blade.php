@php
    $adminName = $adminUser['full_name'] ?? __('admin.brand');
    $adminEmail = $adminUser['email'] ?? '';
    $adminNameParts = preg_split('/\s+/u', trim($adminName)) ?: [];
    $adminInitials = mb_strtoupper(
        mb_substr($adminNameParts[0] ?? '', 0, 1).mb_substr($adminNameParts[count($adminNameParts) - 1] ?? '', 0, 1)
    );
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.nav.dashboard')) — {{ __('admin.brand') }}</title>
    <script>
        (function () {
            var t = localStorage.getItem('panel-theme');
            if (t === 'light') document.documentElement.classList.remove('dark');
            else document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="admin-shell min-h-screen overflow-x-hidden bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100" data-workspace-shell="admin"
    x-data="{
        sidebarOpen: false,
        theme: localStorage.getItem('panel-theme') || 'dark',
        init() { this.applyTheme(); },
        applyTheme() {
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            localStorage.setItem('panel-theme', this.theme);
        },
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            this.applyTheme();
        },
        closeSidebar() {
            this.sidebarOpen = false;
        }
    }"
    @keydown.escape.window="closeSidebar()">
    <div class="panel-shell">
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-slate-950/50 backdrop-blur-sm md:hidden" aria-hidden="true"></div>
        <aside id="admin-sidebar" class="panel-sidebar admin-sidebar"
            :class="sidebarOpen ? 'admin-sidebar-open' : ''"
            aria-label="{{ __('admin.brand') }}">
            <div class="flex h-full min-h-0 flex-col p-6">
                <a href="{{ route('admin.dashboard') }}" class="admin-brand mb-8 block shrink-0 text-lg font-bold">{{ __('admin.brand') }}</a>
                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain text-sm">
                    @include('admin.partials.sidebar-nav')
                </nav>
                <div class="admin-sidebar-footer mt-auto pt-4">
                    <div class="mb-4 space-y-1 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <a href="{{ route('panel.dashboard') }}" class="panel-nav-link">↗ <span>{{ __('admin.nav.student_panel') }}</span></a>
                        <a href="{{ route('home') }}" class="panel-nav-link">⌂ <span>{{ __('admin.nav.marketing_site') }}</span></a>
                    </div>
                    <a data-admin-profile href="{{ route('admin.profile') }}"
                        class="mb-3 flex items-center gap-3 rounded-xl border border-slate-200 p-2.5 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-slate-800 dark:hover:bg-slate-800/60 {{ request()->routeIs('admin.profile') ? 'bg-slate-100 dark:bg-slate-800/60' : '' }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white ring-2 ring-emerald-500/25 dark:bg-emerald-500">{{ $adminInitials }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $adminName }}</span>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $adminEmail }}</span>
                        </span>
                    </a>
                    <form data-admin-logout method="post" action="{{ route('logout') }}" class="border-t border-slate-200 pt-3 dark:border-slate-800">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 dark:text-red-400 dark:hover:bg-red-950/30">
                            <i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>
                            {{ __('panel.nav.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="panel-main">
            @include('admin.partials.admin-header')
            <main class="flex-1 p-6 md:p-10">
                @include('partials.flash-status')
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
