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
    <p class="panel-muted mt-1 text-xs">
        {{ __('panel.skill_radar.analysis_cv', ['name' => $skillRadar['file_name'] ?? $cvFileName ?? 'cv']) }}
        · {{ __('panel.skill_radar.analysis_source', ['source' => $sourceLabel]) }}
        · {{ __('panel.skill_radar.analyzed_at', ['date' => $analysisDate]) }}
        @if (! empty($showAnalysisId))
            · <span class="break-all font-mono">{{ __('panel.skill_radar.analysis_id', ['id' => $skillRadar['analysis_id'] ?? '—']) }}</span>
        @endif
        @if (! empty($showClearInline))
            · <button type="button" @click.stop="resetOpen = true"
                class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                {{ __('panel.skill_radar.clear_cv') }}
            </button>
        @elseif (! empty($showCvToolbar))
            · <button type="button" @click.stop="resetOpen = true"
                class="font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                {{ __('panel.skill_radar.clear_cv') }}
            </button>
        @endif
    </p>
</div>
