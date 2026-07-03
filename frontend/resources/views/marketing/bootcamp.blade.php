@extends('marketing.layouts.marketing')

@section('title', __('marketing.bootcamp.title'))

@section('content')
<section class="mx-auto max-w-4xl px-4 py-16 text-center">
    <p class="mb-4 text-sm font-medium uppercase tracking-widest text-emerald-400">{{ __('marketing.home.eyebrow') }}</p>
    <h1 class="mb-6 text-3xl font-bold tracking-tight md:text-4xl">{{ __('marketing.bootcamp.headline') }}</h1>
    <p class="mx-auto mb-10 max-w-2xl text-lg leading-relaxed text-slate-400">
        {{ __('marketing.bootcamp.body') }}
    </p>
    <a href="{{ route('register') }}" class="inline-block rounded-xl bg-emerald-500 px-8 py-4 font-semibold text-slate-950 hover:bg-emerald-400">
        {{ __('marketing.bootcamp.cta') }}
    </a>
</section>
@endsection
