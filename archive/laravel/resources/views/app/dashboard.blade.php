@extends('app.layouts.app')

@section('content')
<h1 class="mb-2 text-2xl font-bold">Hoş geldin 👋</h1>
<p class="mb-8 text-slate-400">Kariyer panelin — Sprint 1'de CV yükleme burada açılacak.</p>

<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
        <p class="text-sm text-slate-500">Hazırlık</p>
        <p class="text-3xl font-bold text-emerald-400">%0</p>
        <p class="mt-1 text-xs text-slate-500">CV yüklendikten sonra güncellenir</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
        <p class="text-sm text-slate-500">Hedef Meslek</p>
        <p class="text-lg font-semibold">Henüz seçilmedi</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
        <p class="text-sm text-slate-500">Bu Hafta</p>
        <p class="text-lg font-semibold">0 görev</p>
    </div>
</div>

<div class="mt-8 rounded-2xl border border-dashed border-slate-700 p-8 text-center text-slate-500">
    📄 CV yükleme alanı — Sprint 1 (Döne: backend, Yiğit: veri parse)
</div>
@endsection
