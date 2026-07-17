@php
    $currentLocale = app()->getLocale();
    $notifications = trans('admin.notifications');
    $notifications = is_array($notifications) ? $notifications : [];
@endphp

<header class="admin-header flex items-center justify-between gap-4 bg-white/80 px-6 py-3 backdrop-blur dark:bg-slate-950/80 md:px-10">
    <div class="flex min-w-0 items-center gap-2">
        <button type="button" @click="sidebarOpen = !sidebarOpen"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 md:hidden"
            :aria-expanded="sidebarOpen"
            aria-controls="admin-sidebar"
            :aria-label="@js(__('admin.nav.open_menu'))">
            <i data-lucide="menu" class="h-5 w-5" aria-hidden="true"></i>
        </button>
        <p class="truncate text-sm font-medium text-slate-700 dark:text-slate-200 md:hidden">{{ __('admin.brand') }}</p>
    </div>

    <div class="ml-auto flex items-center gap-1 sm:gap-2">
        <div class="hidden items-center gap-3 sm:flex"
            x-data="{
                dateLabel: '',
                timeLabel: '',
                locale: @js($currentLocale),
                tick() {
                    const loc = this.locale === 'en' ? 'en-US' : 'tr-TR';
                    const now = new Date();
                    this.dateLabel = now.toLocaleDateString(loc, {
                        weekday: 'short',
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                    });
                    this.timeLabel = now.toLocaleTimeString(loc, {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                    });
                },
                init() {
                    this.tick();
                    setInterval(() => this.tick(), 1000);
                },
            }">
            <div class="flex items-center gap-2 rounded-lg px-2 py-1 text-xs text-slate-500 dark:text-slate-400">
                <span class="inline-block h-2 w-2 rounded-full {{ ($apiHealth['ok'] ?? false) ? 'admin-accent-dot' : 'bg-red-500' }}"></span>
                {{ ($apiHealth['ok'] ?? false) ? __('panel.header.api_connected') : __('panel.header.api_pending') }}
            </div>
            <div class="flex flex-col text-right leading-tight" aria-live="polite">
                <span class="text-[11px] text-slate-500 dark:text-slate-400" x-text="dateLabel"></span>
                <span class="text-xs font-medium tabular-nums text-slate-700 dark:text-slate-200" x-text="timeLabel"></span>
            </div>
        </div>

        <button type="button" @click="toggleTheme()"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            :title="theme === 'dark' ? @js(__('panel.header.theme_light')) : @js(__('panel.header.theme_dark'))"
            :aria-label="theme === 'dark' ? @js(__('panel.header.theme_light')) : @js(__('panel.header.theme_dark'))">
            <i data-lucide="sun" class="hidden h-5 w-5 dark:block" aria-hidden="true"></i>
            <i data-lucide="moon" class="h-5 w-5 dark:hidden" aria-hidden="true"></i>
        </button>

        <div class="flex items-center gap-1 sm:gap-2"
            x-data="{
                langOpen: false,
                notifOpen: false,
                notifications: {{ Js::from($notifications) }},
                unreadCount() {
                    return this.notifications.filter(n => n.unread).length;
                },
                markAllRead() {
                    this.notifications.forEach(n => n.unread = false);
                },
                closeMenus() {
                    this.langOpen = false;
                    this.notifOpen = false;
                }
            }"
            @keydown.escape.window="closeMenus()">
            <div class="relative">
                <button type="button" @click="notifOpen = !notifOpen; langOpen = false"
                    class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    :aria-expanded="notifOpen"
                    aria-haspopup="true"
                    :aria-label="@js(__('panel.header.notifications'))">
                    <i data-lucide="bell" class="h-5 w-5" aria-hidden="true"></i>
                    <span x-show="unreadCount() > 0"
                        class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full admin-accent-pill px-1 text-[10px] font-bold"
                        x-text="unreadCount()"></span>
                </button>
                <div x-show="notifOpen" x-cloak @click.outside="notifOpen = false"
                    class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('panel.header.notifications') }}</span>
                        <button type="button" @click="markAllRead()" class="admin-accent-link">{{ __('panel.header.mark_read') }}</button>
                    </div>
                    <ul class="max-h-72 overflow-y-auto">
                        <template x-for="item in notifications" :key="item.id">
                            <li class="border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                                <p class="flex items-start gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                                    <span x-show="item.unread" class="admin-accent-dot mt-1.5 h-2 w-2 shrink-0 rounded-full"></span>
                                    <span x-text="item.title"></span>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="item.body"></p>
                                <p class="mt-1 text-[10px] text-slate-400" x-text="item.time"></p>
                            </li>
                        </template>
                    </ul>
                    <p x-show="notifications.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">{{ __('panel.header.notifications_empty') }}</p>
                </div>
            </div>

            <div class="relative">
                <button type="button" @click="langOpen = !langOpen; notifOpen = false"
                    class="flex items-center gap-1 rounded-lg px-2 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    :aria-expanded="langOpen"
                    aria-haspopup="listbox"
                    :aria-label="@js(__('panel.header.language'))">
                    <i data-lucide="languages" class="h-5 w-5" aria-hidden="true"></i>
                    <span class="uppercase">{{ $currentLocale }}</span>
                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400" aria-hidden="true"></i>
                </button>
                <div x-show="langOpen" x-cloak @click.outside="langOpen = false"
                    role="listbox"
                    class="absolute right-0 z-50 mt-2 w-40 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <a href="{{ route('admin.locale', 'tr') }}"
                        class="flex items-center justify-between px-4 py-2 text-sm {{ $currentLocale === 'tr' ? 'admin-lang-active' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50' }}"
                        role="option" @if ($currentLocale === 'tr') aria-selected="true" @endif>
                        Türkçe
                        @if ($currentLocale === 'tr')<i data-lucide="check" class="admin-accent-check h-4 w-4" aria-hidden="true"></i>@endif
                    </a>
                    <a href="{{ route('admin.locale', 'en') }}"
                        class="flex items-center justify-between px-4 py-2 text-sm {{ $currentLocale === 'en' ? 'admin-lang-active' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50' }}"
                        role="option" @if ($currentLocale === 'en') aria-selected="true" @endif>
                        English
                        @if ($currentLocale === 'en')<i data-lucide="check" class="admin-accent-check h-4 w-4" aria-hidden="true"></i>@endif
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
