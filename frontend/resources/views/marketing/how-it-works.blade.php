@extends('marketing.layouts.marketing')

@section('title', __('how_it_works.title'))

@section('content')

{{--
    How It Works — CareerTalent AI
    Extends the marketing layout. Copy comes from lang/en/how_it_works.php and
    lang/tr/how_it_works.php via __() — matching the convention used across
    the marketing site. To edit copy for either language, edit those two
    files, not this one.

    Structure:
      1. Overview      — headline + framed video placeholder
      2. Stats         — quick credibility numbers, count up on scroll
      3. Process       — sticky "career thread" rail + three scrolling steps,
                          each with an expandable "what you get" detail
      4. Live demo     — pick a target role, see a sample match score,
                          matched skills, and gaps animate in
      5. FAQ           — expandable questions (native <details>, no JS)
      6. Closing CTA   — plus a sticky mobile-friendly CTA bar that appears
                          once you've scrolled past the hero and hides again
                          once the closing CTA is in view

    Every array pulled from lang files falls back to [] (see $trArray) and
    every nested list access below uses `?? []` too — a missing sub-key in
    one locale degrades to an empty section instead of a 500 error.

    To drop in real video: each spot marked "VIDEO SLOT" currently renders a
    placeholder button + screenshot. Swap the <img> + button block for a
    <video> or <iframe> embed — the toast/JS wiring can just be deleted once
    real video exists.
--}}

@php
    // is_array() guard: if lang/{locale}/how_it_works.php isn't found, Laravel's
    // __() falls back to returning the key itself as a string. Without this guard,
    // $hero['eyebrow'] on a string throws "Cannot access offset of type string on
    // string" instead of telling you the real problem (the lang file is missing).
    $trArray = function (string $key): array {
        $value = __($key);
        return is_array($value) ? $value : [];
    };

    $hero      = $trArray('how_it_works.hero');
    $process   = $trArray('how_it_works.process');
    $steps     = $trArray('how_it_works.steps');
    $video     = $trArray('how_it_works.video');
    $stats     = $trArray('how_it_works.stats');
    $demo      = $trArray('how_it_works.demo');
    $faq       = $trArray('how_it_works.faq');
    $stickyCta = $trArray('how_it_works.sticky_cta');
    $closing   = $trArray('how_it_works.closing');
@endphp

<style>
    .hiw-serif { font-family: var(--marketing-display); }
    .hiw-mono { font-family: var(--marketing-mono); }

    @keyframes hiw-fade-up {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .hiw-reveal { opacity: 0; }
    .hiw-reveal.is-visible { animation: hiw-fade-up .7s ease forwards; }

    @keyframes hiw-pulse-ring {
        0% { box-shadow: 0 0 0 0 rgba(0, 201, 141, .45); }
        70% { box-shadow: 0 0 0 26px rgba(0, 201, 141, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 201, 141, 0); }
    }
    .hiw-pulse { animation: hiw-pulse-ring 2.6s infinite; }

    .hiw-focus:focus-visible { outline: none; box-shadow: 0 0 0 2px var(--marketing-ink), 0 0 0 4px var(--marketing-green); }

    /* Career thread rail */
    .hiw-thread-fill { transition: height .5s cubic-bezier(.22,.61,.36,1); }
    .hiw-thread-node, .hiw-rail-label, .hiw-rail-eyebrow {
        transition: color .3s ease, border-color .3s ease, background-color .3s ease, box-shadow .3s ease;
    }
    .hiw-rail-node-btn[aria-current="step"] .hiw-thread-node {
        border-color: var(--marketing-green);
        background: rgba(0, 201, 141, .16);
        box-shadow: 0 0 0 4px rgba(0, 201, 141, .12);
        color: var(--marketing-green);
    }
    .hiw-rail-node-btn[aria-current="step"] .hiw-rail-label { color: #fff; }

    /* Video placeholder affordances */
    .hiw-play-pill:active { transform: scale(.97); }
    .hiw-video-toast { transition: opacity .25s ease; }
    .hiw-video-toast.is-visible { opacity: 1; }

    .hiw-glow-card { transition: border-color .3s ease, transform .3s ease, box-shadow .3s ease; }

    /* Expandable details (step benefits + FAQ) — native <details>, styled */
    details.hiw-details > summary { list-style: none; cursor: pointer; }
    details.hiw-details > summary::-webkit-details-marker { display: none; }
    details.hiw-details > summary .hiw-chevron { transition: transform .3s ease; }
    details.hiw-details[open] > summary .hiw-chevron { transform: rotate(180deg); }
    details.hiw-details[open] > summary { color: #fff; }

    /* Role chips in the live demo */
    .hiw-chip-btn { transition: all .25s ease; }
    .hiw-chip-btn[aria-pressed="true"] {
        border-color: var(--marketing-green);
        background: rgba(0, 201, 141, .14);
        color: #fff;
    }

    /* Match score bar */
    .hiw-bar-fill { transition: width 1s cubic-bezier(.22,.61,.36,1); }
    .hiw-demo-list-item { animation: hiw-fade-up .4s ease forwards; }

    /* Sticky CTA bar */
    .hiw-sticky-bar {
        transform: translateY(120%);
        transition: transform .4s cubic-bezier(.22,.61,.36,1);
    }
    .hiw-sticky-bar.is-visible { transform: translateY(0); }

    @media (prefers-reduced-motion: reduce) {
        .hiw-reveal, .hiw-pulse, .hiw-thread-fill, .hiw-video-toast,
        .hiw-bar-fill, .hiw-sticky-bar, .hiw-chevron, .hiw-demo-list-item {
            animation: none !important;
            transition: none !important;
            opacity: 1 !important;
        }
    }
</style>

<div class="relative bg-[#0a0a0a]">

    {{-- 1. OVERVIEW --}}
    <section id="overview" class="relative overflow-hidden px-6 pt-28 pb-20 lg:px-8">
        <div class="pointer-events-none absolute left-1/2 top-0 h-[420px] w-[720px] -translate-x-1/2 rounded-full blur-[120px]" style="background: rgba(0,201,141,.10);"></div>

        <div class="hiw-reveal relative mx-auto max-w-2xl text-center">
            <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/10 px-3 py-1">
                <span class="h-1.5 w-1.5 rounded-full" style="background: var(--marketing-green);"></span>
                <span class="hiw-mono text-[10px] uppercase tracking-[0.2em] text-white/50">{{ $hero['eyebrow'] ?? '' }}</span>
            </div>

            <h1 class="hiw-serif text-4xl font-semibold leading-[1.15] text-white sm:text-5xl">{{ $hero['title'] ?? '' }}</h1>
            <p class="mx-auto mt-5 max-w-lg text-base leading-relaxed text-white/50">{{ $hero['subtitle'] ?? '' }}</p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3" data-hero-ctas>
                <a href="{{ route('register') }}"
                   class="hiw-focus rounded-full px-6 py-3 text-sm font-semibold transition-all duration-300 hover:-translate-y-0.5"
                   style="background: var(--marketing-green); color: var(--marketing-ink);">
                    {{ $hero['cta_primary'] ?? '' }}
                </a>
                <a href="#overview-video"
                   class="hiw-focus rounded-full border border-white/12 px-6 py-3 text-sm font-medium text-white transition-all duration-300 hover:border-white/25 hover:bg-white/[.03]">
                    {{ $hero['cta_secondary'] ?? '' }}
                </a>
            </div>
        </div>

        {{-- Framed video placeholder — the signature "watch it work" moment --}}
        <div id="overview-video" class="hiw-reveal relative mx-auto mt-16 max-w-4xl scroll-mt-28">
            <div class="pointer-events-none absolute inset-0 -z-10 rounded-[28px] blur-2xl" style="background: rgba(0,201,141,.10);"></div>

            <div class="overflow-hidden rounded-[24px] border border-white/8 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.65)]" style="background: var(--marketing-ink-soft);">
                <div class="flex items-center gap-1.5 border-b border-white/8 bg-white/[.02] px-4 py-2.5">
                    <span class="h-2 w-2 rounded-full bg-white/15"></span>
                    <span class="h-2 w-2 rounded-full bg-white/15"></span>
                    <span class="h-2 w-2 rounded-full bg-white/15"></span>
                    <span class="hiw-mono ml-2 truncate text-[10px] text-white/30">{{ $hero['video_url_label'] ?? '' }}</span>
                </div>

                {{-- VIDEO SLOT: replace this whole inner div with a <video>/<iframe> embed when the overview video is ready --}}
                <div class="relative flex aspect-video items-center justify-center" style="background: radial-gradient(60% 90% at 50% 40%, rgba(0,201,141,.10), transparent), var(--marketing-ink);">
                    <div class="relative inline-flex flex-col items-center" data-video-widget>
                        <button type="button" data-video-slot
                                class="hiw-focus hiw-pulse group flex h-20 w-20 items-center justify-center rounded-full border border-white/20 transition-transform duration-300 hover:scale-105"
                                style="background: var(--marketing-green);"
                                aria-label="{{ $hero['cta_secondary'] ?? '' }}">
                            <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7 translate-x-0.5" aria-hidden="true">
                                <path d="M8 5.5v13l11-6.5-11-6.5Z" fill="var(--marketing-ink)" />
                            </svg>
                        </button>
                        <span class="hiw-video-toast pointer-events-none absolute left-1/2 top-[calc(100%+0.85rem)] -translate-x-1/2 whitespace-nowrap rounded-lg border border-white/10 bg-black/85 px-3 py-1.5 text-[11px] text-white opacity-0" data-video-toast>
                            {{ $video['toast_hero'] ?? '' }}
                        </span>
                    </div>

                    <span class="hiw-mono absolute bottom-4 left-4 rounded-md border border-white/10 bg-black/40 px-2 py-1 text-[10px] text-white/60">
                        {{ $hero['video_duration'] ?? '' }}
                    </span>
                </div>
            </div>

            <p class="hiw-mono mt-4 text-center text-[11px] uppercase tracking-[0.1em] text-white/30">{{ $hero['video_caption'] ?? '' }}</p>
        </div>
    </section>

    {{-- 2. STATS — quick credibility numbers, count up when scrolled into view --}}
    <section id="stats" class="hiw-reveal relative mx-auto max-w-5xl px-6 pb-4 lg:px-8">
        <p class="hiw-mono mb-8 text-center text-[11px] uppercase tracking-[0.25em] text-white/30">{{ $stats['eyebrow'] ?? '' }}</p>

        <div class="grid grid-cols-2 divide-x divide-y divide-white/8 overflow-hidden rounded-2xl border border-white/8 sm:grid-cols-4 sm:divide-y-0" style="background: var(--marketing-ink-soft);" data-stats-grid>
            @foreach (($stats['items'] ?? []) as $stat)
                <div class="px-5 py-8 text-center">
                    <p class="hiw-serif text-3xl font-semibold text-white sm:text-4xl">
                        <span data-stat-value="{{ $stat['value'] ?? 0 }}">0</span>{{ $stat['suffix'] ?? '' }}
                    </p>
                    <p class="mt-2 text-xs leading-snug text-white/40">{{ $stat['label'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 3. PROCESS — sticky career-thread rail + scrolling steps --}}
    <section id="process" class="hiw-reveal relative mx-auto max-w-6xl px-6 py-20 lg:px-8">
        <div class="pointer-events-none absolute right-0 top-1/4 h-[360px] w-[480px] rounded-full blur-[110px]" style="background: rgba(0,201,141,.05);"></div>

        <div class="relative mx-auto mb-16 max-w-xl text-center">
            <span class="hiw-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ $process['eyebrow'] ?? '' }}</span>
            <h2 class="hiw-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ $process['title'] ?? '' }}</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-white/50">{{ $process['subtitle'] ?? '' }}</p>
        </div>

        <div class="relative grid gap-12 lg:grid-cols-[240px_1fr] lg:gap-16" data-steps-root>

            {{-- Sticky rail: desktop only. Also keyboard-navigable — focus a
                 step and use Arrow Up/Down or Left/Right to move between steps. --}}
            <div class="hidden lg:block">
                <div class="sticky top-32">
                    <div class="relative pl-10" data-thread-track>
                        <div class="absolute left-[15px] top-1 bottom-1 w-px bg-white/10"></div>
                        <div class="hiw-thread-fill absolute left-[15px] top-1 w-px" style="height: 0px; background: var(--marketing-green);" data-thread-fill></div>

                        @foreach ($steps as $stepKey => $step)
                            <button type="button"
                                    data-step-nav="{{ $loop->index }}"
                                    class="hiw-focus hiw-rail-node-btn group relative mb-14 flex w-full items-start gap-4 rounded-lg text-left last:mb-0"
                                    aria-current="{{ $loop->first ? 'step' : 'false' }}"
                                    aria-label="{{ $process['step_label'] ?? '' }} {{ $loop->iteration }}: {{ $step['nav_label'] ?? '' }}">
                                <span class="hiw-thread-node hiw-mono relative z-10 -ml-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/15 text-[11px] text-white/50" style="background: var(--marketing-ink);" data-thread-node>
                                    {{ $loop->iteration }}
                                </span>
                                <span>
                                    <span class="hiw-rail-eyebrow hiw-mono block text-[10px] uppercase tracking-[0.2em] text-white/30">{{ $process['step_label'] ?? '' }} {{ $loop->iteration }}</span>
                                    <span class="hiw-rail-label mt-1 block text-sm font-medium text-white/50">{{ $step['nav_label'] ?? '' }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Scrolling step content --}}
            <div class="space-y-24 lg:space-y-32">
                @foreach ($steps as $stepKey => $step)
                    <div id="step-{{ $stepKey }}" data-step-section="{{ $loop->index }}" class="hiw-reveal grid items-center gap-8 lg:grid-cols-[1fr_1.2fr] lg:gap-10">

                        <div>
                            <span class="hiw-mono mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 px-3 py-1 text-[10px] uppercase tracking-[0.15em] text-white/40 lg:hidden">
                                {{ $process['step_label'] ?? '' }} {{ $loop->iteration }} — {{ $step['nav_label'] ?? '' }}
                            </span>

                            <span class="hiw-mono block text-[10px] uppercase tracking-[0.2em] text-white/30">{{ $process['powered_by'] ?? '' }}</span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (($step['chips'] ?? []) as $chip)
                                    <span class="hiw-mono rounded-full border border-white/10 bg-white/[.03] px-2.5 py-1 text-[10px] uppercase tracking-[0.1em] text-white/50">
                                        {{ $chip }}
                                    </span>
                                @endforeach
                            </div>

                            <h3 class="hiw-serif mt-4 text-2xl font-semibold text-white sm:text-3xl">{{ $step['title'] ?? '' }}</h3>
                            <p class="mt-3 text-sm leading-relaxed text-white/50 sm:text-base">{{ $step['desc'] ?? '' }}</p>

                            @if (!empty($step['benefits']))
                                <details class="hiw-details hiw-focus mt-5 rounded-xl border border-white/8 px-4 py-3" style="background: rgba(255,255,255,.02);">
                                    <summary class="hiw-focus flex items-center justify-between gap-3 rounded text-sm font-medium text-white/60">
                                        {{ $step['benefits_label'] ?? '' }}
                                        <svg viewBox="0 0 24 24" fill="none" class="hiw-chevron h-4 w-4 shrink-0 text-white/40" aria-hidden="true">
                                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </summary>
                                    <ul class="mt-3 space-y-2">
                                        @foreach ($step['benefits'] as $benefit)
                                            <li class="flex items-start gap-2 text-sm leading-relaxed text-white/50">
                                                <span class="mt-1 h-1 w-1 shrink-0 rounded-full" style="background: var(--marketing-green);"></span>
                                                {{ $benefit }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>

                        <div class="hiw-glow-card group relative">
                            <div class="absolute inset-0 -z-10 rounded-3xl blur-2xl transition-colors duration-500" style="background: rgba(0,201,141,.06);"></div>
                            <div class="overflow-hidden rounded-2xl border border-white/8 shadow-[0_20px_60px_-15px_rgba(0,0,0,0.6)] transition-all duration-700 ease-out group-hover:-translate-y-2" style="background: var(--marketing-ink-soft);">
                                <div class="flex items-center gap-1.5 border-b border-white/8 bg-white/[.02] px-4 py-2.5">
                                    <span class="h-2 w-2 rounded-full bg-white/15"></span>
                                    <span class="h-2 w-2 rounded-full bg-white/15"></span>
                                    <span class="h-2 w-2 rounded-full bg-white/15"></span>
                                    <span class="hiw-mono ml-2 truncate text-[10px] text-white/30">app.careertalent.ai/{{ $step['path'] ?? '' }}</span>
                                </div>

                                {{-- VIDEO SLOT: replace the <img> + button below with a <video>/<iframe> embed for this step's walkthrough --}}
                                <div class="relative" data-video-widget>
                                    <img src="{{ asset('images/how-it-works/'.$stepKey.'.png') }}"
                                         onerror="this.onerror=null;this.src='https://placehold.co/1280x800/10172b/aeb7d0?text={{ urlencode($step['title'] ?? '') }}'"
                                         alt="{{ $step['title'] ?? '' }}"
                                         class="aspect-[16/10] w-full object-cover opacity-90 transition-all duration-700 group-hover:opacity-100 group-hover:scale-105">

                                    <button type="button" data-video-slot
                                            class="hiw-focus hiw-play-pill absolute bottom-4 right-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/40 px-3.5 py-2 backdrop-blur-md transition-all duration-300 hover:border-emerald-400/40 hover:bg-black/60"
                                            aria-label="{{ $step['video_label'] ?? '' }}">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full" style="background: var(--marketing-green);">
                                            <svg viewBox="0 0 24 24" fill="none" class="h-2.5 w-2.5 translate-x-[1px]" aria-hidden="true">
                                                <path d="M8 5.5v13l11-6.5-11-6.5Z" fill="var(--marketing-ink)" />
                                            </svg>
                                        </span>
                                        <span class="hiw-mono text-[10px] uppercase tracking-[0.15em] text-white">{{ $step['video_label'] ?? '' }}</span>
                                    </button>

                                    <span class="hiw-video-toast pointer-events-none absolute bottom-16 right-4 rounded-lg border border-white/10 bg-black/85 px-3 py-1.5 text-[11px] text-white opacity-0" data-video-toast>
                                        {{ $video['toast_step'] ?? '' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 4. LIVE DEMO — pick a target role, watch a sample match score animate in --}}
    <section id="demo" class="hiw-reveal relative mx-auto max-w-4xl px-6 py-20 lg:px-8">
        <div class="pointer-events-none absolute left-1/2 top-1/3 h-[360px] w-[600px] -translate-x-1/2 rounded-full blur-[120px]" style="background: rgba(0,201,141,.07);"></div>

        <div class="relative mx-auto mb-10 max-w-xl text-center">
            <span class="hiw-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ $demo['eyebrow'] ?? '' }}</span>
            <h2 class="hiw-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ $demo['title'] ?? '' }}</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-white/50">{{ $demo['subtitle'] ?? '' }}</p>
        </div>

        <div class="relative overflow-hidden rounded-2xl border border-white/8 p-6 sm:p-8" style="background: var(--marketing-ink-soft);">

            <p class="hiw-mono mb-3 text-[10px] uppercase tracking-[0.2em] text-white/30">{{ $demo['role_label'] ?? '' }}</p>
            <div class="flex flex-wrap gap-2" data-demo-roles role="group" aria-label="{{ $demo['role_label'] ?? '' }}">
                @foreach (($demo['roles'] ?? []) as $role)
                    <button type="button"
                            data-demo-role-btn="{{ $role['key'] ?? '' }}"
                            aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                            class="hiw-focus hiw-chip-btn rounded-full border border-white/10 px-4 py-2 text-xs font-medium text-white/50">
                        {{ $role['label'] ?? '' }}
                    </button>
                @endforeach
            </div>

            <div class="mt-8 grid gap-8 sm:grid-cols-[auto_1fr] sm:items-center">
                <div>
                    <p class="hiw-mono text-[10px] uppercase tracking-[0.2em] text-white/30">{{ $demo['score_label'] ?? '' }}</p>
                    <p class="hiw-serif text-5xl font-semibold text-white">
                        <span data-demo-score>0</span>%
                    </p>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-white/10">
                    <div class="hiw-bar-fill h-full rounded-full" style="width: 0%; background: var(--marketing-green);" data-demo-bar></div>
                </div>
            </div>

            <div class="mt-8 grid gap-6 sm:grid-cols-2">
                <div>
                    <p class="hiw-mono mb-3 text-[10px] uppercase tracking-[0.2em] text-white/30">{{ $demo['matched_label'] ?? '' }}</p>
                    <ul class="space-y-2" data-demo-matched></ul>
                </div>
                <div>
                    <p class="hiw-mono mb-3 text-[10px] uppercase tracking-[0.2em] text-white/30">{{ $demo['gaps_label'] ?? '' }}</p>
                    <ul class="space-y-2" data-demo-gaps></ul>
                </div>
            </div>

            <p class="hiw-mono mt-8 text-center text-[10px] uppercase tracking-[0.15em] text-white/30">{{ $demo['caption'] ?? '' }}</p>

            <div class="mt-5 text-center">
                <a href="{{ route('register') }}" class="hiw-focus text-sm font-semibold underline-offset-4 hover:underline" style="color: var(--marketing-green);">
                    {{ $demo['cta'] ?? '' }} →
                </a>
            </div>
        </div>
    </section>

    {{-- 5. FAQ — native <details>/<summary>, no JS required --}}
    <section id="faq" class="hiw-reveal relative mx-auto max-w-3xl px-6 py-20 lg:px-8">
        <div class="relative mx-auto mb-10 max-w-xl text-center">
            <span class="hiw-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ $faq['eyebrow'] ?? '' }}</span>
            <h2 class="hiw-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ $faq['title'] ?? '' }}</h2>
        </div>

        <div class="divide-y divide-white/8 overflow-hidden rounded-2xl border border-white/8" style="background: var(--marketing-ink-soft);">
            @foreach (($faq['items'] ?? []) as $item)
                <details class="hiw-details hiw-focus group px-5 py-4 sm:px-6">
                    <summary class="hiw-focus flex items-center justify-between gap-4 rounded text-sm font-medium text-white/70 sm:text-base">
                        {{ $item['q'] ?? '' }}
                        <svg viewBox="0 0 24 24" fill="none" class="hiw-chevron h-4 w-4 shrink-0 text-white/40" aria-hidden="true">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-white/50">{{ $item['a'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- 6. CLOSING CTA --}}
    <section id="start" class="hiw-reveal relative mx-auto max-w-5xl px-6 pb-28 pt-4 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl border border-white/10 px-8 py-16 text-center sm:px-16" style="background: var(--marketing-ink-soft);">
            <div class="pointer-events-none absolute left-1/2 top-0 h-[300px] w-[600px] -translate-x-1/2 rounded-full blur-[120px]" style="background: rgba(0,201,141,.14);"></div>

            <div class="relative">
                <h2 class="hiw-serif text-2xl font-semibold text-white sm:text-4xl">{{ $closing['title'] ?? '' }}</h2>
                <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-white/50 sm:text-base">{{ $closing['desc'] ?? '' }}</p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register') }}"
                       class="hiw-focus rounded-full bg-white px-7 py-3 text-sm font-extrabold !text-black transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_40px_-12px_rgba(255,255,255,.35)]">
                        {{ $closing['cta_primary'] ?? '' }}
                    </a>
                    <a href="{{ route('login') }}"
                       class="hiw-focus rounded-full border border-white/15 px-7 py-3 text-sm font-medium text-white transition-all duration-300 hover:border-white/30 hover:bg-white/[.03]">
                        {{ $closing['cta_secondary'] ?? '' }}
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- Sticky CTA bar: hidden until the hero is scrolled past, hidden again once
     the closing CTA (#start) comes into view so there's never two CTAs stacked. --}}
<div class="hiw-sticky-bar pointer-events-none fixed inset-x-0 bottom-0 z-40 px-4 pb-4" data-sticky-bar>
    <div class="pointer-events-auto mx-auto flex max-w-md items-center justify-between gap-4 rounded-2xl border border-white/10 px-5 py-3.5 shadow-[0_20px_50px_-15px_rgba(0,0,0,0.6)] backdrop-blur-md" style="background: rgba(10,10,10,.85);">
        <p class="text-sm font-medium text-white">{{ $stickyCta['text'] ?? '' }}</p>
        <a href="{{ route('register') }}" class="hiw-focus shrink-0 rounded-full px-4 py-2 text-xs font-semibold whitespace-nowrap" style="background: var(--marketing-green); color: var(--marketing-ink);">
            {{ $stickyCta['button'] ?? '' }}
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        // Scroll-triggered reveal
        var revealTargets = document.querySelectorAll('.hiw-reveal');
        if ('IntersectionObserver' in window) {
            var revealObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });
            revealTargets.forEach(function (el) { revealObserver.observe(el); });
        } else {
            revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
        }

        // Career-thread rail: tracks which step is active and fills the thread to match
        var stepSections = Array.prototype.slice.call(document.querySelectorAll('[data-step-section]'));
        var navButtons = Array.prototype.slice.call(document.querySelectorAll('[data-step-nav]'));
        var threadTrack = document.querySelector('[data-thread-track]');
        var threadFill = document.querySelector('[data-thread-fill]');
        var threadNodes = Array.prototype.slice.call(document.querySelectorAll('[data-thread-node]'));

        var nodeOffsets = [];
        var activeIndex = -1;
        var ticking = false;

        function measure() {
            if (!threadTrack || !threadNodes.length) return;
            var trackTop = threadTrack.getBoundingClientRect().top + window.scrollY;
            nodeOffsets = threadNodes.map(function (node) {
                var rect = node.getBoundingClientRect();
                return (rect.top + window.scrollY + rect.height / 2) - trackTop;
            });
        }

        function setActive(index) {
            if (index === activeIndex || index < 0) return;
            activeIndex = index;
            navButtons.forEach(function (btn, idx) {
                btn.setAttribute('aria-current', idx === index ? 'step' : 'false');
            });
            if (threadFill && nodeOffsets[index] !== undefined) {
                threadFill.style.height = nodeOffsets[index] + 'px';
            }
        }

        function update() {
            ticking = false;
            if (!stepSections.length) return;
            var viewportAnchor = window.scrollY + window.innerHeight * 0.45;
            var closestIndex = 0;
            var closestDistance = Infinity;
            stepSections.forEach(function (section, idx) {
                var rect = section.getBoundingClientRect();
                var center = rect.top + window.scrollY + rect.height / 2;
                var distance = Math.abs(center - viewportAnchor);
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closestIndex = idx;
                }
            });
            setActive(closestIndex);
        }

        function onScroll() {
            if (!ticking) {
                window.requestAnimationFrame(update);
                ticking = true;
            }
        }

        if (stepSections.length && threadTrack) {
            measure();
            update();
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', function () { measure(); update(); });
        }

        function goToStep(idx) {
            var target = stepSections[idx];
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        navButtons.forEach(function (btn, idx) {
            btn.addEventListener('click', function () { goToStep(idx); });

            // Keyboard nav: Arrow Up/Left = previous step, Arrow Down/Right = next step.
            // Scoped to the rail buttons themselves so it never hijacks page scroll elsewhere.
            btn.addEventListener('keydown', function (e) {
                var nextIdx = null;
                if (e.key === 'ArrowDown' || e.key === 'ArrowRight') nextIdx = Math.min(navButtons.length - 1, idx + 1);
                if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') nextIdx = Math.max(0, idx - 1);
                if (nextIdx === null) return;
                e.preventDefault();
                navButtons[nextIdx].focus();
                goToStep(nextIdx);
            });
        });

        // Video placeholder toast — remove once real <video>/<iframe> embeds replace the slots
        document.querySelectorAll('[data-video-slot]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var widget = btn.closest('[data-video-widget]');
                var toast = widget ? widget.querySelector('[data-video-toast]') : null;
                if (!toast) return;
                toast.classList.add('is-visible');
                window.clearTimeout(toast._hiwHideTimer);
                toast._hiwHideTimer = window.setTimeout(function () {
                    toast.classList.remove('is-visible');
                }, 1800);
            });
        });

        // Stats count-up: animate each number from 0 to its target once visible
        var statEls = Array.prototype.slice.call(document.querySelectorAll('[data-stat-value]'));
        function animateCount(el) {
            var target = parseInt(el.getAttribute('data-stat-value'), 10) || 0;
            var start = null;
            var duration = 1200;
            function step(ts) {
                if (!start) start = ts;
                var progress = Math.min((ts - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(eased * target).toLocaleString();
                if (progress < 1) window.requestAnimationFrame(step);
            }
            window.requestAnimationFrame(step);
        }
        if (statEls.length) {
            if ('IntersectionObserver' in window) {
                var statObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            animateCount(entry.target);
                            statObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });
                statEls.forEach(function (el) { statObserver.observe(el); });
            } else {
                statEls.forEach(animateCount);
            }
        }

        // Live demo: swap in a sample match score, matched skills, and gaps per role
        var demoRoles = @json($demo['roles'] ?? []);
        var demoRoleButtons = Array.prototype.slice.call(document.querySelectorAll('[data-demo-role-btn]'));
        var demoScoreEl = document.querySelector('[data-demo-score]');
        var demoBarEl = document.querySelector('[data-demo-bar]');
        var demoMatchedEl = document.querySelector('[data-demo-matched]');
        var demoGapsEl = document.querySelector('[data-demo-gaps]');

        function renderDemoList(el, items, tone) {
            if (!el) return;
            el.innerHTML = '';
            (items || []).forEach(function (text, i) {
                var li = document.createElement('li');
                li.className = 'hiw-demo-list-item flex items-start gap-2 text-sm leading-relaxed text-white/50';
                li.style.animationDelay = (i * 60) + 'ms';
                var icon = tone === 'match'
                    ? '<svg viewBox="0 0 24 24" fill="none" class="mt-0.5 h-4 w-4 shrink-0" style="color: var(--marketing-green);" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                    : '<svg viewBox="0 0 24 24" fill="none" class="mt-0.5 h-4 w-4 shrink-0 text-white/25" aria-hidden="true"><path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                li.innerHTML = icon + '<span>' + text + '</span>';
                el.appendChild(li);
            });
        }

        function animateDemoScore(target) {
            if (!demoScoreEl) return;
            var start = null;
            var duration = 900;
            function step(ts) {
                if (!start) start = ts;
                var progress = Math.min((ts - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                demoScoreEl.textContent = Math.round(eased * target);
                if (progress < 1) window.requestAnimationFrame(step);
            }
            window.requestAnimationFrame(step);
        }

        function activateDemoRole(key) {
            var role = demoRoles.find(function (r) { return r.key === key; });
            if (!role) return;
            demoRoleButtons.forEach(function (btn) {
                btn.setAttribute('aria-pressed', btn.getAttribute('data-demo-role-btn') === key ? 'true' : 'false');
            });
            animateDemoScore(role.score || 0);
            if (demoBarEl) demoBarEl.style.width = (role.score || 0) + '%';
            renderDemoList(demoMatchedEl, role.matched, 'match');
            renderDemoList(demoGapsEl, role.gaps, 'gap');
        }

        demoRoleButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateDemoRole(btn.getAttribute('data-demo-role-btn'));
            });
        });

        if (demoRoles.length) {
            // Only kick off the count-up once the demo card is actually visible
            var demoSection = document.getElementById('demo');
            if (demoSection && 'IntersectionObserver' in window) {
                var demoObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            activateDemoRole(demoRoles[0].key);
                            demoObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.3 });
                demoObserver.observe(demoSection);
            } else {
                activateDemoRole(demoRoles[0].key);
            }
        }

        // Sticky CTA bar: visible once past the hero, hidden again once the closing CTA is in view
        var stickyBar = document.querySelector('[data-sticky-bar]');
        var heroCtas = document.querySelector('[data-hero-ctas]');
        var closingSection = document.getElementById('start');
        var pastHero = false;
        var atClosing = false;

        function syncStickyBar() {
            if (!stickyBar) return;
            stickyBar.classList.toggle('is-visible', pastHero && !atClosing);
        }

        if (stickyBar && heroCtas && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    pastHero = !entry.isIntersecting;
                    syncStickyBar();
                });
            }, { threshold: 0 }).observe(heroCtas);
        }

        if (stickyBar && closingSection && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    atClosing = entry.isIntersecting;
                    syncStickyBar();
                });
            }, { threshold: 0 }).observe(closingSection);
        }
    });
</script>

@endsection