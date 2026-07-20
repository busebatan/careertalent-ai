@extends('marketing.layouts.marketing')
@section('title', __('company_positions.public.submitted'))
@section('content')
<section class="flex min-h-[calc(100vh-80px)] items-center justify-center bg-[#080b18] px-5 py-16 text-white">
    <div class="w-full max-w-xl rounded-[28px] border border-white/10 bg-white/[0.045] p-8 text-center sm:p-12"><span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-400/10 text-emerald-300"><i data-lucide="{{ $alreadyExists ? 'history' : 'circle-check-big' }}" class="h-8 w-8" aria-hidden="true"></i></span><h1 class="mt-6 text-3xl font-bold">{{ $alreadyExists ? __('company_positions.public.existing') : __('company_positions.public.submitted') }}</h1><p class="mt-3 text-slate-300">{{ $alreadyExists ? __('company_positions.public.continue') : 'Başvuru durumu aday panelinizden izlenebilir.' }}</p><a class="mt-7 inline-flex rounded-xl bg-emerald-500 px-6 py-3 font-bold text-slate-950" href="{{ route('panel.applications') }}">{{ __('panel.nav.applications') }}</a></div>
</section>
@endsection
