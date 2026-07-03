@extends('marketing.layouts.marketing')

@section('title', __('marketing.features.title'))

@section('content')
<section class="mx-auto max-w-4xl px-4 py-16">
    <header class="mb-10">
        <h1 class="mb-3 text-3xl font-bold tracking-tight md:text-4xl">{{ __('marketing.features.title') }}</h1>
        <p class="text-lg text-slate-400">{{ __('marketing.features.intro') }}</p>
    </header>
    <ul class="space-y-4">
        @foreach(['cv', 'career', 'roadmap', 'chat'] as $key)
            <li class="flex items-start gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <span class="mt-0.5 text-emerald-400" aria-hidden="true">✓</span>
                <span class="text-slate-200">{{ __('marketing.features.'.$key) }}</span>
            </li>
        @endforeach
        <li class="flex items-start gap-4 rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 p-5">
            <span class="mt-0.5 rounded bg-slate-800 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ __('marketing.features.soon') }}</span>
            <span class="text-slate-400">{{ __('marketing.features.jobs') }}</span>
        </li>
    </ul>
    <div class="mt-10 text-center">
        <a href="{{ route('register') }}" class="inline-block rounded-xl bg-emerald-500 px-8 py-4 font-semibold text-slate-950 hover:bg-emerald-400">
            {{ __('marketing.home.cta_register') }}
        </a>
    </div>
</section>
@endsection
