<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel — CareerTalent AI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 border-r border-slate-800 bg-slate-900 p-6 md:block">
            <a href="{{ route('home') }}" class="mb-8 block text-lg font-bold text-emerald-400">CareerTalent AI</a>
            <nav class="space-y-2 text-sm">
                <a href="{{ route('panel.dashboard') }}" class="block rounded-lg bg-slate-800 px-3 py-2 text-white">Özet</a>
                <span class="block rounded-lg px-3 py-2 text-slate-500">CV (Sprint 1)</span>
                <span class="block rounded-lg px-3 py-2 text-slate-500">Kariyer (Sprint 2)</span>
                <span class="block rounded-lg px-3 py-2 text-slate-500">Yol Haritası (Sprint 2)</span>
                <span class="block rounded-lg px-3 py-2 text-slate-500">Sohbet (Sprint 3)</span>
            </nav>
        </aside>
        <main class="flex-1 p-6 md:p-10">
            @yield('content')
        </main>
    </div>
    @livewireScripts
</body>
</html>
