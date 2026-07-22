<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('panel.nav.dashboard')) — {{ __('panel.brand') }}</title>
    <script>
        (function () {
            var t = localStorage.getItem('panel-theme');
            if (t === 'light') document.documentElement.classList.remove('dark');
            else document.documentElement.classList.add('dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    @livewireStyles
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
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
        <aside id="panel-sidebar" class="panel-sidebar panel-mobile-sidebar"
            :class="sidebarOpen ? 'panel-mobile-sidebar-open' : ''"
            aria-label="{{ __('panel.brand') }}">
            <div class="flex h-full min-h-0 flex-col p-6">
                <a href="{{ route('panel.dashboard') }}" class="mb-8 block shrink-0 text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ __('panel.brand') }}</a>
                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain text-sm">
                    @include('app.partials.sidebar-nav')
                </nav>
                <div class="mt-auto shrink-0">
                    @include('app.partials.sidebar-user')
                    @if (session('auth.user.is_admin') === true)
                        <a data-admin-return href="{{ route('admin.dashboard') }}" @click="sidebarOpen = false"
                            class="mb-3 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-emerald-600 transition hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-950/30">
                            <i data-lucide="shield" class="h-4 w-4" aria-hidden="true"></i>
                            {{ __('panel.nav.return_to_admin') }}
                        </a>
                    @endif
                    <div class="border-t border-slate-200 pt-4 dark:border-slate-800">
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30">
                                <i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>
                                {{ __('panel.nav.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <div class="panel-main">
            @include('app.partials.panel-header')
            <main class="flex-1 p-6 md:p-10 @yield('main_class')">
                @include('partials.flash-status')
                @if (session('panel_error'))
                    <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-700 dark:text-red-300" role="alert">
                        {{ session('panel_error') }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
