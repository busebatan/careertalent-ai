@php
    $radarAlignment = in_array(($radarAlignment ?? 'left'), ['left', 'intro-centered', 'frame-centered'], true)
        ? $radarAlignment
        : 'left';
@endphp

<div data-skill-radar-layout="split"
    data-skill-radar-alignment="{{ $radarAlignment }}"
    class="grid min-w-0 gap-4 md:mx-auto md:w-fit md:max-w-full md:grid-cols-[minmax(0,35rem)_minmax(15rem,18rem)] md:items-center">
    <div class="relative mx-auto min-w-0 w-full max-w-[35rem] overflow-hidden">
        <svg viewBox="0 0 {{ $svgSize }} {{ $svgSize }}"
            data-radar-label-safe-layout
            data-radar-plot-safe-zone
            data-radar-plot-min-x="{{ $plotSafeMin }}"
            data-radar-plot-min-y="{{ $plotSafeMin }}"
            data-radar-plot-max-x="{{ $plotSafeMax }}"
            data-radar-plot-max-y="{{ $plotSafeMax }}"
            class="h-auto w-full overflow-hidden" role="img" aria-label="{{ __('panel.skill_radar.title') }}">
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
                    $angle = (2 * M_PI * $i / $n) - M_PI / 2;
                    $cosine = cos($angle);
                    $sine = sin($angle);
                    $usesSideColumn = abs($cosine) >= abs($sine);
                    $labelSide = $usesSideColumn
                        ? ($cosine >= 0 ? 'right' : 'left')
                        : ($sine >= 0 ? 'bottom' : 'top');
                    $labelWidth = $usesSideColumn ? 66 : 128;
                    $labelLines = $wrapSkillLabel((string) $skill['label'], $usesSideColumn ? 10 : 18, 4);
                    $lineHeight = 11;
                    $labelHeight = count($labelLines) * $lineHeight + 2;
                    $labelRadius = $maxR + 36;
                    $labelX = match ($labelSide) {
                        'left' => 4,
                        'right' => $svgSize - $labelWidth - 4,
                        default => min(
                            $svgSize - $labelWidth - 4,
                            max(4, $cx + ($labelRadius * $cosine) - ($labelWidth / 2)),
                        ),
                    };
                    $labelY = match ($labelSide) {
                        'top' => 4,
                        'bottom' => $svgSize - $labelHeight - 4,
                        default => min(
                            $svgSize - $labelHeight - 4,
                            max(4, $cy + ($labelRadius * $sine) - ($labelHeight / 2)),
                        ),
                    };
                    $labelAlign = match ($labelSide) {
                        'left' => 'text-right',
                        'right' => 'text-left',
                        default => 'text-center',
                    };
                @endphp
                <circle cx="{{ $px }}" cy="{{ $py }}" r="3.5" class="fill-emerald-500 dark:fill-emerald-400"/>
                <foreignObject data-radar-label-box data-radar-label-side="{{ $labelSide }}"
                    x="{{ round($labelX, 2) }}" y="{{ round($labelY, 2) }}" width="{{ $labelWidth }}" height="{{ $labelHeight }}">
                    <div xmlns="http://www.w3.org/1999/xhtml"
                        class="{{ $labelAlign }} break-words [overflow-wrap:anywhere] text-[9px] font-medium leading-[11px] text-slate-600 dark:text-slate-300">
                        @foreach ($labelLines as $line)
                            <div>{{ $line }}</div>
                        @endforeach
                    </div>
                </foreignObject>
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

    <ul class="min-w-0 w-full space-y-2">
        @foreach ($skills as $skill)
            @php
                $gap = max(0, $skill['target'] - $skill['score']);
                $barColor = $skill['score'] >= $skill['target']
                    ? 'bg-emerald-500'
                    : ($gap > 20 ? 'bg-amber-500' : 'bg-sky-500');
            @endphp
            <li class="panel-entry !space-y-1.5 !p-2.5">
                <div class="flex items-start justify-between gap-2 text-xs">
                    <span class="min-w-0 flex-1 break-words [overflow-wrap:anywhere] font-medium leading-snug text-slate-800 dark:text-slate-100">{{ $skill['label'] }}</span>
                    <span class="shrink-0 tabular-nums text-slate-600 dark:text-slate-300">
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">%{{ $skill['score'] }}</span>
                        <span class="panel-muted text-[10px]">/ %{{ $skill['target'] }}</span>
                    </span>
                </div>
                <div class="relative h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                    <div class="absolute inset-y-0 left-0 rounded-full {{ $barColor }}" style="width: {{ $skill['score'] }}%"></div>
                    <div class="absolute inset-y-0 w-0.5 rounded-full bg-slate-400 dark:bg-slate-500" style="left: {{ $skill['target'] }}%"></div>
                </div>
                @if ($gap > 0)
                    <p class="panel-muted text-[10px] leading-tight">{{ __('panel.skill_radar.gap', ['points' => $gap]) }}</p>
                @else
                    <p class="text-[10px] leading-tight text-emerald-600 dark:text-emerald-400">{{ __('panel.skill_radar.met') }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</div>
