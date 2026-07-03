{{-- Layout B: panel önizleme kartı (hero sağ) --}}
<div class="relative overflow-hidden rounded-2xl border border-slate-700/80 bg-slate-900 shadow-2xl shadow-emerald-950/20">
    <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-emerald-500/10 blur-2xl" aria-hidden="true"></div>
    <div class="border-b border-slate-800 px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-slate-400">{{ __('marketing.preview.readiness') }}</span>
            <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Live</span>
        </div>
    </div>
    <div class="space-y-4 p-5">
        <div>
            <p class="text-4xl font-bold tabular-nums text-emerald-400">67%</p>
            <p class="mt-1 text-sm font-medium text-white">{{ __('marketing.preview.role') }}</p>
            <p class="text-xs text-slate-500">{{ __('marketing.preview.from_gap') }}</p>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-800">
            <div class="h-full w-[67%] rounded-full bg-gradient-to-r from-emerald-600 to-emerald-400"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('marketing.preview.this_week') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-200">{{ __('marketing.preview.tasks') }}</p>
            </div>
            <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-3">
                <div class="flex items-start justify-between gap-1">
                    <p class="text-[10px] uppercase tracking-wide text-amber-200/80">{{ __('marketing.preview.gap_label') }}</p>
                    <span class="flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500/20 px-1 text-[10px] font-bold tabular-nums text-amber-400" aria-hidden="true">3</span>
                </div>
                <p class="mt-1 text-sm font-semibold leading-tight text-amber-300">{{ __('marketing.preview.gap_skills') }}</p>
            </div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-3">
            <p class="mb-2 text-xs font-semibold text-slate-300">{{ __('marketing.preview.weekly_tasks') }}</p>
            <ul class="space-y-2 text-xs text-slate-400">
                <li class="flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    {{ __('marketing.preview.task_1') }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-600"></span>
                    {{ __('marketing.preview.task_2') }}
                </li>
                <li class="flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-600"></span>
                    {{ __('marketing.preview.task_3') }}
                </li>
            </ul>
        </div>
    </div>
</div>
