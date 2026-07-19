@php($isAdmin = $portal === 'admin')
@php($isCompany = $portal === 'company')
@php($isRegister = ! $isAdmin && ! $isCompany && $mode === 'register')
@php($action = $isAdmin ? route('admin.login.submit') : ($isCompany ? route('company.login.submit') : ($isRegister ? route('register.submit') : route('login.submit'))))
<section class="auth-form" aria-labelledby="auth-title">
    <div class="form-kicker">
        <span class="portal-dot" aria-hidden="true"></span>
        {{ $isAdmin ? __('marketing.auth.admin_kicker') : ($isCompany ? ($organizationProfile['name'] ?? __('marketing.auth.company_kicker')) : ($isRegister ? __('marketing.auth.register_kicker') : __('marketing.auth.panel_kicker'))) }}
    </div>
    <h1 id="auth-title">{{ $isAdmin ? __('marketing.auth.admin_heading') : ($isCompany ? (isset($organizationProfile) ? __('marketing.auth.organization_heading', ['organization' => $organizationProfile['name']]) : __('marketing.auth.company_heading')) : ($isRegister ? __('marketing.auth.register_heading') : __('marketing.auth.panel_heading'))) }}</h1>
    <p class="form-intro">{{ $isAdmin ? __('marketing.auth.admin_intro') : ($isCompany ? ($organizationProfile['description'] ?? __('marketing.auth.company_intro')) : ($isRegister ? __('marketing.auth.register_intro') : __('marketing.auth.panel_intro'))) }}</p>

    <form class="auth-native-form" data-auth-form action="{{ $action }}" method="post">
        @csrf
        @if ($errors->any())
            <div class="form-alert is-error" role="alert" aria-live="polite">{{ $errors->first() }}</div>
        @endif

        @if ($isRegister)
            <label>
                <span>{{ __('marketing.auth.name') }}</span>
                <input name="name" value="{{ old('name') }}" autocomplete="name" required minlength="2" maxlength="100" @error('name') aria-invalid="true" @enderror>
            </label>
        @endif

        <label>
            <span>{{ __('marketing.auth.email') }}</span>
            <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" @enderror>
        </label>

        <label>
            <span>{{ __('marketing.auth.password') }}</span>
            <span class="password-field">
                <input id="password" name="password" type="password" autocomplete="{{ $isRegister ? 'new-password' : 'current-password' }}" required minlength="{{ $isRegister ? 8 : 1 }}" maxlength="128" @error('password') aria-invalid="true" @enderror>
                <button type="button" data-password-toggle aria-controls="password" aria-label="{{ __('marketing.auth.show_password') }}">{{ __('marketing.auth.show_password') }}</button>
            </span>
        </label>

        @if ($isRegister)
            <label>
                <span>{{ __('marketing.auth.password_confirmation') }}</span>
                <input name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="128">
            </label>
        @endif

        <button class="submit-button" type="submit">
            <span>{{ $isAdmin ? __('marketing.auth.admin_submit') : ($isCompany ? __('marketing.auth.company_submit') : ($isRegister ? __('marketing.auth.submit_register') : __('marketing.auth.panel_submit'))) }}</span>
            <i aria-hidden="true">→</i>
        </button>
    </form>

    @if ($isAdmin)
        <p class="switch-link"><a href="{{ route('login') }}">{{ __('marketing.auth.back_to_panel') }}</a></p>
    @elseif ($isCompany)
        <p class="switch-link"><a href="{{ route('login') }}">{{ __('marketing.auth.back_to_panel') }}</a></p>
    @elseif ($isRegister)
        <p class="switch-link">{{ __('marketing.auth.has_account') }} <a href="{{ route('login') }}">{{ __('marketing.nav.login') }}</a></p>
    @else
        <p class="switch-link">{{ __('marketing.auth.no_account') }} <a href="{{ route('register') }}">{{ __('marketing.nav.register') }}</a></p>
    @endif
</section>
