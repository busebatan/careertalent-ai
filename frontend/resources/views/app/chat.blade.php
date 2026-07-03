@extends('app.layouts.app')

@section('title', __('panel.chat.title'))

@section('content')
<div class="mx-auto max-w-2xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.chat.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.chat.subtitle') }}</p>
    </header>

    <section class="panel-card border-dashed p-10 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl dark:bg-slate-800">💬</div>
        <h2 class="mb-2 text-lg font-semibold">{{ __('panel.chat.coming_title') }}</h2>
        <p class="panel-muted mx-auto max-w-md text-sm">{{ __('panel.chat.coming_desc') }}</p>
    </section>
</div>
@endsection
