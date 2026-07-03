@extends('marketing.layouts.marketing')

@section('title', __('marketing.contact.title'))

@section('content')
    @include('marketing.partials.placeholder-page', [
        'titleKey' => 'marketing.contact.title',
        'introKey' => 'marketing.contact.intro',
    ])
@endsection
