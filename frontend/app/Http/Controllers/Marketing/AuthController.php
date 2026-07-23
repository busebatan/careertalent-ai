<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;
use App\Support\PortalAuthSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.page', ['portal' => 'panel', 'mode' => 'login']);
    }

    public function register(): View
    {
        return view('auth.page', ['portal' => 'panel', 'mode' => 'register']);
    }

    public function adminLogin(): View
    {
        return view('auth.page', ['portal' => 'admin', 'mode' => 'login']);
    }

    public function companyLogin(): View
    {
        return view('auth.page', ['portal' => 'company', 'mode' => 'login']);
    }

    public function authenticate(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        return $this->attemptLogin($request, $api, false);
    }

    public function authenticateAdmin(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        return $this->attemptLogin($request, $api, true);
    }

    public function authenticateCompany(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        return $this->attemptCompanyLogin($request, $api);
    }

    private function attemptCompanyLogin(
        Request $request,
        CareerTalentApiClient $api,
    ): RedirectResponse {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $result = $api->login($credentials['email'], $credentials['password']);
        if (! $result['ok']) {
            return back()->withInput($request->only('email'))->withErrors(['email' => $this->authError($result['status'])]);
        }
        $me = $api->me($result['body']['access_token']);
        if (! $me['ok'] || ($me['body']['role'] ?? null) !== 'company' || ($me['body']['is_admin'] ?? false) === true) {
            $request->session()->forget(PortalAuthSession::COMPANY);

            return back()->withInput($request->only('email'))->withErrors(['email' => __('marketing.auth.company_required')]);
        }
        $context = $api->companyContext($result['body']['access_token']);
        $memberships = $context['ok'] && is_array($context['body']['memberships'] ?? null)
            ? $context['body']['memberships']
            : [];
        if ($memberships === []) {
            $request->session()->forget(PortalAuthSession::COMPANY);

            return back()->withInput($request->only('email'))->withErrors(['email' => __('marketing.auth.company_membership_required')]);
        }
        $intendedUrl = $request->session()->pull('url.intended');
        $intendedPath = is_string($intendedUrl)
            ? trim((string) parse_url($intendedUrl, PHP_URL_PATH), '/')
            : '';
        $intendedSlug = explode('/', $intendedPath, 2)[0] ?? '';
        $intendedMembership = collect($memberships)->first(
            fn (array $item): bool => ($item['organization_slug'] ?? null) === $intendedSlug
        );
        $membership = is_array($intendedMembership) ? $intendedMembership : $memberships[0];
        if (($request->session()->get('auth.user.role') ?? null) === 'company') {
            $request->session()->forget(PortalAuthSession::DEFAULT);
        }
        $this->startSession($request, $result['body']['access_token'], $me['body'], PortalAuthSession::COMPANY);
        $request->session()->put('company.memberships', $memberships);
        $request->session()->put('company.organization_id', $membership['organization_id']);

        if (is_array($intendedMembership) && $intendedPath !== '') {
            return redirect()->to('/'.$intendedPath);
        }

        return redirect()->route('company.positions', [
            'organizationSlug' => $membership['organization_slug'],
        ]);
    }

    public function companyInvitation(string $token, CareerTalentApiClient $api): View
    {
        $result = $api->companyInvitation($token);

        return view('auth.company-invitation', [
            'portal' => 'company',
            'mode' => 'invite',
            'token' => $token,
            'invitation' => $result['ok'] ? $result['body'] : null,
        ]);
    }

    public function acceptCompanyInvitation(Request $request, string $token, CareerTalentApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ]);
        $result = $api->acceptCompanyInvitation($token, $payload);

        return $result['ok']
            ? redirect()->route('company.login')->with('status', __('marketing.auth.company_invite_accepted'))
            : back()->withInput($request->only('full_name'))->withErrors(['email' => $result['error']]);
    }

    private function attemptLogin(Request $request, CareerTalentApiClient $api, bool $adminPortal): RedirectResponse
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

        $isAdmin = ($me['body']['is_admin'] ?? false) === true;
        $isCompany = ($me['body']['role'] ?? null) === 'company';

        if ($adminPortal && ! $isAdmin) {
            $request->session()->forget('auth');

            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('marketing.auth.admin_required'),
            ]);
        }

        if (! $adminPortal && $isCompany) {
            $request->session()->forget('auth');

            return back()->withInput($request->only('email'))->withErrors([
                'email' => __('marketing.auth.candidate_required'),
            ]);
        }

        $this->startSession($request, $result['body']['access_token'], $me['body']);

        if ($isAdmin) {
            $request->session()->forget('url.intended');

            return redirect()->route(($me['body']['must_change_password'] ?? false) === true ? 'admin.profile' : 'admin.dashboard');
        }

        $intendedUrl = $this->candidateIntendedUrl($request);

        return $intendedUrl !== null
            ? redirect()->to($intendedUrl)
            : redirect()->route('panel.dashboard');
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

        $intendedUrl = $this->candidateIntendedUrl($request);

        return $intendedUrl !== null
            ? redirect()->to($intendedUrl)
            : redirect()->route('panel.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(PortalAuthSession::DEFAULT);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function logoutCompany(Request $request): RedirectResponse
    {
        $request->session()->forget([PortalAuthSession::COMPANY, 'company']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('company.login');
    }

    private function startSession(Request $request, string $accessToken, array $user, string $sessionKey = PortalAuthSession::DEFAULT): void
    {
        $request->session()->regenerate();
        $request->session()->put($sessionKey, [
            'access_token' => $accessToken,
            'user' => $user,
        ]);
        $request->session()->put(
            'panel_locale',
            in_array($user['preferred_locale'] ?? null, ['tr', 'en'], true)
                ? $user['preferred_locale']
                : 'tr',
        );
    }

    private function candidateIntendedUrl(Request $request): ?string
    {
        $intendedUrl = $request->session()->pull('url.intended');
        if (! is_string($intendedUrl) || $intendedUrl === '') {
            return null;
        }

        $intendedHost = parse_url($intendedUrl, PHP_URL_HOST);
        if (is_string($intendedHost)
            && ! hash_equals(strtolower($request->getHost()), strtolower($intendedHost))) {
            return null;
        }

        $intendedPath = parse_url($intendedUrl, PHP_URL_PATH);
        if (! is_string($intendedPath) || $intendedPath === '') {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($intendedPath, 'GET'));
        } catch (MethodNotAllowedHttpException|NotFoundHttpException) {
            return null;
        }

        $routeName = $route->getName();
        if (! is_string($routeName)
            || (! str_starts_with($routeName, 'panel.') && $routeName !== 'public.jobs.start')) {
            return null;
        }

        $query = parse_url($intendedUrl, PHP_URL_QUERY);
        $fragment = parse_url($intendedUrl, PHP_URL_FRAGMENT);

        return $intendedPath
            .(is_string($query) && $query !== '' ? '?'.$query : '')
            .(is_string($fragment) && $fragment !== '' ? '#'.$fragment : '');
    }

    private function authError(?int $status): string
    {
        return $status === 401
            ? __('marketing.auth.invalid_credentials')
            : __('marketing.auth.service_error');
    }
}
