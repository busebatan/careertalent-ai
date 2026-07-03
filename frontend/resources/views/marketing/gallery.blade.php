@extends('marketing.layouts.marketing')

@section('title', __('marketing.gallery.title'))

@section('content')
    @include('marketing.partials.placeholder-page', [
        'titleKey' => 'marketing.gallery.title',
        'introKey' => 'marketing.gallery.intro',
    ])
@endsection
