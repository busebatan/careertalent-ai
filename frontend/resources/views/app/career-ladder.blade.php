@extends('app.layouts.app')

@section('title', __('panel.career_ladder.title'))

@section('content')
<div class="mx-auto max-w-5xl">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.career_ladder.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.career_ladder.subtitle') }}</p>
    </header>

    @include('app.partials.career-ladder', ['hideSectionHeader' => true])
</div>
@endsection
