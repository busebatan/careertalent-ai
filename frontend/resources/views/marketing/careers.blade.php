@extends('marketing.layouts.marketing')

@section('title', __('marketing.careers.title'))

@section('content')
<section class="mx-auto max-w-5xl px-4 py-12 lg:py-16" id="meslekler-sihirbaz" data-careers-root>
    <header class="mb-10 text-center">
        <h1 class="mb-3 text-3xl font-bold tracking-tight md:text-4xl">{{ __('marketing.careers.title') }}</h1>
        <p class="mx-auto max-w-2xl text-lg text-slate-400">{{ __('marketing.careers.intro') }}</p>
    </header>

  <div data-careers-wizard>
        <ol class="mb-8 flex flex-wrap items-center justify-center gap-2 sm:gap-0" data-careers-stepper aria-label="{{ __('marketing.careers.wizard_label') }}">
            @foreach([
                ['index' => 0, 'key' => 'step_main'],
                ['index' => 1, 'key' => 'step_current'],
                ['index' => 2, 'key' => 'step_target'],
                ['index' => 3, 'key' => 'step_salary'],
            ] as $i => $s)
                @if($i > 0)
                    <li class="hidden h-px w-6 bg-slate-700 sm:block" aria-hidden="true"></li>
                @endif
                <li data-step-index="{{ $s['index'] }}"
                    class="rounded-full border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-500 sm:px-4 sm:text-sm">
                    <span class="mr-1.5 text-emerald-500">{{ $s['index'] + 1 }}</span>{{ __('marketing.careers.'.$s['key']) }}
                </li>
            @endforeach
        </ol>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 md:p-8">
            <div data-careers-step="0">
                <label for="careers-main" class="mb-2 block text-sm font-medium text-slate-300">{{ __('marketing.careers.step_main') }}</label>
                <select id="careers-main" data-careers-main
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </select>
                <p class="mt-2 text-xs text-slate-500">{{ __('marketing.careers.step_main_hint') }}</p>
            </div>

            <div data-careers-step="1" hidden>
                <label for="careers-current" class="mb-2 block text-sm font-medium text-slate-300">{{ __('marketing.careers.step_current') }}</label>
                <select id="careers-current" data-careers-current
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </select>
                <p class="mt-2 text-xs text-slate-500">{{ __('marketing.careers.step_current_hint') }}</p>
            </div>

            <div data-careers-step="2" hidden>
                <p class="mb-3 text-sm font-medium text-slate-300">{{ __('marketing.careers.step_target') }}</p>
                <p class="mb-2 text-xs text-slate-500">{{ __('marketing.careers.step_target_hint') }}</p>
                <p class="mb-4 text-xs text-amber-400/90">{{ __('marketing.careers.targets_optional') }}</p>
                <div class="grid gap-2 sm:grid-cols-2" data-careers-target></div>
            </div>

            <div data-careers-step="3" hidden>
                <label for="careers-salary" class="mb-2 block text-sm font-medium text-slate-300">{{ __('marketing.careers.step_salary') }}</label>
                <select id="careers-salary" data-careers-salary
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </select>
                <p class="mt-2 text-xs text-slate-500">{{ __('marketing.careers.step_salary_hint') }}</p>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-6" data-careers-actions>
                <button type="button" data-careers-back hidden
                        class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm text-slate-300 transition hover:border-slate-600 hover:text-white">
                    {{ __('marketing.careers.back') }}
                </button>
                <div class="ml-auto flex flex-wrap justify-end gap-2">
                    <button type="button" data-careers-next disabled
                            class="rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40">
                        {{ __('marketing.careers.next') }}
                    </button>
                    <button type="button" data-careers-show hidden disabled
                            class="rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40">
                        {{ __('marketing.careers.show_results') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-10" data-careers-results hidden>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-white">{{ __('marketing.careers.results_title') }}</h2>
                <div class="mt-2" data-careers-summary></div>
            </div>
            <div class="flex shrink-0 items-center gap-5">
                <button type="button" data-careers-results-back
                        class="text-sm text-slate-400 transition hover:text-emerald-400">
                    ← {{ __('marketing.careers.back') }}
                </button>
                <button type="button" data-careers-reset
                        class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 transition hover:border-slate-600 hover:text-white">
                    {{ __('marketing.careers.reset') }}
                </button>
            </div>
        </div>
        <div class="space-y-6" data-careers-results-grid></div>
        <div class="mt-8 text-center">
            <a href="{{ route('register') }}" class="inline-block rounded-xl bg-emerald-500 px-8 py-4 font-semibold text-slate-950 hover:bg-emerald-400">
                {{ __('marketing.home.cta_register') }}
            </a>
        </div>
    </div>
</section>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-careers-root]');
        if (!root || typeof window.initCareersWizard !== 'function') return;
        window.initCareersWizard(root, @json($careersCatalog), @json($careersWizardLabels));
    });
</script>
@endpush
@endsection
