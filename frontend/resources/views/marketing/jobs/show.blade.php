@extends('marketing.layouts.marketing')
@section('title', data_get($job, 'position.title', __('company_positions.title')))
@section('description', data_get($job, 'position.responsibilities', __('company_positions.public.process_title')))
@section('content')
@php
    $position = $job['position'];
    $organization = $job['organization'];
    $applicationOpen = (bool) ($position['application_open'] ?? false);
    $positionPath = basename((string) ($position['public_path'] ?? request()->route('positionPath')));
@endphp
<section class="min-h-[calc(100vh-80px)] bg-[#080b18] px-5 py-16 text-white sm:px-8">
    <div class="mx-auto max-w-6xl">
        <div class="grid gap-8 lg:grid-cols-[1.35fr_.65fr]">
            <main class="rounded-[28px] border border-white/10 bg-white/[0.045] p-7 shadow-2xl shadow-black/30 sm:p-10">
                <div class="flex flex-wrap items-center gap-3"><span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">{{ $organization['name'] }}</span><span class="text-sm text-slate-400">{{ __('company_positions.status.'.($position['status'] ?? 'draft')) }}</span></div>
                <h1 class="mt-6 text-4xl font-bold tracking-tight sm:text-5xl">{{ $position['title'] }}</h1>
                <p class="mt-4 text-lg text-slate-300">{{ $position['location'] ?? '—' }} · {{ isset($position['workplace_type']) ? __('company.positions.workplace_'.$position['workplace_type']) : '—' }} · {{ ucfirst($position['level'] ?? '') }}</p>
                @if(!empty($position['responsibilities']))<p class="mt-8 whitespace-pre-line text-base leading-8 text-slate-300">{{ $position['responsibilities'] }}</p>@endif

                <div class="mt-8 flex flex-wrap gap-2">@foreach(array_slice($position['must_have_skills'] ?? [], 0, 8) as $skill)<span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-slate-200">{{ $skill }}</span>@endforeach</div>

                @if(!$applicationOpen)
                    <div class="mt-8 rounded-2xl border border-amber-400/20 bg-amber-400/10 p-5 text-amber-100">{{ ($position['status'] ?? null) === 'paused' ? __('company_positions.public.paused') : __('company_positions.public.closed') }}</div>
                @else
                    <a class="mt-8 inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-6 py-3.5 font-bold text-slate-950 transition hover:bg-emerald-400" href="{{ route('public.jobs.start', ['organizationSlug' => $organization['slug'], 'positionPath' => $positionPath, 'source' => $source]) }}">{{ __('company_positions.public.start') }}<i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i></a>
                @endif
            </main>

            <aside class="space-y-6">
                <section class="rounded-[24px] border border-white/10 bg-white/[0.045] p-6"><h2 class="text-lg font-bold">{{ __('company_positions.public.process_title') }}</h2><ol class="mt-5 space-y-4 text-sm text-slate-300">@foreach(range(1,4) as $step)<li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-400/10 text-xs font-bold text-emerald-300">{{ $step }}</span><span>{{ __('company_positions.public.process_'.$step) }}</span></li>@endforeach</ol></section>
                <section class="rounded-[24px] border border-white/10 bg-white/[0.045] p-6"><div class="space-y-3 text-sm text-slate-300"><p>{{ __('company_positions.public.estimated_application', ['minutes' => $position['estimated_application_minutes'] ?? 8]) }}</p><p>{{ __('company_positions.public.estimated_assessment', ['minutes' => $position['estimated_assessment_minutes'] ?? 35]) }}</p></div><hr class="my-5 border-white/10"><h2 class="font-bold">{{ __('company_positions.public.shared_title') }}</h2><ul class="mt-4 space-y-2 text-sm text-slate-300"><li>✓ {{ __('company_positions.fields.must_have_skills') }}</li><li>✓ İletişim bilgileriniz</li><li>✓ Bu ilana ait değerlendirme sonucu</li></ul><p class="mt-5 rounded-xl bg-emerald-400/10 p-3 text-sm text-emerald-200">{{ __('company_positions.public.not_shared') }}</p></section>
            </aside>
        </div>
    </div>
</section>
@endsection
