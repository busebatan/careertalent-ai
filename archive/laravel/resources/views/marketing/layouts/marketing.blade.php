<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CareerTalent AI') — Kariyer Yol Arkadaşın</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <header class="border-b border-slate-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="text-xl font-bold text-emerald-400">CareerTalent AI</a>
            <nav class="hidden gap-6 text-sm text-slate-300 md:flex">
                <a href="{{ route('features') }}" class="hover:text-white">Özellikler</a>
                <a href="{{ route('how-it-works') }}" class="hover:text-white">Nasıl Çalışır?</a>
                <a href="{{ route('bootcamp') }}" class="hover:text-white">Bootcamp</a>
            </nav>
            <div class="flex gap-3">
                <a href="{{ route('panel.dashboard') }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400">
                    Panele Git
                </a>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-slate-800 py-8 text-center text-sm text-slate-500">
        YZTA Bootcamp 2026 — Grup 92
    </footer>
</body>
</html>
