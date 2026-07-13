<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('marketing.auth.login');
    }

    public function register(): View
    {
        return view('marketing.auth.register');
    }

    public function authenticate(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $api->login($credentials['email'], $credentials['password']);
        if (! $result['ok']) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => $this->authError($result['status']),
            ]);
        }

        $me = $api->me($result['body']['access_token']);
        if (! $me['ok']) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('marketing.auth.service_error'),
            ]);
        }

        $this->startSession($request, $result['body']['access_token'], $me['body']);

        return redirect()->intended(route('panel.dashboard'));
    }

    public function store(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);

        $registered = $api->register([
            'full_name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        if (! $registered['ok']) {
            return back()->withInput($request->only('name', 'email'))->withErrors([
                'email' => $registered['status'] === 409
                    ? __('marketing.auth.email_registered')
                    : __('marketing.auth.service_error'),
            ]);
        }

        $loggedIn = $api->login($data['email'], $data['password']);
        if (! $loggedIn['ok']) {
            return redirect()->route('login')->withErrors([
                'email' => __('marketing.auth.login_after_register_error'),
            ]);
        }

        $this->startSession($request, $loggedIn['body']['access_token'], $registered['body']);

        return redirect()->route('panel.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function startSession(Request $request, string $accessToken, array $user): void
    {
        $request->session()->regenerate();
        $request->session()->put('auth', [
            'access_token' => $accessToken,
            'user' => $user,
        ]);
    }

    private function authError(?int $status): string
    {
        return $status === 401
            ? __('marketing.auth.invalid_credentials')
            : __('marketing.auth.service_error');
    }
}
