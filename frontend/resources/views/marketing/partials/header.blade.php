@php
    $locale = app()->getLocale();
    $infoActive = request()->routeIs('faq', 'blog', 'about');
@endphp
<header class="sticky top-0 z-50 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur-md">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
        <a href="{{ route('home') }}" class="shrink-0 text-lg font-bold tracking-tight text-emerald-400 sm:text-xl">
            {{ __('marketing.brand') }}
        </a>

        <nav class="hidden items-center gap-5 text-sm text-slate-300 md:flex" aria-label="{{ __('marketing.brand') }}">
            <a href="{{ route('home') }}" class="transition hover:text-white @if(request()->routeIs('home')) text-white @endif">
                {{ __('marketing.nav.home') }}
            </a>
            <a href="{{ route('features') }}" class="transition hover:text-white @if(request()->routeIs('features')) text-white @endif">
                {{ __('marketing.nav.features') }}
            </a>
            <a href="{{ route('careers') }}" class="transition hover:text-white @if(request()->routeIs('careers')) text-white @endif">
                {{ __('marketing.nav.careers') }}
            </a>
            <a href="{{ route('pricing') }}" class="transition hover:text-white @if(request()->routeIs('pricing')) text-white @endif">
                {{ __('marketing.nav.pricing') }}
            </a>
            <a href="{{ route('gallery') }}" class="transition hover:text-white @if(request()->routeIs('gallery')) text-white @endif">
                {{ __('marketing.nav.gallery') }}
            </a>

            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-1 transition hover:text-white @if($infoActive) text-white @endif [&::-webkit-details-marker]:hidden">
                    {{ __('marketing.nav.info') }}
                    <svg class="h-3.5 w-3.5 opacity-60 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="absolute left-0 top-full z-50 mt-2 min-w-[11rem] overflow-hidden rounded-xl border border-slate-700 bg-slate-900 py-1 shadow-xl shadow-black/40">
                    <a href="{{ route('faq') }}" class="block px-4 py-2.5 transition hover:bg-slate-800 hover:text-white @if(request()->routeIs('faq')) bg-slate-800/60 text-white @endif">
                        {{ __('marketing.nav.faq') }}
                    </a>
                    <a href="{{ route('blog') }}" class="block px-4 py-2.5 transition hover:bg-slate-800 hover:text-white @if(request()->routeIs('blog')) bg-slate-800/60 text-white @endif">
                        {{ __('marketing.nav.blog') }}
                    </a>
                    <a href="{{ route('about') }}" class="block px-4 py-2.5 transition hover:bg-slate-800 hover:text-white @if(request()->routeIs('about')) bg-slate-800/60 text-white @endif">
                        {{ __('marketing.nav.about') }}
                    </a>
                </div>
            </details>

            <a href="{{ route('contact') }}" class="transition hover:text-white @if(request()->routeIs('contact')) text-white @endif">
                {{ __('marketing.nav.contact') }}
            </a>
        </nav>

        <div class="flex items-center gap-2 sm:gap-3">
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs font-medium text-slate-300 transition hover:border-slate-600 hover:text-white [&::-webkit-details-marker]:hidden" aria-label="{{ __('marketing.nav.language') }}">
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.6 9h16.8M3.6 15h16.8M12 3c-2.5 3-2.5 15 0 18M12 3c2.5 3 2.5 15 0 18" />
                    </svg>
                    <span class="uppercase text-emerald-400">{{ $locale }}</span>
                    <svg class="h-3 w-3 opacity-50 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                    </svg>
                </summary>
                <div class="absolute right-0 top-full z-50 mt-2 min-w-[8rem] overflow-hidden rounded-xl border border-slate-700 bg-slate-900 py-1 shadow-xl shadow-black/40">
                    <a href="{{ route('marketing.locale', 'tr') }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs transition hover:bg-slate-800 @if($locale === 'tr') bg-slate-800/60 text-emerald-400 @else text-slate-300 @endif">
                        <span>Türkçe</span>
                        <span class="font-semibold uppercase">TR</span>
                    </a>
                    <a href="{{ route('marketing.locale', 'en') }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs transition hover:bg-slate-800 @if($locale === 'en') bg-slate-800/60 text-emerald-400 @else text-slate-300 @endif">
                        <span>English</span>
                        <span class="font-semibold uppercase">EN</span>
                    </a>
                </div>
            </details>

            <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm text-slate-300 transition hover:text-white sm:inline-block">
                {{ __('marketing.nav.login') }}
            </a>
            <a href="{{ route('register') }}" class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 sm:px-4">
                {{ __('marketing.nav.register') }}
            </a>
        </div>
    </div>
</header>
