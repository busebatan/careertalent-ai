<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#080b18">
    <title>@yield('title') — {{ __('marketing.brand') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,500..800&family=IBM+Plex+Mono:wght@500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
:root{--ink:#080b18;--panel:#10172b;--line:#26314d;--text:#f7f8ff;--muted:#9ca8c4;--green:#20dca5;--amber:#ffbd72;--accent:{{ $portal==='admin'?'var(--amber)':'var(--green)' }}}*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:var(--ink);color:var(--text);font-family:Manrope,sans-serif}body{min-height:100vh;background-image:radial-gradient(circle at 18% 15%,rgba(32,220,165,.08),transparent 34%),linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:auto,42px 42px,42px 42px}.shell{min-height:100vh;display:grid;grid-template-rows:auto 1fr auto}.topbar{height:82px;display:flex;align-items:center;justify-content:space-between;width:min(1240px,calc(100% - 48px));margin:auto}.brand{display:inline-flex;align-items:center;gap:11px;color:#fff;text-decoration:none;font-family:'Bricolage Grotesque';font-weight:750;letter-spacing:-.03em}.brand b{color:var(--accent)}.brand-mark{width:32px;height:32px;border:1px solid color-mix(in srgb,var(--accent) 38%,transparent);border-radius:11px;position:relative;background:color-mix(in srgb,var(--accent) 12%,transparent)}.brand-mark i{position:absolute;width:4px;height:4px;border-radius:50%;background:var(--accent)}.brand-mark i:nth-child(1){left:8px;top:8px}.brand-mark i:nth-child(2){right:7px;top:13px}.brand-mark i:nth-child(3){right:11px;bottom:7px}.brand-mark:before,.brand-mark:after{content:"";position:absolute;height:1px;width:11px;background:#b9c4d8;transform-origin:left}.brand-mark:before{left:11px;top:10px;transform:rotate(25deg)}.brand-mark:after{left:11px;top:16px;transform:rotate(50deg)}.top-actions{display:flex;align-items:center;gap:18px;font:600 12px/1 'IBM Plex Mono';color:var(--muted)}.top-actions a{color:#d7dcef;text-decoration:none}.portal-badge{border:1px solid color-mix(in srgb,var(--accent) 45%,transparent);border-radius:999px;padding:8px 11px;color:var(--accent)}main{width:min(1240px,calc(100% - 48px));margin:auto;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(360px,.75fr);gap:clamp(56px,8vw,126px);align-items:center;padding:38px 0 70px}.admin main{grid-template-columns:minmax(360px,.75fr) minmax(0,1.25fr)}.admin .visual{order:2}.admin .auth-form{order:1}.visual{max-width:700px}.eyebrow{font:600 11px/1 'IBM Plex Mono';text-transform:uppercase;letter-spacing:.16em;color:var(--accent);margin-bottom:24px}.visual h2{font:700 clamp(46px,5.5vw,78px)/.96 'Bricolage Grotesque';letter-spacing:-.055em;margin:0;max-width:720px}.visual h2 em{font-style:normal;color:var(--accent)}.lead{font-size:16px;line-height:1.75;color:#b3bdd5;max-width:550px;margin:26px 0 34px}.route-map{padding-block:12px;position:relative;height:215px;max-width:650px;border-top:1px solid var(--line);border-bottom:1px solid var(--line);overflow:hidden}.route-map svg{position:absolute;inset:18px 12px 12px;width:calc(100% - 24px);height:160px}.route-path{fill:none;stroke:var(--accent);stroke-width:2;stroke-dasharray:5 7}.route-node circle{fill:var(--ink);stroke:var(--accent);stroke-width:2}.route-node text{fill:#dbe2f3;font:600 10px 'IBM Plex Mono'}.route-node .node-meta{fill:#7f8ba8;font-size:8px}.admin-cues{display:grid;gap:0;border-top:1px solid var(--line);margin-top:38px}.admin-cues div{display:grid;grid-template-columns:42px 1fr;gap:16px;padding:18px 0;border-bottom:1px solid var(--line)}.admin-cues span{font:600 11px 'IBM Plex Mono';color:var(--accent)}.admin-cues strong{font:600 14px Manrope}.admin-cues p{margin:5px 0 0;color:var(--muted);font-size:12px}.auth-form{position:relative;padding:36px;border:1px solid #27324e;border-radius:22px;background:linear-gradient(155deg,rgba(17,24,45,.96),rgba(11,16,31,.96));box-shadow:0 30px 90px rgba(0,0,0,.34)}.auth-form:before{content:"";position:absolute;top:-1px;left:28px;width:86px;height:2px;background:var(--accent)}.form-kicker{display:flex;align-items:center;gap:8px;margin-bottom:17px;color:var(--accent);font:600 10px 'IBM Plex Mono';letter-spacing:.14em;text-transform:uppercase}.portal-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);box-shadow:0 0 14px var(--accent)}.auth-form h1{font:700 32px/1.05 'Bricolage Grotesque';letter-spacing:-.04em;margin:0 0 12px}.form-intro{color:var(--muted);font-size:13px;line-height:1.6;margin:0 0 28px}.auth-native-form{display:grid;gap:17px}.auth-native-form label>span:first-child{display:block;font-size:12px;font-weight:600;color:#dce2ef;margin:0 0 7px}.auth-native-form input{width:100%;height:48px;border:1px solid #34405d;border-radius:11px;background:#090e1d;color:#fff;padding:0 14px;font:500 14px Manrope;outline:none;transition:border .16s,box-shadow .16s}.auth-native-form input:focus{border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 18%,transparent)}.password-field{display:flex!important;position:relative;margin:0!important}.password-field button{position:absolute;right:7px;top:7px;height:34px;border:0;background:transparent;color:var(--accent);font:600 10px 'IBM Plex Mono';cursor:pointer}.submit-button{height:50px;margin-top:5px;border:0;border-radius:11px;background:var(--accent);color:#07120f;display:flex;align-items:center;justify-content:space-between;padding:0 18px;font:700 13px Manrope;cursor:pointer}.admin .submit-button{color:#211606}.submit-button:disabled{opacity:.65;cursor:wait}.form-alert{padding:11px 12px;border-radius:9px;font-size:12px;line-height:1.5}.form-alert.is-success{background:rgba(32,220,165,.1);color:#8bf1d0}.form-alert.is-error{background:rgba(248,113,113,.1);color:#fda4af}.switch-link{text-align:center;margin:22px 0 0;color:#8592ae;font-size:12px}.switch-link a{color:var(--accent);font-weight:700;text-decoration:none}.legal{width:min(1240px,calc(100% - 48px));margin:auto;padding:20px 0 26px;border-top:1px solid rgba(151,164,200,.12);display:flex;justify-content:space-between;color:#6f7b98;font:500 10px 'IBM Plex Mono'}.legal a{color:#aeb8ce;text-decoration:none}:focus-visible{outline:3px solid color-mix(in srgb,var(--accent) 48%,transparent);outline-offset:3px}@media(max-width:860px){.topbar{height:68px;width:min(100% - 30px,620px)}main,.admin main{width:min(100% - 30px,620px);grid-template-columns:1fr;gap:46px;padding:24px 0 50px}.visual,.admin .visual{order:2}.auth-form,.admin .auth-form{order:1;padding:28px 22px}.visual h2{font-size:44px}.route-map{padding-block:12px;height:190px}.legal{width:min(100% - 30px,620px);gap:12px;flex-direction:column}}@media(prefers-reduced-motion:no-preference){.auth-form{animation:rise .55s ease both}.route-path{animation:dash 12s linear infinite}@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}@keyframes dash{to{stroke-dashoffset:-100}}}
.auth-skip-link{position:fixed;left:16px;top:-60px;z-index:50;padding:10px 14px;border-radius:8px;background:var(--accent);color:#07120f;font:700 12px Manrope;text-decoration:none}.auth-skip-link:focus{top:16px}.auth-native-form input[aria-invalid=true]{border-color:#f87171}.auth-native-form button:focus-visible,.topbar a:focus-visible,.legal a:focus-visible{outline:3px solid color-mix(in srgb,var(--accent) 48%,transparent);outline-offset:3px}.organization-brand{display:flex;align-items:center;gap:14px;margin-bottom:24px}.organization-logo{width:62px;height:62px;object-fit:contain;border-radius:16px;background:#fff;padding:8px}.organization-monogram{display:grid;place-items:center;width:62px;height:62px;border:1px solid color-mix(in srgb,var(--accent) 42%,transparent);border-radius:16px;background:color-mix(in srgb,var(--accent) 12%,transparent);color:var(--accent);font:700 20px 'Bricolage Grotesque'}.organization-website{display:inline-flex;margin-top:18px;color:var(--accent);font:600 12px 'IBM Plex Mono';text-decoration:none}
    </style>
</head>
<body class="{{ $portal === 'admin' ? 'admin auth-shell--admin' : 'panel auth-shell--panel' }}" data-auth-portal="{{ $portal }}" data-auth-mode="{{ $mode }}">
<a class="auth-skip-link" href="#main-content">{{ __('marketing.skip_to_content') }}</a>
<div class="shell">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ __('marketing.brand') }}">
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
            <span>{{ $organizationProfile['name'] ?? 'CareerTalent' }} @unless(isset($organizationProfile))<b>AI</b>@endunless</span>
        </a>
        <div class="top-actions">
            <span class="portal-badge">{{ $portal === 'admin' ? 'ADMIN' : ($portal === 'company' ? 'COMPANY' : 'PANEL') }}</span>
            @if ($portal === 'panel' && $mode === 'login')
                <a href="{{ route('company.login') }}">{{ __('marketing.auth.company_login_link') }}</a>
            @endif
            <a href="{{ route('home') }}">{{ __('marketing.auth.back_to_site') }}</a>
        </div>
    </header>

    <main id="main-content">
        <section class="visual" aria-labelledby="auth-visual-title">
            @if (isset($organizationProfile))
                <div class="organization-brand">
                    @if (! empty($organizationProfile['logo_url']))
                        <img class="organization-logo" src="{{ $organizationProfile['logo_url'] }}" alt="{{ __('marketing.auth.organization_logo', ['organization' => $organizationProfile['name']]) }}" referrerpolicy="no-referrer">
                    @else
                        <span class="organization-monogram" aria-hidden="true">{{ mb_strtoupper(mb_substr($organizationProfile['name'], 0, 2)) }}</span>
                    @endif
                </div>
            @endif
            <p class="eyebrow">{{ $portal === 'admin' ? __('marketing.auth.admin_boundary') : ($portal === 'company' ? __('marketing.auth.company_boundary') : __('marketing.auth.career_route')) }}</p>
            <h2 id="auth-visual-title">
                @if ($portal === 'admin')
                    {{ __('marketing.auth.admin_visual_heading') }}
                @elseif ($portal === 'company')
                    {{ $organizationProfile['name'] ?? __('marketing.auth.company_visual_heading') }}
                @else
                    {!! str_replace('yola dönüşür.', '<em>yola dönüşür.</em>', e(__('marketing.auth.career_heading'))) !!}
                @endif
            </h2>
            <p class="lead">{{ $portal === 'admin' ? __('marketing.auth.admin_visual_lead') : ($portal === 'company' ? ($organizationProfile['description'] ?? __('marketing.auth.company_visual_lead')) : __('marketing.auth.career_lead')) }}</p>
            @if (isset($organizationProfile) && ! empty($organizationProfile['website']))
                <a class="organization-website" href="{{ $organizationProfile['website'] }}" target="_blank" rel="noopener noreferrer">{{ __('marketing.auth.organization_website') }} ↗</a>
            @endif

            @if ($portal === 'admin')
                <div class="admin-cues">
                    <div><span>ROL</span><section><strong>Rol doğrulaması</strong><p>Yalnız yönetici yetkisine sahip hesaplar.</p></section></div>
                    <div><span>İZ</span><section><strong>İzlenebilir erişim</strong><p>Yönetim bağlamı kullanıcı panelinden ayrı tutulur.</p></section></div>
                    <div><span>AYRI</span><section><strong>Ayrı güvenlik yüzeyi</strong><p>Amber renk ve sol form konumuyla ayırt edilir.</p></section></div>
                </div>
            @elseif ($portal === 'company')
                <div class="admin-cues">
                    <div><span>KUR</span><section><strong>{{ __('marketing.auth.company_cue_org') }}</strong><p>{{ __('marketing.auth.company_cue_org_text') }}</p></section></div>
                    <div><span>ROL</span><section><strong>{{ __('marketing.auth.company_cue_role') }}</strong><p>{{ __('marketing.auth.company_cue_role_text') }}</p></section></div>
                    <div><span>İZ</span><section><strong>{{ __('marketing.auth.company_cue_audit') }}</strong><p>{{ __('marketing.auth.company_cue_audit_text') }}</p></section></div>
                </div>
            @else
                <div class="route-map" aria-label="CV'den kariyer rotasına ilerleme">
                    <svg viewBox="0 0 650 160" role="img" aria-label="CV YÜKLENDİ, YETENEK RADARI, HEDEF MESLEK, GÖREVLER">
                        <path class="route-path" d="M20 120 C130 120 105 35 230 48 S370 145 458 88 S550 24 630 32"/>
                        <g class="route-node" aria-label="CV YÜKLENDİ"><circle cx="22" cy="120" r="8"/><text x="20" y="99">CV</text><text class="node-meta" x="20" y="146">YÜKLENDİ</text></g>
                        <g class="route-node" aria-label="YETENEK RADARI"><circle cx="230" cy="48" r="8"/><text x="201" y="25">RADAR</text><text class="node-meta" x="194" y="78">YETENEKLER</text></g>
                        <g class="route-node" aria-label="HEDEF MESLEK"><circle cx="458" cy="88" r="8"/><text x="425" y="65">HEDEF</text><text class="node-meta" x="425" y="116">MESLEK</text></g>
                        <g class="route-node" aria-label="GÖREVLER"><circle cx="630" cy="32" r="8"/><text x="580" y="16">İLERLEME</text><text class="node-meta" x="580" y="58">GÖREVLER</text></g>
                    </svg>
                </div>
            @endif
        </section>

        @yield('form')
    </main>

    <footer class="legal">
        <span>{{ __('marketing.brand') }}</span>
        <span><a href="{{ route('contact') }}">{{ __('marketing.nav.contact') }}</a></span>
    </footer>
</div>
<script>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.textContent = visible ? @json(__('marketing.auth.show_password')) : @json(__('marketing.auth.hide_password'));
        button.setAttribute('aria-label', button.textContent);
    });
});
document.querySelectorAll('[data-auth-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        if (!form.checkValidity()) return;
        const button = form.querySelector('.submit-button');
        button.disabled = true;
        button.querySelector('span').textContent = @json(app()->getLocale() === 'tr' ? 'Kontrol ediliyor…' : 'Checking…');
    });
});
</script>
</body>
</html>
