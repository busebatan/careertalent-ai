<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('marketing.brand')) — {{ __('marketing.meta_suffix') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 font-sans text-slate-100 antialiased">
    @include('marketing.partials.header')

    <main>
        @yield('content')
    </main>

    <footer class="mt-20 border-t border-slate-800 py-10 text-center text-sm text-slate-500">
        {{ __('marketing.footer') }}
    </footer>
    @stack('scripts')
</body>
</html>
