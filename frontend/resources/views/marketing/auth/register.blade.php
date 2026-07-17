@extends('marketing.layouts.marketing')

@section('title', __('marketing.auth.register_title'))

@section('content')
<section class="mx-auto max-w-md px-4 py-16">
    <header class="mb-8 text-center">
        <h1 class="mb-2 text-2xl font-bold">{{ __('marketing.auth.register_title') }}</h1>
        <p class="text-sm text-slate-400">{{ __('marketing.auth.register_subtitle') }}</p>
    </header>

    <form class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-6" action="{{ route('register.submit') }}" method="post">
        @csrf
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ $errors->first() }}</div>
        @endif
        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('marketing.auth.name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required minlength="2" maxlength="100"
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('marketing.auth.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('marketing.auth.password') }}</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8" maxlength="128"
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">{{ __('marketing.auth.password_confirmation') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required minlength="8" maxlength="128"
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <button type="submit" class="w-full rounded-xl bg-emerald-500 py-3 text-sm font-semibold text-slate-950 hover:bg-emerald-400">
            {{ __('marketing.auth.submit_register') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        {{ __('marketing.auth.has_account') }}
        <a href="{{ route('login') }}" class="font-medium text-emerald-400 hover:underline">{{ __('marketing.nav.login') }}</a>
    </p>

</section>
@endsection
