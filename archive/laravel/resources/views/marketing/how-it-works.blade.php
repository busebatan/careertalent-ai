@extends('marketing.layouts.marketing')

@section('title', 'Nasıl Çalışır?')

@section('content')
<section class="mx-auto max-w-4xl px-4 py-16">
    <h1 class="mb-12 text-3xl font-bold text-center">3 Adımda Kariyerin</h1>
    <div class="space-y-8">
        <div class="flex gap-6">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-xl font-bold text-slate-950">1</div>
            <div>
                <h3 class="mb-2 text-xl font-semibold">CV'ni Yükle</h3>
                <p class="text-slate-400">Sistem yeteneklerini çıkarır, bugünkü durumunu haritalar.</p>
            </div>
        </div>
        <div class="flex gap-6">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-xl font-bold text-slate-950">2</div>
            <div>
                <h3 class="mb-2 text-xl font-semibold">Yol Haritanı Al</h3>
                <p class="text-slate-400">Hedef mesleğe göre haftalık plan: kurslar, projeler, eksikler.</p>
            </div>
        </div>
        <div class="flex gap-6">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-xl font-bold text-slate-950">3</div>
            <div>
                <h3 class="mb-2 text-xl font-semibold">Hazır Ol, Eşleş</h3>
                <p class="text-slate-400">Hazırlık yüzden yeterli olunca uygun iş ilanları önerilir.</p>
            </div>
        </div>
    </div>
</section>
@endsection
