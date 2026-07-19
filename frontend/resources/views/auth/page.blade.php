@extends('auth.layout')

@section('title', isset($organizationProfile) ? $organizationProfile['name'].' · '.__('marketing.auth.company_heading') : ($mode === 'register' ? __('marketing.auth.register_title') : ($portal === 'admin' ? __('marketing.auth.admin_heading') : ($portal === 'company' ? __('marketing.auth.company_heading') : __('marketing.auth.login_title')))))

@section('form')
    @include('auth.partials.form')
@endsection
