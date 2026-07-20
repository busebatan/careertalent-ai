@php
    $skills = $skillRadar['skills'];
    $n = count($skills);
    $cx = 160;
    $cy = 160;
    $maxR = 105;

    $radarPoint = static function (int $i, int $total, float $score) use ($cx, $cy, $maxR): array {
        $angle = (2 * M_PI * $i / $total) - M_PI / 2;
        $r = ($score / 100) * $maxR;

        return [
            round($cx + $r * cos($angle), 2),
            round($cy + $r * sin($angle), 2),
        ];
    };

    $labelPoint = static function (int $i, int $total) use ($cx, $cy, $maxR): array {
        $angle = (2 * M_PI * $i / $total) - M_PI / 2;
        $r = $maxR + 22;

        return [
            round($cx + $r * cos($angle), 2),
            round($cy + $r * sin($angle), 2),
        ];
    };

    $currentPoly = [];
    $targetPoly = [];
    foreach ($skills as $i => $skill) {
        [$x, $y] = $radarPoint($i, $n, (float) $skill['score']);
        $currentPoly[] = "{$x},{$y}";
        [$tx, $ty] = $radarPoint($i, $n, (float) $skill['target']);
        $targetPoly[] = "{$tx},{$ty}";
    }

    $analysisDate = (string) ($skillRadar['analyzed_at'] ?? '');
    try {
        $analysisDate = $analysisDate !== '' ? \Illuminate\Support\Carbon::parse($analysisDate)->format('d.m.Y H:i') : '—';
    } catch (\Throwable) {
        $analysisDate = $analysisDate ?: '—';
    }
    $analysisSource = (string) ($skillRadar['source'] ?? '');
    $sourceKey = 'panel.skill_radar.sources.'.$analysisSource;
    $sourceLabel = $analysisSource !== '' && __($sourceKey) !== $sourceKey ? __($sourceKey) : ($analysisSource ?: '—');
@endphp

<section id="yetenek-radari" class="panel-card mb-8 overflow-hidden p-6 lg:p-8">
    @if (! empty($collapsible))
        <details class="group" :open="radarExpanded" @toggle="onRadarToggle($event)">
            <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-4 [&::-webkit-details-marker]:hidden">
                @include('app.partials.skill-radar-chart-intro')
                <div class="flex flex-wrap items-center gap-3">
                    <div class="panel-card shrink-0 border-emerald-500/20 bg-emerald-500/5 px-5 py-4 text-center dark:bg-emerald-500/10">
                        <p class="panel-muted text-xs uppercase tracking-wide">{{ __('panel.skill_radar.overall') }}</p>
                        <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">%{{ $skillRadar['overall_match'] }}</p>
                        <a href="{{ route('panel.career-ladder') }}" @click.stop class="mt-1 inline-block text-xs text-emerald-600 hover:underline dark:text-emerald-400">
                            {{ __('panel.skill_radar.view_ladder') }} →
                        </a>
                    </div>
                    <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" aria-hidden="true"></i>
                    <span class="sr-only">{{ __('panel.skill_radar.show_details') }}</span>
                </div>
            </summary>
            <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-800">
                @include('app.partials.skill-radar-chart-body')
            </div>
        </details>
    @else
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            @include('app.partials.skill-radar-chart-intro')
            <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-stretch">
                @if (! empty($showCvToolbar))
                    <div class="flex flex-col gap-2 sm:min-w-[9.5rem]">
                        <a href="{{ route('panel.account') }}#cv-yukle"
                            class="panel-btn-secondary text-center text-sm">
                            {{ __('panel.dashboard.upload_cv') }}
                        </a>
                        <a href="{{ route('panel.cv-builder') }}"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-emerald-500">
                            {{ __('panel.skill_radar.update_cv') }}
                        </a>
                    </div>
                @endif

                <div class="panel-card shrink-0 border-emerald-500/20 bg-emerald-500/5 px-5 py-4 text-center dark:bg-emerald-500/10">
                    <p class="panel-muted text-xs uppercase tracking-wide">{{ __('panel.skill_radar.overall') }}</p>
                    <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">%{{ $skillRadar['overall_match'] }}</p>
                    <a href="{{ route('panel.career-ladder') }}" class="mt-1 inline-block text-xs text-emerald-600 hover:underline dark:text-emerald-400">
                        {{ __('panel.skill_radar.view_ladder') }} →
                    </a>
                </div>
            </div>
        </div>

        @include('app.partials.skill-radar-chart-body')
    @endif
</section>

@if (! empty($showClearInline) || ! empty($showCvToolbar))
    <div x-show="resetOpen" x-cloak @keydown.escape.window="if (!resetWorking) resetOpen = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="career-reset-title">
        <div @click.outside="if (!resetWorking) resetOpen = false" class="panel-card w-full max-w-lg space-y-5 p-6">
            <div>
                <h2 id="career-reset-title" class="text-lg font-semibold">{{ __('panel.skill_radar.reset_title') }}</h2>
                <p class="panel-muted mt-1 text-sm">{{ __('panel.skill_radar.reset_desc') }}</p>
            </div>
            <div class="space-y-3">
                @foreach ([
                    ['value' => 'analysis', 'title' => 'reset_analysis', 'desc' => 'reset_analysis_desc'],
                    ['value' => 'plan', 'title' => 'reset_plan', 'desc' => 'reset_plan_desc'],
                    ['value' => 'all', 'title' => 'reset_all', 'desc' => 'reset_all_desc'],
                ] as $option)
                    <label class="panel-entry flex cursor-pointer items-start gap-3 p-4">
                        <input type="radio" x-model="resetScope" value="{{ $option['value'] }}" class="mt-1 accent-emerald-500">
                        <span>
                            <span class="block text-sm font-medium">{{ __('panel.skill_radar.'.$option['title']) }}</span>
                            <span class="panel-muted mt-1 block text-xs">{{ __('panel.skill_radar.'.$option['desc']) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p x-show="resetError" x-cloak class="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-200" x-text="resetError"></p>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" @click="resetOpen = false" :disabled="resetWorking" class="panel-btn-secondary">
                    {{ __('panel.skill_radar.reset_cancel') }}
                </button>
                <button type="button" @click="{{ ! empty($showClearInline) ? 'clearCvAnalysis()' : 'clearCv()' }}" :disabled="resetWorking"
                    class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-60"
                    x-text="resetWorking ? @js(__('panel.skill_radar.reset_working')) : @js(__('panel.skill_radar.reset_confirm'))">
                </button>
            </div>
        </div>
    </div>
@endif
