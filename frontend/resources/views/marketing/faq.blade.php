@extends('marketing.layouts.marketing')

@section('title', __('marketing.faq.title'))

@section('content')
    @include('marketing.partials.placeholder-page', [
        'titleKey' => 'marketing.faq.title',
        'introKey' => 'marketing.faq.intro',
    ])
@endsection
