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
        theme: localStorage.getItem('panel-theme') || 'dark',
        init() { this.applyTheme(); },
        applyTheme() {
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            localStorage.setItem('panel-theme', this.theme);
        },
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            this.applyTheme();
        }
    }">
    <div class="panel-shell">
        <aside class="panel-sidebar" aria-label="{{ __('panel.brand') }}">
            <div class="flex h-full min-h-0 flex-col p-6">
                <a href="{{ route('home') }}" class="mb-8 block shrink-0 text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ __('panel.brand') }}</a>
                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain text-sm">
                    @include('app.partials.sidebar-nav')
                </nav>
                <div class="mt-auto shrink-0 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <a href="{{ route('home') }}"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        {{ __('panel.nav.logout') }}
                    </a>
                </div>
            </div>
        </aside>

        <div class="panel-main">
            @include('app.partials.panel-header')
            <main class="flex-1 p-6 md:p-10">
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
