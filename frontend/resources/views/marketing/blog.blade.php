@extends('marketing.layouts.marketing')

@section('title', __('marketing.blog.title'))

@section('content')
    @include('marketing.partials.placeholder-page', [
        'titleKey' => 'marketing.blog.title',
        'introKey' => 'marketing.blog.intro',
    ])
@endsection
