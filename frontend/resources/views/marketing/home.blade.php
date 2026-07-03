@extends('marketing.layouts.marketing')

@section('title', __('marketing.home.title'))

@section('content')
<section class="mx-auto max-w-6xl px-4 py-12 lg:py-20">
    <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
        <div class="text-center lg:text-left">
            <p class="mb-4 text-sm font-medium uppercase tracking-widest text-emerald-400">{{ __('marketing.home.eyebrow') }}</p>
            <h1 class="mb-6 text-4xl font-bold leading-tight tracking-tight md:text-5xl lg:text-6xl">
                {{ __('marketing.home.headline') }}
                <span class="text-emerald-400">{{ __('marketing.home.headline_highlight') }}</span>
                @if(__('marketing.home.headline_end'))
                    {{ __('marketing.home.headline_end') }}
                @endif
            </h1>
            <p class="mx-auto mb-8 max-w-xl text-lg leading-relaxed text-slate-400 lg:mx-0">
                {{ __('marketing.home.subtitle') }}
            </p>
            <div class="flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                <a href="{{ route('register') }}" class="w-full rounded-xl bg-emerald-500 px-8 py-4 text-center text-lg font-semibold text-slate-950 transition hover:bg-emerald-400 sm:w-auto">
                    {{ __('marketing.home.cta_register') }}
                </a>
                <a href="{{ route('how-it-works') }}" class="w-full rounded-xl border border-slate-700 px-8 py-4 text-center text-lg text-slate-300 transition hover:border-slate-500 hover:text-white sm:w-auto">
                    {{ __('marketing.home.cta_how') }}
                </a>
            </div>
        </div>
        <div class="mx-auto w-full max-w-md lg:max-w-none">
            @include('marketing.partials.panel-preview')
        </div>
    </div>
</section>

<section class="border-t border-slate-800/60 bg-slate-900/30">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-16 md:grid-cols-3">
        <article class="rounded-2xl border border-slate-800 bg-slate-900 p-6 transition hover:border-slate-700">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-lg" aria-hidden="true">📄</div>
            <h2 class="mb-2 text-lg font-semibold">{{ __('marketing.home.feature_cv_title') }}</h2>
            <p class="text-sm leading-relaxed text-slate-400">{{ __('marketing.home.feature_cv_desc') }}</p>
        </article>
        <article class="rounded-2xl border border-slate-800 bg-slate-900 p-6 transition hover:border-slate-700">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-lg" aria-hidden="true">🗺️</div>
            <h2 class="mb-2 text-lg font-semibold">{{ __('marketing.home.feature_roadmap_title') }}</h2>
            <p class="text-sm leading-relaxed text-slate-400">{{ __('marketing.home.feature_roadmap_desc') }}</p>
        </article>
        <article class="rounded-2xl border border-slate-800 bg-slate-900 p-6 transition hover:border-slate-700">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-lg" aria-hidden="true">🎯</div>
            <h2 class="mb-2 text-lg font-semibold">{{ __('marketing.home.feature_match_title') }}</h2>
            <p class="text-sm leading-relaxed text-slate-400">{{ __('marketing.home.feature_match_desc') }}</p>
        </article>
    </div>
</section>
@endsection
