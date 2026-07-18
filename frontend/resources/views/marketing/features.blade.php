@extends('marketing.layouts.marketing')

@section('title', __('features.title'))

@section('content')

<style>
  .ct-serif { font-family: var(--marketing-display); }
  .ct-mono { font-family: var(--marketing-mono); }

  @keyframes ct-fade-up {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .ct-reveal { opacity: 0; }
  .ct-reveal.is-visible { animation: ct-fade-up .7s ease forwards; }

  .tab-content { display: none; opacity: 0; }
  .tab-content.active { display: grid; animation: ct-fade-up .5s ease forwards; }

  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

  @keyframes ct-pulse-ring {
    0% { box-shadow: 0 0 0 0 rgba(0, 201, 141, .45); }
    70% { box-shadow: 0 0 0 22px rgba(0, 201, 141, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 201, 141, 0); }
  }
  .ct-pulse { animation: ct-pulse-ring 2.4s infinite; border-radius: 11px; }

  @keyframes ct-dot {
    0%, 60%, 100% { opacity: .25; transform: translateY(0); }
    30% { opacity: 1; transform: translateY(-3px); }
  }
  .ct-dot { animation: ct-dot 1.2s infinite ease-in-out; }
  .ct-dot:nth-child(2) { animation-delay: .15s; }
  .ct-dot:nth-child(3) { animation-delay: .3s; }

  .ct-rail-node { transition: border-color .3s ease, background-color .3s ease, box-shadow .3s ease; }
  .tab-btn.is-active .ct-rail-node { border-color: var(--marketing-green); background: rgba(0, 201, 141, .12); box-shadow: 0 0 0 4px rgba(0, 201, 141, .12); }
  .tab-btn.is-active .ct-rail-num { color: var(--marketing-green); }
  .tab-btn.is-active .ct-tab-label { color: #fff; }
  .ct-tab-label, .ct-rail-num { transition: color .3s ease; }

  .ct-tab-underline { color: var(--marketing-cloud); border-bottom-color: transparent; transition: color .3s ease, border-color .3s ease; }
  .ct-tab-underline.is-active { color: #fff; border-bottom-color: var(--marketing-green); }

  .ct-faq-a { max-height: 0; overflow: hidden; transition: max-height .4s ease; }
  .ct-faq-icon { transition: transform .3s ease; }
  .ct-faq-item.is-open .ct-faq-icon { transform: rotate(45deg); }

  /* Component-level additions (theme tokens unchanged) */
  .ct-glow-card { transition: border-color .3s ease, transform .3s ease, box-shadow .3s ease; }
  .ct-glow-card:hover { border-color: rgba(0, 201, 141, .35); box-shadow: 0 24px 60px -24px rgba(0, 201, 141, .25); }
  .ct-chat-panel { display: none; }
  .ct-chat-panel.active { display: flex; animation: ct-fade-up .4s ease forwards; }
  .ct-focus:focus-visible { outline: none; box-shadow: 0 0 0 2px var(--marketing-ink), 0 0 0 4px var(--marketing-green); }

  @media (prefers-reduced-motion: reduce) {
    .ct-reveal, .ct-pulse, .ct-dot, .tab-content.active, .ct-chat-panel.active { animation: none !important; opacity: 1 !important; }
  }
</style>

{{-- 1. HERO --}}
<section class="relative overflow-hidden px-6 pt-28 pb-16 text-center lg:px-8">
  <div class="pointer-events-none absolute left-1/2 top-0 h-[420px] w-[720px] -translate-x-1/2 rounded-full blur-[120px]" style="background: rgba(0,201,141,.10);"></div>

  <div class="ct-reveal relative mx-auto max-w-2xl">
    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/10 px-3 py-1">
      <span class="h-1.5 w-1.5 rounded-full" style="background: var(--marketing-green);"></span>
      <span class="ct-mono text-[10px] uppercase tracking-[0.2em]" style="color: var(--marketing-cloud);">{{ __('features.eyebrow') }}</span>
    </div>

    <h1 class="ct-serif text-4xl font-semibold leading-[1.15] text-white sm:text-5xl">{{ __('features.title') }}</h1>
    <p class="mx-auto mt-5 max-w-lg text-base leading-relaxed" style="color: var(--marketing-cloud);">{{ __('features.intro') }}</p>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
      <a href="{{ route('register') }}"
         class="ct-focus rounded-full px-6 py-3 text-sm font-semibold transition-all duration-300 hover:-translate-y-0.5"
         style="background: var(--marketing-green); color: var(--marketing-ink);">
        {{ __('marketing.home.cta_register') }}
      </a>
      <a href="#ai-chat"
         class="ct-focus rounded-full border border-white/12 px-6 py-3 text-sm font-medium text-white transition-all duration-300 hover:border-white/25 hover:bg-white/[.03]">
        {{ __('features.cta_secondary') }}
      </a>
    </div>

    <div class="ct-mono mt-8 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[11px] uppercase tracking-[0.1em]" style="color: var(--marketing-cloud);">
      <a href="#toolkit" class="transition-colors hover:text-white">{{ __('features.section_nav.toolkit') }}</a>
      <span class="opacity-30">/</span>
      <a href="#ai-chat" class="transition-colors hover:text-white">{{ __('features.section_nav.chat') }}</a>
      <span class="opacity-30">/</span>
      <a href="#more-tools" class="transition-colors hover:text-white">{{ __('features.section_nav.more') }}</a>
      <span class="opacity-30">/</span>
      <a href="#faq" class="transition-colors hover:text-white">{{ __('features.section_nav.faq') }}</a>
    </div>
  </div>
</section>

{{-- 2. CORE TOOLKIT / FLAGSHIP RAIL --}}
<section id="toolkit" class="ct-reveal relative mx-auto max-w-7xl px-6 py-20 lg:px-8">
  <div class="pointer-events-none absolute right-0 top-1/3 h-[360px] w-[500px] rounded-full blur-[110px]" style="background: rgba(0,201,141,.05);"></div>

  <div class="relative mx-auto mb-12 max-w-2xl text-center">
    <span class="ct-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ __('features.toolkit.eyebrow') }}</span>
    <h2 class="ct-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ __('features.toolkit.title') }}</h2>
    <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ __('features.toolkit.desc') }}</p>
  </div>

  @php
    // Ordered as the real product journey: analyze -> build -> plan -> prove -> apply.
    $flagships = ['cv_merkezi', 'cv_olusturucu', 'kariyer_rotam', 'yetenek_pasaportu', 'is_firsatlari'];
  @endphp

  <div data-tab-group="toolkit" class="relative grid gap-8 lg:grid-cols-[260px_1fr] lg:gap-10">
    <div class="relative">
      <div class="pointer-events-none absolute left-5 top-5 bottom-5 hidden w-px bg-white/10 lg:block"></div>
      <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2 lg:block lg:space-y-1 lg:overflow-visible lg:pb-0">
        @foreach($flagships as $key)
          @php $item = __('features.items.'.$key); @endphp
          <button type="button" data-target="tab-toolkit-{{ $key }}"
                  class="ct-focus tab-btn group relative flex w-full shrink-0 items-center gap-3 rounded-xl px-2 py-2.5 text-left transition-colors duration-300 hover:bg-white/[.03] lg:px-3 {{ $loop->first ? 'is-active' : '' }}"
                  aria-selected="{{ $loop->first ? 'true' : 'false' }}">
            <span class="ct-rail-node relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/15" style="background: var(--marketing-ink);">
              <span class="ct-mono ct-rail-num text-[11px]" style="color: var(--marketing-cloud);">{{ $loop->iteration }}</span>
            </span>
            <span class="ct-tab-label whitespace-nowrap text-sm font-medium lg:whitespace-normal" style="color: var(--marketing-cloud);">{{ $item['name'] }}</span>
          </button>
        @endforeach
      </div>
    </div>

    <div class="tab-panels relative min-h-[420px]">
      @foreach($flagships as $key)
        @php $item = __('features.items.'.$key); @endphp
        <div id="tab-toolkit-{{ $key }}" class="tab-content items-center gap-10 lg:grid-cols-[1fr_1.4fr] {{ $loop->first ? 'active' : '' }}">
          <div>
            <span class="ct-mono inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[10px] uppercase tracking-[0.15em]" style="color: var(--marketing-green);">
              {{ __('features.step_label') }} {{ $loop->iteration }} / {{ count($flagships) }}
            </span>
            <h3 class="ct-serif mt-4 text-2xl font-semibold text-white sm:text-3xl">{{ $item['name'] }}</h3>
            <p class="mt-3 text-sm leading-relaxed sm:text-base" style="color: var(--marketing-cloud);">{{ $item['desc'] }}</p>
          </div>

          <div class="group relative">
            <div class="absolute inset-0 -z-10 rounded-3xl blur-2xl transition-colors duration-500" style="background: rgba(0,201,141,.06);"></div>
            <div class="overflow-hidden rounded-2xl border border-white/8 shadow-[0_20px_60px_-15px_rgba(0,0,0,0.6)] transition-all duration-700 ease-out group-hover:-translate-y-2" style="background: var(--marketing-ink-soft);">
              <div class="flex items-center gap-1.5 border-b border-white/8 bg-white/[.02] px-4 py-2.5">
                <span class="h-2 w-2 rounded-full bg-white/15"></span>
                <span class="h-2 w-2 rounded-full bg-white/15"></span>
                <span class="h-2 w-2 rounded-full bg-white/15"></span>
                <span class="ct-mono ml-2 truncate text-[10px] text-white/30">app.careertalent.ai/{{ $key }}</span>
              </div>
              <img src="{{ asset('images/features/'.$key.'.png') }}"
                   onerror="this.onerror=null;this.src='https://placehold.co/1280x800/10172b/aeb7d0?text={{ urlencode($item['name']) }}'"
                   alt="{{ $item['name'] }}"
                   class="aspect-[16/10] w-full object-cover opacity-90 transition-all duration-700 group-hover:opacity-100 group-hover:scale-105">
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- 3. AI CHAT — the always-on career strategist --}}
<section id="ai-chat" class="ct-reveal relative mx-auto max-w-7xl px-6 py-20 lg:px-8">
  <div class="pointer-events-none absolute left-0 top-0 h-[380px] w-[520px] -translate-x-1/3 rounded-full blur-[120px]" style="background: rgba(0,201,141,.07);"></div>

  <div class="relative grid gap-12 lg:grid-cols-[1fr_1.1fr] lg:items-center">
    <div>
      <span class="ct-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ __('features.chat.eyebrow') }}</span>
      <h2 class="ct-serif mt-3 text-2xl font-semibold leading-tight text-white sm:text-3xl">{{ __('features.chat.title') }}</h2>
      <p class="mt-4 max-w-md text-sm leading-relaxed sm:text-base" style="color: var(--marketing-cloud);">{{ __('features.chat.desc') }}</p>

      <div class="mt-8 flex flex-wrap gap-2" data-chat-group>
        @foreach(['career', 'interview'] as $i => $mode)
          <button type="button" data-chat-target="chat-panel-{{ $mode }}"
                  class="ct-focus ct-tab-underline border-b-2 pb-1 text-sm font-medium {{ $i === 0 ? 'is-active' : '' }}">
            {{ __('features.chat.tabs.'.$mode) }}
          </button>
        @endforeach
      </div>
    </div>

    <div class="relative">
      <div class="absolute inset-0 -z-10 rounded-3xl blur-2xl" style="background: rgba(0,201,141,.08);"></div>
      <div class="overflow-hidden rounded-2xl border border-white/8 shadow-[0_20px_60px_-15px_rgba(0,0,0,0.6)] backdrop-blur-md" style="background: var(--marketing-ink-soft);">
        <div class="flex items-center justify-between border-b border-white/8 bg-white/[.02] px-4 py-2.5">
          <div class="flex items-center gap-1.5">
            <span class="h-2 w-2 rounded-full bg-white/15"></span>
            <span class="h-2 w-2 rounded-full bg-white/15"></span>
            <span class="h-2 w-2 rounded-full bg-white/15"></span>
            <span class="ct-mono ml-2 truncate text-[10px] text-white/30">app.careertalent.ai/ai-chat</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="ct-pulse flex h-2 w-2 items-center justify-center rounded-full" style="background: var(--marketing-green);"></span>
            <span class="ct-mono text-[10px] uppercase tracking-[0.15em]" style="color: var(--marketing-cloud);">{{ __('features.chat.live_label') }}</span>
          </div>
        </div>

        <div class="flex min-h-[320px] flex-col justify-end gap-3 p-5">
          @foreach(['career', 'interview'] as $i => $mode)
            <div id="chat-panel-{{ $mode }}" class="ct-chat-panel flex-col gap-3 {{ $i === 0 ? 'active' : '' }}">
              @foreach(__('features.chat.messages_'.$mode) as $message)
                @if($message['role'] === 'ai')
                  <div class="max-w-[85%] rounded-2xl rounded-bl-sm border border-white/8 bg-white/[.03] px-4 py-2.5 text-sm leading-relaxed text-white/90">
                    {{ $message['text'] }}
                  </div>
                @else
                  <div class="ml-auto max-w-[85%] rounded-2xl rounded-br-sm px-4 py-2.5 text-sm leading-relaxed" style="background: var(--marketing-green); color: var(--marketing-ink);">
                    {{ $message['text'] }}
                  </div>
                @endif
              @endforeach
              <div class="flex items-center gap-1.5 px-1 pt-1">
                <span class="ct-mono text-[10px] uppercase tracking-[0.1em]" style="color: var(--marketing-cloud);">{{ __('features.chat.typing_label') }}</span>
                <span class="ct-dot h-1 w-1 rounded-full" style="background: var(--marketing-cloud);"></span>
                <span class="ct-dot h-1 w-1 rounded-full" style="background: var(--marketing-cloud);"></span>
                <span class="ct-dot h-1 w-1 rounded-full" style="background: var(--marketing-cloud);"></span>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- 4. ALSO INCLUDED GRID --}}
<section id="more-tools" class="ct-reveal relative mx-auto max-w-7xl px-6 py-20 lg:px-8">
  <div class="relative mx-auto mb-12 max-w-2xl text-center">
    <span class="ct-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ __('features.more.eyebrow') }}</span>
    <h2 class="ct-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ __('features.more.title') }}</h2>
    <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ __('features.more.desc') }}</p>
  </div>

  @php
    $secondary = [
      'gorevlerim' => 'M9 6.75h9M9 12h9M9 17.25h9M4.5 6.75h.008v.008H4.5V6.75Zm0 5.25h.008v.008H4.5V12Zm0 5.25h.008v.008H4.5v-.008Z',
      'basvurularim' => 'M3.75 6.75h16.5M3.75 6.75v10.5A1.5 1.5 0 0 0 5.25 18.75h13.5a1.5 1.5 0 0 0 1.5-1.5V6.75M3.75 6.75 6 3.75h12l2.25 3M9.75 11.25h4.5',
      'mulakat_hazirligi' => 'M12 15a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3Zm-6-3a6 6 0 0 0 12 0M12 18v3m-3 0h6',
      'uzmanlardan_destek' => 'M15 19.5a3 3 0 0 0-6 0M8.25 9a2.625 2.625 0 1 1 5.25 0 2.625 2.625 0 0 1-5.25 0Zm7.5 1.5a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Zm1.902 5.298A3.75 3.75 0 0 0 15.75 15M4.098 15.798A3.75 3.75 0 0 1 8.25 15',
    ];
  @endphp

  <div class="relative grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
    @foreach($secondary as $key => $path)
      @php $item = __('features.items.'.$key); @endphp
      <div class="ct-glow-card rounded-2xl border border-white/8 bg-white/[.02] p-6 backdrop-blur-md">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/10" style="background: var(--marketing-ink);">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.5" class="h-5 w-5" style="color: var(--marketing-green);">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
          </svg>
        </span>
        <h3 class="ct-serif mt-4 text-lg font-semibold text-white">{{ $item['name'] }}</h3>
        <p class="mt-2 text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ $item['desc'] }}</p>
      </div>
    @endforeach
  </div>
</section>

{{-- 5. FAQ --}}
<section id="faq" class="ct-reveal relative mx-auto max-w-3xl px-6 py-20 lg:px-8">
  <div class="relative mb-10 text-center">
    <span class="ct-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ __('features.faq.eyebrow') }}</span>
    <h2 class="ct-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ __('features.faq.title') }}</h2>
  </div>

  <div class="divide-y divide-white/8 rounded-2xl border border-white/8" style="background: var(--marketing-ink-soft);">
    @foreach(__('features.faq.items') as $index => $faq)
      <div class="ct-faq-item">
        <button type="button" data-faq-toggle
                class="ct-focus flex w-full items-center justify-between gap-4 px-5 py-4 text-left" aria-expanded="false">
          <span class="text-sm font-medium text-white sm:text-base">{{ $faq['q'] }}</span>
          <span class="ct-faq-icon ct-mono flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-white/15 text-sm" style="color: var(--marketing-green);">+</span>
        </button>
        <div class="ct-faq-a px-5">
          <p class="pb-4 text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ $faq['a'] }}</p>
        </div>
      </div>
    @endforeach
  </div>
</section>

{{-- 6. BOTTOM CTA --}}
<section class="ct-reveal relative mx-auto max-w-5xl px-6 pb-28 pt-4 lg:px-8">
  <div class="relative overflow-hidden rounded-3xl border border-white/10 px-8 py-16 text-center sm:px-16" style="background: var(--marketing-ink-soft);">
    <div class="pointer-events-none absolute left-1/2 top-0 h-[300px] w-[600px] -translate-x-1/2 rounded-full blur-[120px]" style="background: rgba(0,201,141,.14);"></div>

    <div class="relative">
      <h2 class="ct-serif text-2xl font-semibold text-white sm:text-4xl">{{ __('features.cta.title') }}</h2>
      <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed sm:text-base" style="color: var(--marketing-cloud);">{{ __('features.cta.desc') }}</p>

      <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('register') }}"
           class="ct-focus rounded-full bg-white px-7 py-3 text-sm font-extrabold !text-black transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_40px_-12px_rgba(255,255,255,.35)]">
          {{ __('features.cta.primary') }}
        </a>
        <a href="{{ route('login') }}"
           class="ct-focus rounded-full border border-white/15 px-7 py-3 text-sm font-medium text-white transition-all duration-300 hover:border-white/30 hover:bg-white/[.03]">
          {{ __('features.cta.secondary') }}
        </a>
      </div>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Scroll-triggered reveal
    var revealTargets = document.querySelectorAll('.ct-reveal');
    if ('IntersectionObserver' in window) {
      var revealObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.15 });
      revealTargets.forEach(function (el) { revealObserver.observe(el); });
    } else {
      revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // Flagship rail tabs
    document.querySelectorAll('[data-tab-group]').forEach(function (group) {
      var buttons = group.querySelectorAll('.tab-btn');
      var panelsRoot = group.querySelector('.tab-panels');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var targetId = btn.getAttribute('data-target');

          buttons.forEach(function (b) {
            b.classList.remove('is-active');
            b.setAttribute('aria-selected', 'false');
          });
          btn.classList.add('is-active');
          btn.setAttribute('aria-selected', 'true');

          if (panelsRoot) {
            panelsRoot.querySelectorAll('.tab-content').forEach(function (panel) {
              panel.classList.toggle('active', panel.id === targetId);
            });
          }
        });
      });
    });

    // AI chat mode switch (career vs. interview)
    document.querySelectorAll('[data-chat-group]').forEach(function (group) {
      var buttons = group.querySelectorAll('[data-chat-target]');
      var section = group.closest('section');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var targetId = btn.getAttribute('data-chat-target');

          buttons.forEach(function (b) { b.classList.remove('is-active'); });
          btn.classList.add('is-active');

          if (section) {
            section.querySelectorAll('.ct-chat-panel').forEach(function (panel) {
              panel.classList.toggle('active', panel.id === targetId);
            });
          }
        });
      });
    });

    // FAQ accordion
    document.querySelectorAll('[data-faq-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.closest('.ct-faq-item');
        var answer = item.querySelector('.ct-faq-a');
        var isOpen = item.classList.contains('is-open');

        item.closest('.divide-y').querySelectorAll('.ct-faq-item.is-open').forEach(function (openItem) {
          if (openItem !== item) {
            openItem.classList.remove('is-open');
            openItem.querySelector('[data-faq-toggle]').setAttribute('aria-expanded', 'false');
            openItem.querySelector('.ct-faq-a').style.maxHeight = null;
          }
        });

        if (isOpen) {
          item.classList.remove('is-open');
          btn.setAttribute('aria-expanded', 'false');
          answer.style.maxHeight = null;
        } else {
          item.classList.add('is-open');
          btn.setAttribute('aria-expanded', 'true');
          answer.style.maxHeight = answer.scrollHeight + 'px';
        }
      });
    });
  });
</script>

@endsection