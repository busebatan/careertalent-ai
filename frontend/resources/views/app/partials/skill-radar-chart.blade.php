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
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 flex-1">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold">{{ __('panel.skill_radar.title') }}</h2>
                <span class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                    {{ __('panel.skill_radar.ai_badge') }}
                </span>
                @if (! empty($fromApi))
                    <span class="rounded-full bg-sky-500/15 px-2.5 py-0.5 text-xs font-medium text-sky-700 dark:text-sky-300">
                        {{ __('panel.skill_radar.from_cv_analysis') }}
                    </span>
                @endif
            </div>
            <p class="panel-muted text-sm">{{ __('panel.skill_radar.subtitle', ['role' => $skillRadar['target_role']]) }}</p>
            <p class="panel-muted mt-1 text-xs">
                {{ __('panel.skill_radar.analysis_cv', ['name' => $skillRadar['file_name'] ?? $cvFileName ?? 'cv']) }}
                · {{ __('panel.skill_radar.analysis_source', ['source' => $sourceLabel]) }}
                · {{ __('panel.skill_radar.analyzed_at', ['date' => $analysisDate]) }}
                @if (! empty($showAnalysisId))
                    · <span class="break-all font-mono">{{ __('panel.skill_radar.analysis_id', ['id' => $skillRadar['analysis_id'] ?? '—']) }}</span>
                @endif
                @if (! empty($showClearInline))
                    · <button type="button" @click="resetOpen = true"
                        class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                        {{ __('panel.skill_radar.clear_cv') }}
                    </button>
                @elseif (! empty($showCvToolbar))
                    · <button type="button" @click="resetOpen = true"
                        class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                        {{ __('panel.skill_radar.clear_cv') }}
                    </button>
                @endif
            </p>
        </div>

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

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center">
        <div class="relative mx-auto w-full max-w-md">
            <svg viewBox="0 0 320 320" class="h-auto w-full" role="img" aria-label="{{ __('panel.skill_radar.title') }}">
                @foreach ([25, 50, 75, 100] as $ring)
                    @php
                        $ringPoints = [];
                        for ($i = 0; $i < $n; $i++) {
                            [$rx, $ry] = $radarPoint($i, $n, (float) $ring);
                            $ringPoints[] = "{$rx},{$ry}";
                        }
                    @endphp
                    <polygon points="{{ implode(' ', $ringPoints) }}"
                        fill="none"
                        class="stroke-slate-200 dark:stroke-slate-700"
                        stroke-width="1"
                        @if ($ring === 100) stroke-dasharray="4 3" @endif />
                @endforeach

                @for ($i = 0; $i < $n; $i++)
                    @php [$ax, $ay] = $radarPoint($i, $n, 100); @endphp
                    <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $ax }}" y2="{{ $ay }}"
                        class="stroke-slate-200 dark:stroke-slate-700" stroke-width="1"/>
                @endfor

                <polygon points="{{ implode(' ', $targetPoly) }}"
                    fill="none"
                    class="stroke-slate-400 dark:stroke-slate-500"
                    stroke-width="1.5"
                    stroke-dasharray="5 4"/>

                <polygon points="{{ implode(' ', $currentPoly) }}"
                    class="fill-emerald-500/20 stroke-emerald-500 dark:fill-emerald-400/20 dark:stroke-emerald-400"
                    stroke-width="2"/>

                @foreach ($skills as $i => $skill)
                    @php
                        [$px, $py] = $radarPoint($i, $n, (float) $skill['score']);
                        [$lx, $ly] = $labelPoint($i, $n);
                        $anchor = $lx < $cx - 10 ? 'end' : ($lx > $cx + 10 ? 'start' : 'middle');
                    @endphp
                    <circle cx="{{ $px }}" cy="{{ $py }}" r="3.5" class="fill-emerald-500 dark:fill-emerald-400"/>
                    <text x="{{ $lx }}" y="{{ $ly }}" text-anchor="{{ $anchor }}" dominant-baseline="middle"
                        class="fill-slate-600 text-[9px] font-medium dark:fill-slate-300">
                        {{ $skill['label'] }}
                    </text>
                @endforeach
            </svg>

            <div class="mt-3 flex flex-wrap justify-center gap-4 text-xs">
                <span class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                    <span class="inline-block h-0.5 w-5 rounded bg-emerald-500"></span>
                    {{ __('panel.skill_radar.your_level') }}
                </span>
                <span class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                    <span class="inline-block h-0.5 w-5 border-t border-dashed border-slate-400"></span>
                    {{ __('panel.skill_radar.target_role') }}
                </span>
            </div>
        </div>

        <ul class="space-y-3">
            @foreach ($skills as $skill)
                @php
                    $gap = max(0, $skill['target'] - $skill['score']);
                    $barColor = $skill['score'] >= $skill['target']
                        ? 'bg-emerald-500'
                        : ($gap > 20 ? 'bg-amber-500' : 'bg-sky-500');
                @endphp
                <li class="panel-entry !space-y-2 !p-3">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $skill['label'] }}</span>
                        <span class="tabular-nums text-slate-600 dark:text-slate-300">
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">%{{ $skill['score'] }}</span>
                            <span class="panel-muted text-xs">/ %{{ $skill['target'] }}</span>
                        </span>
                    </div>
                    <div class="relative h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div class="absolute inset-y-0 left-0 rounded-full {{ $barColor }}" style="width: {{ $skill['score'] }}%"></div>
                        <div class="absolute inset-y-0 w-0.5 rounded-full bg-slate-400 dark:bg-slate-500" style="left: {{ $skill['target'] }}%"></div>
                    </div>
                    @if ($gap > 0)
                        <p class="panel-muted text-[11px]">{{ __('panel.skill_radar.gap', ['points' => $gap]) }}</p>
                    @else
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400">{{ __('panel.skill_radar.met') }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
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
