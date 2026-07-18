@extends('marketing.layouts.marketing')
@section('title', __('marketing.bootcamp.title'))

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');

    main.bootcamp-page {
        font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
    }
    main.bootcamp-page h1,
    main.bootcamp-page h2,
    main.bootcamp-page h3,
    main.bootcamp-page h4 {
        font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif;
        letter-spacing: -0.015em;
    }

    .skill-graph .edge { fill:none; stroke: rgba(233,239,245,0.16); stroke-width:1.4; stroke-dasharray: 220; stroke-dashoffset: 220; animation: draw 1.4s ease forwards; }
    .skill-graph .edge.hot { stroke: rgba(41,199,111,0.35); }
    .skill-graph .pulse { fill: none; stroke: #29c76f; stroke-width: 1; opacity:0; animation: pulse 2.6s ease-out infinite; }
    @keyframes draw { to { stroke-dashoffset: 0; } }
    @keyframes pulse { 0% { r:16; opacity:.55; } 100% { r:44; opacity:0; } }
    .blink-dot { animation: blink 1.6s ease-in-out infinite; }
    @keyframes blink { 0%,100% { opacity:1; } 50% { opacity:.25; } }
</style>

<main class="bg-slate-950 text-slate-300 font-sans selection:bg-green-500/30 bootcamp-page">

    {{-- HERO SECTION --}}
    <section class="py-20 lg:py-28 px-6 max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
        <div class="space-y-8">
            <div class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-slate-900 border border-slate-800">
                <div class="flex items-center -space-x-1">
                    <div class="w-2.5 h-2.5 rounded-full bg-blue-500 border-2 border-slate-900"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-red-500 border-2 border-slate-900"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-yellow-400 border-2 border-slate-900"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-slate-900"></div>
                </div>
                <span class="text-sm font-semibold text-slate-200">{{ __('marketing.bootcamp.hero.badge') }}</span>
            </div>

            {{-- Notice the {!! !!} so the <em> tag turns green instead of printing as text --}}
            <h1 class="text-4xl md:text-6xl font-bold text-white leading-tight tracking-tight [&>em]:text-green-400 [&>em]:not-italic">
                {!! __('marketing.bootcamp.hero.headline') !!}
            </h1>

            <p class="text-lg text-slate-400 leading-relaxed max-w-xl">
                {{ __('marketing.bootcamp.hero.sub') }}
            </p>

            <div class="flex flex-wrap items-center gap-4">
                <a href="#cta" class="px-8 py-3.5 rounded-full bg-green-500 text-green-950 font-bold hover:bg-green-400 transition-colors inline-flex items-center gap-2">
                    {{ __('marketing.bootcamp.hero.cta_primary') }}
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <a href="https://github.com/busebatan/careertalent-ai" target="_blank" class="px-8 py-3.5 rounded-full bg-transparent border border-slate-700 text-white font-semibold hover:border-green-500/50 hover:text-green-400 transition-colors">
                    {{ __('marketing.bootcamp.hero.cta_secondary') }}
                </a>
            </div>
        </div>

        {{-- VISUAL GRAPH --}}
        <div class="relative rounded-3xl border border-slate-800 bg-gradient-to-b from-slate-900 to-slate-950 p-8 overflow-hidden shadow-2xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(41,199,111,0.08),transparent_55%)] pointer-events-none"></div>

            <div class="flex justify-between items-center text-xs font-mono uppercase tracking-widest text-slate-500 mb-6 relative z-10">
                <span>{{ __('marketing.bootcamp.hero.visual.preview') }}</span>
                <span class="inline-flex items-center gap-2 text-green-400">
                    <span class="w-2 h-2 rounded-full bg-green-500 blink-dot"></span>
                    {{ __('marketing.bootcamp.hero.visual.graph') }}
                </span>
            </div>

            <svg class="skill-graph w-full h-auto relative z-10" viewBox="0 0 460 320" fill="none">
                <path class="edge" d="M230 160 C 170 140, 120 110, 80 70" />
                <path class="edge" d="M230 160 C 170 180, 120 210, 78 240" />
                <path class="edge hot" d="M230 160 C 290 120, 340 90, 385 60" />
                <path class="edge" d="M230 160 C 290 190, 340 220, 388 250" />
                <path class="edge" d="M230 160 C 230 110, 230 70, 230 40" />
                <circle class="pulse" cx="230" cy="160" r="16" />

                <g class="node">
                    <circle cx="80" cy="70" r="30" fill="#131c27" stroke="rgba(233,239,245,0.16)" stroke-width="1.5" />
                    <text x="80" y="74" font-size="11" fill="#eef3f7" text-anchor="middle" font-weight="600">{{ __('marketing.bootcamp.hero.visual.nodes.skill') }}</text>
                </g>
                <g class="node">
                    <circle cx="78" cy="240" r="30" fill="#131c27" stroke="rgba(233,239,245,0.16)" stroke-width="1.5" />
                    <text x="78" y="244" font-size="11" fill="#eef3f7" text-anchor="middle" font-weight="600">{{ __('marketing.bootcamp.hero.visual.nodes.interest') }}</text>
                </g>
                <g class="node">
                    <circle cx="385" cy="60" r="30" fill="#131c27" stroke="rgba(233,239,245,0.16)" stroke-width="1.5" />
                    <text x="385" y="64" font-size="11" fill="#eef3f7" text-anchor="middle" font-weight="600">{{ __('marketing.bootcamp.hero.visual.nodes.role') }}</text>
                </g>
                <g class="node">
                    <circle cx="388" cy="250" r="34" fill="#131c27" stroke="rgba(233,239,245,0.16)" stroke-width="1.5" />
                    <text x="388" y="254" font-size="11" fill="#eef3f7" text-anchor="middle" font-weight="600">{{ __('marketing.bootcamp.hero.visual.nodes.mentor') }}</text>
                </g>
                <g class="node">
                    <circle cx="230" cy="40" r="26" fill="#131c27" stroke="rgba(233,239,245,0.16)" stroke-width="1.5" />
                    <text x="230" y="44" font-size="10.5" fill="#eef3f7" text-anchor="middle" font-weight="600">{{ __('marketing.bootcamp.hero.visual.nodes.cohort') }}</text>
                </g>
                <g class="node core">
                    <circle cx="230" cy="160" r="40" fill="#29c76f" stroke="#5be095" filter="drop-shadow(0 0 14px rgba(41,199,111,0.55))" />
                    <text x="230" y="157" font-size="11.5" fill="#052712" text-anchor="middle" font-weight="bold">Career</text>
                    <text x="230" y="171" font-size="11.5" fill="#052712" text-anchor="middle" font-weight="bold">Talent AI</text>
                </g>
            </svg>

            <div class="flex gap-4 mt-6 relative z-10">
                <span class="flex items-center gap-2 text-xs text-slate-400"><span class="w-2 h-2 rounded-full bg-green-500"></span>{{ __('marketing.bootcamp.hero.visual.active') }}</span>
                <span class="flex items-center gap-2 text-xs text-slate-400"><span class="w-2 h-2 rounded-full bg-slate-600"></span>{{ __('marketing.bootcamp.hero.visual.signal') }}</span>
            </div>
        </div>
    </section>

    {{-- PARTNER STRIP --}}
    <div class="border-y border-slate-800 bg-slate-900/50 py-6 px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-center md:justify-between gap-6">
            <span class="text-xs font-mono text-slate-500 uppercase tracking-widest">{{ __('marketing.bootcamp.partners.label') }}</span>
            <div class="flex flex-wrap justify-center gap-8 text-sm font-semibold text-slate-300">
                <span>T.C. Sanayi ve Teknoloji Bakanlığı</span>
                <span>Google Türkiye</span>
                <span>T3 Girişim Merkezi</span>
                <span>Türkiye Girişimcilik Vakfı</span>
            </div>
        </div>
    </div>

    {{-- ABOUT & FACTS --}}
    <section class="py-24 px-6 bg-slate-900 border-b border-slate-800">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-16">
            <div class="space-y-6">
                <span class="text-xs font-mono text-green-400 uppercase tracking-widest flex items-center gap-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 ring-4 ring-green-500/20"></span>
                    {{ __('marketing.bootcamp.about.eyebrow') }}
                </span>
                <h2 class="text-3xl md:text-4xl font-bold text-white">{{ __('marketing.bootcamp.about.headline') }}</h2>
                <p class="text-slate-400 text-lg">{{ __('marketing.bootcamp.about.p1') }}</p>
                <p class="text-slate-400 text-lg">{{ __('marketing.bootcamp.about.p2') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Bomb-Proof array check added here! --}}
                @if(is_array(__('marketing.bootcamp.about.facts')))
                    @foreach(__('marketing.bootcamp.about.facts') as $fact)
                        <div class="bg-slate-950 border border-slate-800 rounded-2xl p-6 flex flex-col justify-center">
                            <strong class="text-3xl font-bold text-white mb-2">{{ $fact['value'] ?? '' }}</strong>
                            <span class="text-sm text-slate-400">{{ $fact['label'] ?? '' }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

</main>
@endsection