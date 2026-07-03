{{-- @param string $titleKey marketing.*.title --}}
{{-- @param string|null $introKey marketing.*.intro --}}
<section class="mx-auto max-w-3xl px-4 py-20 text-center">
    <div class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 text-2xl text-slate-500" aria-hidden="true">◇</div>
    <h1 class="mb-3 text-3xl font-bold tracking-tight md:text-4xl">{{ __($titleKey) }}</h1>
    @if(!empty($introKey))
        <p class="mb-6 text-lg text-slate-400">{{ __($introKey) }}</p>
    @endif
    <p class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900 px-4 py-2 text-sm text-slate-400">
        <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
        {{ __('marketing.placeholder') }}
    </p>
</section>
