@extends('marketing.layouts.marketing')

@section('title', __('marketing.about.title'))

@section('content')
    @include('marketing.partials.placeholder-page', [
        'titleKey' => 'marketing.about.title',
        'introKey' => 'marketing.about.intro',
    ])
@endsection
