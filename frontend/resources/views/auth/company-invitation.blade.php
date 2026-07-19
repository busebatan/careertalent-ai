@extends('auth.layout')
@section('title', __('marketing.auth.company_invite_title'))
@section('form')
<section class="auth-form" aria-labelledby="auth-title"><div class="form-kicker"><span class="portal-dot"></span>{{ __('marketing.auth.company_kicker') }}</div>
<h1 id="auth-title">{{ __('marketing.auth.company_invite_title') }}</h1>
@if($invitation)<p class="form-intro">{{ __('marketing.auth.company_invite_intro',['organization'=>$invitation['organization_name'],'email'=>$invitation['email']]) }}</p>
<form class="auth-native-form" method="post" action="{{ route('company.invitation.accept',$token) }}">@csrf
@if($errors->any())<div class="form-alert is-error">{{ $errors->first() }}</div>@endif
<label><span>{{ __('marketing.auth.name') }}</span><input name="full_name" value="{{ old('full_name') }}" required></label>
<label><span>{{ __('marketing.auth.password') }}</span><input type="password" name="password" required minlength="8"></label>
<label><span>{{ __('marketing.auth.password_confirmation') }}</span><input type="password" name="password_confirmation" required minlength="8"></label>
<button class="submit-button" type="submit"><span>{{ __('marketing.auth.company_invite_submit') }}</span></button></form>
@else<p class="form-alert is-error">{{ __('marketing.auth.company_invite_invalid') }}</p>@endif</section>
@endsection
