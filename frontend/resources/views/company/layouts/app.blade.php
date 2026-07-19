@php
    $companyName = $companyUser['full_name'] ?? __('company.brand');
    $companyEmail = $companyUser['email'] ?? '';
    $companyNameParts = preg_split('/\s+/u', trim($companyName)) ?: [];
    $companyInitials = mb_strtoupper(
        mb_substr($companyNameParts[0] ?? '', 0, 1).mb_substr($companyNameParts[count($companyNameParts) - 1] ?? '', 0, 1)
    );
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('company.dashboard.title')) — {{ __('company.brand') }}</title>
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
<body class="company-shell min-h-screen overflow-x-hidden bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100" data-workspace-shell="company"
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
        <aside id="company-sidebar" class="panel-sidebar company-sidebar"
            :class="sidebarOpen ? 'company-sidebar-open' : ''"
            aria-label="{{ __('company.brand') }}">
            <div class="flex h-full min-h-0 flex-col p-6">
                <a href="{{ route('company.dashboard') }}" class="company-brand mb-8 block shrink-0 whitespace-nowrap text-[17px] font-bold leading-7 tracking-[-0.03em]">{{ __('company.brand') }}</a>
                <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain text-sm">
                    @include('workspace.partials.sidebar-nav', ['workspaceNav' => $companyNav])
                </nav>
                <div class="company-sidebar-footer mt-auto pt-4">
                    <div class="mb-4 space-y-1 border-t border-slate-200 pt-4 dark:border-slate-800">
                        @if (count($companyMemberships) > 1)
                            <details class="group">
                                <summary class="panel-nav-link cursor-pointer list-none">
                                    <i data-lucide="building-2" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                                    <span class="min-w-0 flex-1 truncate">{{ $companyMembership['organization_name'] }}</span>
                                    <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                                </summary>
                                <div class="mt-1 space-y-1 pl-2">
                                    @foreach ($companyMemberships as $membership)
                                        <form method="post" action="{{ route('company.organization.switch', ['organization' => $membership['organization_id']]) }}">
                                            @csrf
                                            <button type="submit" class="company-membership-option w-full rounded-lg px-3 py-2 text-left text-xs {{ $membership['organization_id'] === $companyMembership['organization_id'] ? 'company-membership-active' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                                                {{ $membership['organization_name'] }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </details>
                        @else
                            <div class="panel-nav-link" title="{{ __('company.organization.active') }}">
                                <i data-lucide="building-2" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                                <span class="truncate">{{ $companyMembership['organization_name'] }}</span>
                            </div>
                        @endif
                        <a href="{{ route('home') }}" class="panel-nav-link">
                            <i data-lucide="house" class="h-4 w-4 shrink-0" aria-hidden="true"></i>
                            <span>{{ __('company.nav.marketing_site') }}</span>
                        </a>
                    </div>
                    @if (in_array('organization.update', $companyMembership['permissions'], true))
                        <a data-company-profile href="{{ route('company.profile') }}"
                            class="mb-3 flex items-center gap-3 rounded-xl border border-slate-200 p-2.5 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-slate-800 dark:hover:bg-slate-800/60 {{ request()->routeIs('company.profile') ? 'bg-slate-100 dark:bg-slate-800/60' : '' }}">
                    @else
                        <div class="mb-3 flex items-center gap-3 rounded-xl border border-slate-200 p-2.5 dark:border-slate-800">
                    @endif
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white ring-2 ring-emerald-500/25 dark:bg-emerald-500">{{ $companyInitials }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $companyName }}</span>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $companyEmail }}</span>
                        </span>
                    @if (in_array('organization.update', $companyMembership['permissions'], true))
                        </a>
                    @else
                        </div>
                    @endif
                    <form data-company-logout method="post" action="{{ route('company.logout') }}" class="border-t border-slate-200 pt-3 dark:border-slate-800">
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
            @include('workspace.partials.header', [
                'workspaceApiHealth' => $apiHealth,
                'workspaceBrand' => __('company.brand'),
                'workspaceLocaleRoute' => 'company.locale',
                'workspaceMenuLabel' => __('company.nav.open_menu'),
                'workspaceNotifications' => [],
                'workspaceSidebarId' => 'company-sidebar',
            ])
            <main class="flex-1 p-6 md:p-10">
                @if (session('status'))
                    <div class="company-feedback-success mb-5 rounded-xl border p-4 text-sm">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 rounded-xl bg-red-500/10 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
