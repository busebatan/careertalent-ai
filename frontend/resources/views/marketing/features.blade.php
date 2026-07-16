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

  @media (prefers-reduced-motion: reduce) {
    .ct-reveal, .ct-pulse, .ct-dot, .tab-content.active { animation: none !important; opacity: 1 !important; }
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
         class="rounded-full px-6 py-3 text-sm font-semibold transition-all duration-300 hover:-translate-y-0.5"
         style="background: var(--marketing-green); color: var(--marketing-ink);">
        {{ __('marketing.home.cta_register') }}
      </a>
      <a href="#ai-chat"
         class="rounded-full border border-white/12 px-6 py-3 text-sm font-medium text-white transition-all duration-300 hover:border-white/25 hover:bg-white/[.03]">
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

{{-- 2. CORE TOOLKIT --}}
{{-- CHANGED: max-w-6xl -> max-w-7xl --}}
<section id="toolkit" class="ct-reveal relative mx-auto max-w-7xl px-6 py-20 lg:px-8">
  <div class="pointer-events-none absolute right-0 top-1/3 h-[360px] w-[500px] rounded-full blur-[110px]" style="background: rgba(0,201,141,.05);"></div>

  <div class="relative mx-auto mb-12 max-w-2xl text-center">
    <span class="ct-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ __('features.toolkit.eyebrow') }}</span>
    <h2 class="ct-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ __('features.toolkit.title') }}</h2>
    <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ __('features.toolkit.desc') }}</p>
  </div>

  @php $flagships = ['cv_analysis', 'career_ladder', 'readiness_score', 'roadmap', 'skill_passport']; @endphp

  <div data-tab-group="toolkit" class="relative grid gap-8 lg:grid-cols-[260px_1fr] lg:gap-10">
    <div class="relative">
      <div class="pointer-events-none absolute left-5 top-5 bottom-5 hidden w-px bg-white/10 lg:block"></div>
      <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2 lg:block lg:space-y-1 lg:overflow-visible lg:pb-0">
        @foreach($flagships as $key)
          @php $item = __('features.items.'.$key); @endphp
          <button type="button" data-target="tab-toolkit-{{ $key }}"
                  class="tab-btn group relative flex w-full shrink-0 items-center gap-3 rounded-xl px-2 py-2.5 text-left transition-colors duration-300 hover:bg-white/[.03] lg:px-3"
                  aria-selected="{{ $loop->first ? 'true' : 'false' }}">
            <span class="ct-rail-node relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-white/15" style="background: var(--marketing-ink);">
              <span class="ct-mono ct-rail-num text-[11px]" style="color: var(--marketing-cloud);">{{ $loop->iteration }}</span>
            </span>
            <span class="ct-tab-label whitespace-nowrap text-sm font-medium lg:whitespace-normal" style="color: var(--marketing-cloud);">{{ $item['name'] }}</span>
          </button>
        @endforeach
      </div>
    </div>

    <div class="relative min-h-[420px]">
      @foreach($flagships as $key)
        @php $item = __('features.items.'.$key); @endphp
        {{-- CHANGED: lg:grid-cols-2 -> lg:grid-cols-[1fr_1.4fr] --}}
        <div id="tab-toolkit-{{ $key }}" class="tab-content items-center gap-10 lg:grid-cols-[1fr_1.4fr]">
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
              {{-- CHANGED: aspect-[4/3] -> aspect-[16/10] --}}
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

{{-- 3. SIGNATURE BLOCK — AI Chat --}}
<section id="ai-chat" class="ct-reveal relative mx-auto max-w-4xl px-6 py-24 text-center lg:px-8">
  <div class="pointer-events-none absolute left-1/2 top-1/2 h-[380px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full blur-[100px]" style="background: rgba(0,201,141,.10);"></div>

  <div class="relative mx-auto max-w-md rounded-3xl border border-white/10 p-8 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.7)]" style="background: var(--marketing-ink-soft);">
    <div class="mb-6 flex items-center justify-center gap-4">
      <span class="ct-pulse inline-block" style="transform: scale(1.6);" aria-hidden="true">
        <span class="brand-mark"><span></span><span></span><span></span></span>
      </span>
      <span class="ct-mono text-xs uppercase tracking-[0.2em]" style="color: var(--marketing-green);">{{ __('features.live_label') }}</span>
    </div>
    <div class="flex justify-center gap-1.5 py-4">
      <span class="ct-dot h-2 w-2 rounded-full" style="background: var(--marketing-cloud);"></span>
      <span class="ct-dot h-2 w-2 rounded-full" style="background: var(--marketing-cloud);"></span>
      <span class="ct-dot h-2 w-2 rounded-full" style="background: var(--marketing-cloud);"></span>
    </div>
  </div>

  <h2 class="ct-serif mt-10 text-3xl font-semibold text-white sm:text-4xl">{{ __('features.items.chat.name') }}</h2>
  <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed" style="color: var(--marketing-cloud);">{{ __('features.items.chat.desc') }}</p>

  <a href="{{ route('panel.chat') }}"
     class="group relative mt-9 inline-flex items-center gap-2 rounded-full px-7 py-3.5 text-sm font-semibold transition-all duration-300 hover:-translate-y-0.5"
     style="background: var(--marketing-green); color: var(--marketing-ink);">
    {{ __('features.chat_cta') }}
    <span class="transition-transform duration-300 group-hover:translate-x-1">→</span>
  </a>
</section>

{{-- 4. VALUE PROPS --}}
<section class="mx-auto max-w-6xl border-t border-white/8 px-6 py-20 lg:px-8">
  <div class="grid gap-6 md:grid-cols-3">
    @foreach(['speed', 'evidence', 'action'] as $key)
      <div class="ct-reveal rounded-2xl border border-white/8 bg-white/[.015] p-6 transition-colors duration-300 hover:border-white/15">
        <h3 class="ct-serif text-xl font-semibold text-white">{{ __('features.value_props.'.$key.'.title') }}</h3>
        <p class="mt-2 text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ __('features.value_props.'.$key.'.desc') }}</p>
      </div>
    @endforeach
  </div>
</section>

{{-- 5. ALSO INCLUDED --}}
{{-- CHANGED: max-w-6xl -> max-w-7xl --}}
<section id="more-tools" class="ct-reveal relative mx-auto max-w-7xl px-6 pb-20 lg:px-8">
  <div class="mb-10">
    <h2 class="ct-serif text-2xl font-semibold text-white sm:text-3xl">{{ __('features.also_included') }}</h2>
    <p class="mt-2 text-sm" style="color: var(--marketing-cloud);">{{ __('features.more_tools_desc') }}</p>
  </div>

  @php $extras = ['job_radar', 'applications', 'interview_sim', 'mentor_support', 'learning']; @endphp

  <div data-tab-group="extras">
    <div class="mb-10 flex gap-8 overflow-x-auto border-b border-white/10 hide-scrollbar">
      @foreach($extras as $key)
        <button type="button" data-target="tab-extra-{{ $key }}"
                class="tab-btn ct-tab-underline whitespace-nowrap border-b-2 pb-4 text-sm font-medium"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
           {{ __('features.items.'.$key.'.name') }}
        </button>
      @endforeach
    </div>

    <div class="relative min-h-[300px]">
      @foreach($extras as $key)
        @php $item = __('features.items.'.$key); @endphp
        {{-- CHANGED: lg:grid-cols-2 -> lg:grid-cols-[1fr_1.4fr] --}}
        <div id="tab-extra-{{ $key }}" class="tab-content items-center gap-10 lg:grid-cols-[1fr_1.4fr]">
          <div class="order-2 lg:order-1">
            <h3 class="ct-serif mb-3 text-xl font-semibold text-white sm:text-2xl">{{ $item['name'] }}</h3>
            <p class="mb-5 text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ $item['desc'] }}</p>
            <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-widest" style="color: var(--marketing-green);">
               <span class="h-1.5 w-1.5 rounded-full" style="background: var(--marketing-green);"></span>
               {{ __('features.add_on_label') }}
            </div>
          </div>

          <div class="group relative order-1 lg:order-2">
            <div class="overflow-hidden rounded-2xl border border-white/8 shadow-[0_15px_40px_-10px_rgba(0,0,0,0.5)] transition-transform duration-500 group-hover:-translate-y-1.5" style="background: var(--marketing-ink-soft);">
              <div class="flex items-center gap-1.5 border-b border-white/8 bg-white/[.02] px-4 py-2.5">
                <span class="h-2 w-2 rounded-full bg-white/15"></span>
                <span class="h-2 w-2 rounded-full bg-white/15"></span>
                <span class="h-2 w-2 rounded-full bg-white/15"></span>
                <span class="ct-mono ml-2 truncate text-[10px] text-white/30">app.careertalent.ai/{{ $key }}</span>
              </div>
              {{-- CHANGED: aspect-[4/3] -> aspect-[16/10] --}}
              <img src="{{ asset('images/features/'.$key.'.png') }}"
                   onerror="this.onerror=null;this.src='https://placehold.co/1280x800/10172b/aeb7d0?text={{ urlencode($item['name']) }}'"
                   alt="{{ $item['name'] }}"
                   class="aspect-[16/10] w-full object-cover transition-transform duration-700 group-hover:scale-105">
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- 6. FAQ --}}
<section id="faq" class="ct-reveal mx-auto max-w-3xl border-t border-white/8 px-6 py-20 lg:px-8">
  <div class="mb-10 text-center">
    <span class="ct-mono text-[11px] uppercase tracking-[0.25em]" style="color: var(--marketing-green);">{{ __('features.faq.eyebrow') }}</span>
    <h2 class="ct-serif mt-3 text-2xl font-semibold text-white sm:text-3xl">{{ __('features.faq.title') }}</h2>
  </div>

  <div class="space-y-3">
    @foreach(__('features.faqs') as $faq)
      <div class="ct-faq-item overflow-hidden rounded-2xl border border-white/8 bg-white/[.015]">
        <button type="button" class="ct-faq-q flex w-full items-center justify-between gap-4 px-5 py-4 text-left" aria-expanded="false">
          <span class="text-sm font-medium text-white sm:text-base">{{ $faq['q'] }}</span>
          <span class="ct-faq-icon ct-mono flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-white/15 text-sm" style="color: var(--marketing-cloud);">+</span>
        </button>
        <div class="ct-faq-a px-5">
          <p class="pb-4 text-sm leading-relaxed" style="color: var(--marketing-cloud);">{{ $faq['a'] }}</p>
        </div>
      </div>
    @endforeach
  </div>
</section>

{{-- 7. BOTTOM CTA --}}
<section class="border-t border-white/8 px-6 py-24 text-center lg:px-8">
  <h2 class="ct-serif text-2xl font-semibold text-white sm:text-3xl">{{ __('features.closing_title') }}</h2>
  <a href="{{ route('register') }}"
     class="mt-7 inline-flex items-center gap-2 rounded-full bg-white px-9 py-3.5 text-sm font-extrabold !text-black transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_30px_rgba(255,255,255,0.2)]">
    {{ __('marketing.home.cta_register') }}
  </a>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.15 });
      document.querySelectorAll('.ct-reveal').forEach(function (el) { io.observe(el); });
    } else {
      document.querySelectorAll('.ct-reveal').forEach(function (el) { el.classList.add('is-visible'); });
    }

    document.querySelectorAll('[data-tab-group]').forEach(function (group) {
      var tabs = group.querySelectorAll('.tab-btn');
      var contents = group.querySelectorAll('.tab-content');
      if (!tabs.length) return;

      function activate(tab) {
        tabs.forEach(function (t) { t.classList.remove('is-active'); t.setAttribute('aria-selected', 'false'); });
        contents.forEach(function (c) { c.classList.remove('active'); });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        var target = document.getElementById(tab.getAttribute('data-target'));
        if (target) target.classList.add('active');
      }

      activate(tabs[0]);
      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { activate(tab); });
      });
    });

    document.querySelectorAll('.ct-faq-item').forEach(function (item) {
      var btn = item.querySelector('.ct-faq-q');
      var panel = item.querySelector('.ct-faq-a');
      btn.addEventListener('click', function () {
        var isOpen = item.classList.contains('is-open');
        document.querySelectorAll('.ct-faq-item.is-open').forEach(function (openItem) {
          if (openItem !== item) {
            openItem.classList.remove('is-open');
            openItem.querySelector('.ct-faq-a').style.maxHeight = null;
            openItem.querySelector('.ct-faq-q').setAttribute('aria-expanded', 'false');
          }
        });
        item.classList.toggle('is-open', !isOpen);
        btn.setAttribute('aria-expanded', String(!isOpen));
        panel.style.maxHeight = !isOpen ? panel.scrollHeight + 'px' : null;
      });
    });
  });
</script>
@endsection