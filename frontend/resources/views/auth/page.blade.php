@extends('auth.layout')

@section('title', $mode === 'register' ? __('marketing.auth.register_title') : ($portal === 'admin' ? __('marketing.auth.admin_heading') : __('marketing.auth.login_title')))

@section('form')
    @include('auth.partials.form')
@endsection
