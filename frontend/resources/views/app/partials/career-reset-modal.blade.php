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
            <button type="button" @click="{{ $resetAction }}" :disabled="resetWorking"
                class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-60"
                x-text="resetWorking ? @js(__('panel.skill_radar.reset_working')) : @js(__('panel.skill_radar.reset_confirm'))">
            </button>
        </div>
    </div>
</div>
