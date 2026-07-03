@extends('marketing.layouts.marketing')

@section('title', 'Ana Sayfa')

@section('content')
<section class="mx-auto max-w-6xl px-4 py-20 text-center">
    <p class="mb-4 text-sm font-medium uppercase tracking-widest text-emerald-400">YZTA Bootcamp 2026</p>
    <h1 class="mb-6 text-4xl font-bold leading-tight md:text-6xl">
        Gelecek kaygını<br>
        <span class="text-emerald-400">yol haritasına</span> çevir
    </h1>
    <p class="mx-auto mb-10 max-w-2xl text-lg text-slate-400">
        CV'ni analiz et, hedef mesleğini seç, eksiklerini gör ve haftalık planla hazırlan.
        Hazır olunca sana uygun iş ilanlarıyla eşleş.
    </p>
    <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
        <a href="{{ route('panel.dashboard') }}" class="rounded-xl bg-emerald-500 px-8 py-4 text-lg font-semibold text-slate-950 hover:bg-emerald-400">
            Ücretsiz Başla
        </a>
        <a href="{{ route('how-it-works') }}" class="rounded-xl border border-slate-700 px-8 py-4 text-lg text-slate-300 hover:border-slate-500">
            Nasıl Çalışır?
        </a>
    </div>
</section>

<section class="mx-auto grid max-w-6xl gap-6 px-4 py-12 md:grid-cols-3">
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
        <div class="mb-3 text-3xl">📄</div>
        <h3 class="mb-2 font-semibold">CV Analizi</h3>
        <p class="text-sm text-slate-400">PDF'nden yeteneklerini otomatik çıkarır, güçlü yönlerini gösterir.</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
        <div class="mb-3 text-3xl">🗺️</div>
        <h3 class="mb-2 font-semibold">Kişisel Yol Haritası</h3>
        <p class="text-sm text-slate-400">Hedef mesleğe göre haftalık görev planı: ne öğreneceksin, hangi projeyi yapacaksın.</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
        <div class="mb-3 text-3xl">🎯</div>
        <h3 class="mb-2 font-semibold">Akıllı Eşleştirme</h3>
        <p class="text-sm text-slate-400">Yeterince hazır olunca sana uygun iş ilanlarını önerir ve başvurunda yardımcı olur.</p>
    </div>
</section>
@endsection
