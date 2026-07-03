@extends('marketing.layouts.marketing')

@section('title', __('marketing.pricing.title'))

@section('content')
    @include('marketing.partials.placeholder-page', [
        'titleKey' => 'marketing.pricing.title',
        'introKey' => 'marketing.pricing.intro',
    ])
@endsection
