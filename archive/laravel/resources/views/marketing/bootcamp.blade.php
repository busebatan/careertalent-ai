@extends('marketing.layouts.marketing')

@section('title', 'Bootcamp')

@section('content')
<section class="mx-auto max-w-4xl px-4 py-16 text-center">
    <h1 class="mb-6 text-3xl font-bold">YZTA Bootcamp İş Birliği</h1>
    <p class="mb-8 text-lg text-slate-400">
        CareerTalent AI, YZTA Bootcamp 2026 Grup 92 pilot projesidir.
        Bootcamp öğrencileri ücretsiz kullanır; mentörler cohort ilerlemesini takip eder.
    </p>
    <a href="{{ route('panel.dashboard') }}" class="inline-block rounded-xl bg-emerald-500 px-8 py-4 font-semibold text-slate-950 hover:bg-emerald-400">
        Pilot Programa Katıl
    </a>
</section>
@endsection
