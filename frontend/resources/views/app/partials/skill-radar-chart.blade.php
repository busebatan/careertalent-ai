@php
    $skills = $skillRadar['skills'];
    $n = count($skills);
    $svgSize = 360;
    $cx = 180;
    $cy = 180;
    $maxR = 102;
    $plotSafePadding = 4;
    $plotSafeMin = $cx - $maxR - $plotSafePadding;
    $plotSafeMax = $cx + $maxR + $plotSafePadding;

    $radarPoint = static function (int $i, int $total, float $score) use ($cx, $cy, $maxR): array {
        $angle = (2 * M_PI * $i / $total) - M_PI / 2;
        $r = ($score / 100) * $maxR;

        return [
            round($cx + $r * cos($angle), 2),
            round($cy + $r * sin($angle), 2),
        ];
    };

    $wrapSkillLabel = static function (string $label, int $maxChars = 16, int $maxLines = 4): array {
        if (mb_strlen($label) <= $maxChars) {
            return [$label];
        }

        $words = preg_split('/[\s\/\-]+/u', trim($label), -1, PREG_SPLIT_NO_EMPTY) ?: [$label];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            while (mb_strlen($word) > $maxChars) {
                $lines[] = mb_substr($word, 0, $maxChars);
                $word = mb_substr($word, $maxChars);
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lastIndex = $maxLines - 1;
            $lines[$lastIndex] = rtrim(mb_substr($lines[$lastIndex], 0, max(1, $maxChars - 1))).'…';
        }

        return $lines !== [] ? $lines : [$label];
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
    $radarAlignment = in_array(($radarAlignment ?? 'left'), ['left', 'intro-centered', 'frame-centered'], true)
        ? $radarAlignment
        : 'left';
@endphp

<section id="yetenek-radari" class="panel-card mb-8 overflow-hidden p-6 lg:p-8">
    @if (! empty($collapsible))
        <details class="group" :open="radarExpanded" @toggle="onRadarToggle($event)">
            <summary
                @if ($radarAlignment === 'frame-centered') data-skill-radar-frame="summary" @endif
                @class([
                    'flex w-full cursor-pointer list-none flex-wrap items-start justify-between gap-4 [&::-webkit-details-marker]:hidden',
                    'md:mx-auto md:max-w-[54rem]' => $radarAlignment === 'frame-centered',
                ])>
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
            <div
                @if ($radarAlignment === 'frame-centered') data-skill-radar-frame="body" @endif
                @class([
                    'mt-6 w-full border-t border-slate-200 pt-6 dark:border-slate-800',
                    'md:mx-auto md:max-w-[54rem]' => $radarAlignment === 'frame-centered',
                ])>
                @include('app.partials.skill-radar-chart-body')
            </div>
        </details>
    @else
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            @include('app.partials.skill-radar-chart-intro')
            <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-stretch">
                @if (! empty($showCvToolbar))
                    <div class="flex flex-col gap-2 sm:min-w-[9.5rem]">
                        <a href="{{ route('panel.cv-builder') }}"
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
    @include('app.partials.career-reset-modal', [
        'resetAction' => ! empty($showClearInline) ? 'clearCvAnalysis()' : 'clearCv()',
    ])
@endif
