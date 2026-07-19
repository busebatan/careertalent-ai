@extends('company.layouts.app')
@section('title', __('company.profile.title'))
@section('content')
<div class="mx-auto max-w-4xl"><h1 class="text-3xl font-bold">{{ __('company.profile.title') }}</h1><p class="panel-muted mt-2">{{ __('company.profile.subtitle') }}</p>
<form class="panel-card mt-7 grid gap-5 p-6 md:grid-cols-2" method="post" action="{{ route('company.profile.update') }}">@csrf @method('PATCH')
<label class="text-sm md:col-span-2">{{ __('company.profile.name') }}<input class="panel-input-block mt-2" name="name" value="{{ old('name',$companyMembership['organization_name']) }}" required></label>
<label class="text-sm">{{ __('company.profile.billing_email') }}<input class="panel-input-block mt-2" type="email" name="billing_email" value="{{ old('billing_email',$companyMembership['billing_email']) }}" required></label>
<label class="text-sm">{{ __('company.profile.website') }}<input class="panel-input-block mt-2" type="url" name="website" value="{{ old('website',$companyMembership['website']) }}"></label>
<div class="md:col-span-2"><button class="company-btn-primary">{{ __('company.profile.save') }}</button></div></form></div>
@endsection
