@php
    $currentLocale = app()->getLocale();
    $notifications = __('panel.notifications');
@endphp

<header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white/80 px-6 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80 md:px-10">

    <p class="truncate text-sm font-medium text-slate-600 dark:text-slate-400 md:hidden">{{ __('panel.brand') }}</p>

    <div class="ml-auto flex items-center gap-1 sm:gap-2">
        {{-- API durumu + tarih/saat --}}
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
                <span class="inline-block h-2 w-2 rounded-full {{ ($apiHealth['ok'] ?? false) ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                {{ ($apiHealth['ok'] ?? false) ? __('panel.header.api_connected') : __('panel.header.api_pending') }}
            </div>
            <div class="flex flex-col text-right leading-tight" aria-live="polite">
                <span class="text-[11px] text-slate-500 dark:text-slate-400" x-text="dateLabel"></span>
                <span class="text-xs font-medium tabular-nums text-slate-700 dark:text-slate-200" x-text="timeLabel"></span>
            </div>
        </div>

        {{-- Tema: body x-data scope (iç içe x-data yok) --}}
        <button type="button" @click="toggleTheme()"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            :title="theme === 'dark' ? @js(__('panel.header.theme_light')) : @js(__('panel.header.theme_dark'))"
            :aria-label="theme === 'dark' ? @js(__('panel.header.theme_light')) : @js(__('panel.header.theme_dark'))">
            {{-- Koyu modda: güneş (açık temaya geç) --}}
            <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            {{-- Açık modda: ay (koyu temaya geç) --}}
            <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
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

            {{-- Bildirimler --}}
            <div class="relative">
                <button type="button" @click="notifOpen = !notifOpen; langOpen = false"
                    class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    :aria-expanded="notifOpen"
                    aria-haspopup="true"
                    :aria-label="@js(__('panel.header.notifications'))">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span x-show="unreadCount() > 0"
                        class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold text-white"
                        x-text="unreadCount()"></span>
                </button>
                <div x-show="notifOpen" x-cloak @click.outside="notifOpen = false"
                    class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('panel.header.notifications') }}</span>
                        <button type="button" @click="markAllRead()" class="text-xs text-emerald-600 hover:underline dark:text-emerald-400">{{ __('panel.header.mark_read') }}</button>
                    </div>
                    <ul class="max-h-72 overflow-y-auto">
                        <template x-for="item in notifications" :key="item.id">
                            <li class="border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                                <p class="flex items-start gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                                    <span x-show="item.unread" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
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

            {{-- Dil --}}
            <div class="relative">
                <button type="button" @click="langOpen = !langOpen; notifOpen = false"
                    class="flex items-center gap-1 rounded-lg px-2 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    :aria-expanded="langOpen"
                    aria-haspopup="listbox"
                    :aria-label="@js(__('panel.header.language'))">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
                    </svg>
                    <span class="uppercase">{{ $currentLocale }}</span>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="langOpen" x-cloak @click.outside="langOpen = false"
                    role="listbox"
                    class="absolute right-0 z-50 mt-2 w-40 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <a href="{{ route('panel.locale', 'tr') }}"
                        class="flex items-center justify-between px-4 py-2 text-sm {{ $currentLocale === 'tr' ? 'bg-slate-100 font-semibold text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50' }}"
                        role="option" @if ($currentLocale === 'tr') aria-selected="true" @endif>
                        Türkçe
                        @if ($currentLocale === 'tr')<span class="text-emerald-500">✓</span>@endif
                    </a>
                    <a href="{{ route('panel.locale', 'en') }}"
                        class="flex items-center justify-between px-4 py-2 text-sm {{ $currentLocale === 'en' ? 'bg-slate-100 font-semibold text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50' }}"
                        role="option" @if ($currentLocale === 'en') aria-selected="true" @endif>
                        English
                        @if ($currentLocale === 'en')<span class="text-emerald-500">✓</span>@endif
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
