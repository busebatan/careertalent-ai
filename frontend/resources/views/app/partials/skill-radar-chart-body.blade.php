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
