@extends('marketing.layouts.marketing')

@section('title', __('marketing.how.title'))

@section('content')
<section class="mx-auto max-w-4xl px-4 py-16">
    <header class="mb-10 text-center md:text-left">
        <h1 class="mb-3 text-3xl font-bold tracking-tight md:text-4xl">{{ __('marketing.how.title') }}</h1>
        <p class="text-lg text-slate-400">{{ __('marketing.how.intro') }}</p>
    </header>
    <ol class="space-y-6">
        @foreach(range(1, 4) as $step)
            <li class="rounded-2xl border border-slate-800 bg-slate-900 p-6 md:flex md:gap-6">
                <span class="mb-3 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-sm font-bold text-emerald-400 md:mb-0">
                    {{ $step }}
                </span>
                <div>
                    <h2 class="mb-2 text-xl font-semibold">{{ __('marketing.how.step'.$step.'_title') }}</h2>
                    <p class="text-sm leading-relaxed text-slate-400">{{ __('marketing.how.step'.$step.'_desc') }}</p>
                </div>
            </li>
        @endforeach
    </ol>
    <div class="mt-10 text-center">
        <a href="{{ route('register') }}" class="inline-block rounded-xl bg-emerald-500 px-8 py-4 font-semibold text-slate-950 hover:bg-emerald-400">
            {{ __('marketing.how.cta') }}
        </a>
    </div>
</section>
@endsection
