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
<body class="admin-shell min-h-screen overflow-x-hidden bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
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
        <aside class="panel-sidebar" aria-label="{{ __('admin.brand') }}">
            <div class="flex h-full min-h-0 flex-col p-6">
                <a href="{{ route('admin.dashboard') }}" class="admin-brand mb-8 block shrink-0 text-lg font-bold">{{ __('admin.brand') }}</a>
                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain text-sm">
                    @include('admin.partials.sidebar-nav')
                </nav>
                <div class="admin-sidebar-footer mt-auto space-y-2 pt-4">
                    <a href="{{ route('panel.dashboard') }}" class="panel-nav-link">↗ <span>{{ __('admin.nav.student_panel') }}</span></a>
                    <a href="{{ route('home') }}" class="panel-nav-link">⌂ <span>{{ __('admin.nav.marketing_site') }}</span></a>
                </div>
            </div>
        </aside>

        <div class="panel-main">
            @include('admin.partials.admin-header')
            <main class="flex-1 p-6 md:p-10">
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
