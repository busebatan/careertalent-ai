@extends('marketing.layouts.marketing')

@section('title', __('marketing.auth.login_title'))

@section('content')
<section class="mx-auto max-w-md px-4 py-16">
    <header class="mb-8 text-center">
        <h1 class="mb-2 text-2xl font-bold">{{ __('marketing.auth.login_title') }}</h1>
        <p class="text-sm text-slate-400">{{ __('marketing.auth.login_subtitle') }}</p>
    </header>

    <form class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-6" action="#" method="post" onsubmit="return false">
        @csrf
        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('marketing.auth.email') }}</label>
            <input type="email" id="email" name="email" autocomplete="email"
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('marketing.auth.password') }}</label>
            <input type="password" id="password" name="password" autocomplete="current-password"
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <button type="submit" class="w-full rounded-xl bg-emerald-500 py-3 text-sm font-semibold text-slate-950 hover:bg-emerald-400">
            {{ __('marketing.auth.submit_login') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        {{ __('marketing.auth.no_account') }}
        <a href="{{ route('register') }}" class="font-medium text-emerald-400 hover:underline">{{ __('marketing.nav.register') }}</a>
    </p>

    <p class="mt-6 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-center text-xs leading-relaxed text-amber-200/90">
        {{ __('marketing.auth.sprint_note') }}
        <a href="{{ route('panel.dashboard') }}" class="mt-2 inline-block font-semibold text-emerald-400 hover:underline">{{ __('marketing.auth.demo_panel') }} →</a>
    </p>
</section>
@endsection
