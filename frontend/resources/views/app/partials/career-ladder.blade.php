@php
    $tierOrder = ['ready', 'near', 'reachable'];
    $grouped = collect($careerLadder)->groupBy('tier');
@endphp

<section id="kariyer-merdiveni" class="mb-10">
    @empty($hideSectionHeader)
    <div class="mb-4">
        <h2 class="text-lg font-semibold">{{ __('panel.career_ladder.title') }}</h2>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('panel.career_ladder.subtitle') }}</p>
    </div>
    @endempty

    <div class="space-y-8">
        @foreach ($tierOrder as $tierKey)
            @if ($grouped->has($tierKey))
                <div>
                    <div class="mb-3 flex flex-wrap items-baseline gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-emerald-400">
                            {{ $careerTierMeta[$tierKey]['heading'] }}
                        </h3>
                        <span class="text-xs text-slate-500">{{ $careerTierMeta[$tierKey]['hint'] }}</span>
                    </div>
                    <div class="space-y-3">
                        @foreach ($grouped[$tierKey] as $role)
                            <article class="panel-card p-5"
                                x-data="{
                                    open: {{ $role['tier'] === 'near' ? 'true' : 'false' }},
                                    swotShow: @js(__('panel.career_ladder.swot_show')),
                                    swotHide: @js(__('panel.career_ladder.swot_hide'))
                                }">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <h4 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $role['title'] }}</h4>
                                            <span class="rounded-md bg-emerald-950/50 px-2 py-0.5 text-xs font-medium text-emerald-300">
                                                %{{ $role['readiness'] }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-600 dark:text-slate-400">
                                            {{ __('panel.career_ladder.gaps', ['count' => $role['gap_count']]) }}:
                                            {{ $role['gaps_summary'] }}
                                        </p>
                                        @if ($role['weeks_estimate'])
                                            <p class="mt-1 text-xs text-slate-500">{{ __('panel.career_ladder.estimate') }}: {{ $role['weeks_estimate'] }}</p>
                                        @endif
                                    </div>
                                    <button type="button" @click="open = !open" class="panel-outline-btn">
                                        <span x-text="open ? swotHide : swotShow"></span>
                                    </button>
                                </div>
                                <div x-show="open" x-cloak class="mt-4 grid gap-2 sm:grid-cols-2">
                                    <div class="panel-swot-cell">
                                        <p class="mb-1 text-xs font-medium text-emerald-400">Güçlü (S)</p>
                                        <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
                                            @foreach ($role['swot']['strengths'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="panel-swot-cell">
                                        <p class="mb-1 text-xs font-medium text-rose-400">Zayıf (W)</p>
                                        <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
                                            @foreach ($role['swot']['weaknesses'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="panel-swot-cell">
                                        <p class="mb-1 text-xs font-medium text-sky-400">Fırsat (O)</p>
                                        <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
                                            @foreach ($role['swot']['opportunities'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="panel-swot-cell">
                                        <p class="mb-1 text-xs font-medium text-amber-400">Tehdit (T)</p>
                                        <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
                                            @foreach ($role['swot']['threats'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
