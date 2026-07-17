@php
    $tierOrder = ['ready', 'near', 'reachable'];
    $grouped = collect($careerLadder)->groupBy('tier');
    $selectedLadderRoleId = null;
    if (! empty($selectedTarget['title'])) {
        foreach ($careerLadder as $ladderRole) {
            if (($ladderRole['title'] ?? '') === ($selectedTarget['title'] ?? '')) {
                $selectedLadderRoleId = (string) ($ladderRole['id'] ?? '');
                break;
            }
        }
    }
    $hasLadderSelection = $selectedLadderRoleId !== null;
@endphp

<section
    id="kariyer-merdiveni"
    class="mb-10"
    x-data="{ expandedRoles: {} }"
    data-selected-role-id="{{ $selectedLadderRoleId ?? '' }}"
>
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
                            @php
                                $roleKey = (string) ($role['id'] ?? $role['title']);
                                $roleTitle = (string) ($role['title'] ?? $roleKey);
                                $isRoleSelected = $hasLadderSelection && $selectedLadderRoleId === $roleKey;
                                $isRoleCollapsed = $hasLadderSelection && ! $isRoleSelected;
                            @endphp
                            <article
                                data-career-role="{{ $roleKey }}"
                                data-swot-default-open="{{ ! $hasLadderSelection || $isRoleSelected ? 'true' : 'false' }}"
                                data-swot-toggleable="{{ $isRoleCollapsed ? 'true' : 'false' }}"
                                @class([
                                    'panel-card',
                                    'panel-card-ladder-selected p-5' => $isRoleSelected,
                                    'panel-card-ladder-collapsed p-4' => $isRoleCollapsed,
                                    'panel-card-ladder-active p-5' => ! $hasLadderSelection,
                                ])
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <h4 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $role['title'] }}</h4>
                                            <span class="rounded-md bg-emerald-950/50 px-2 py-0.5 text-xs font-medium text-emerald-300">
                                                %{{ $role['readiness'] }}
                                            </span>
                                        </div>
                                        @if (! $isRoleCollapsed)
                                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                                {{ __('panel.career_ladder.gaps', ['count' => $role['gap_count']]) }}:
                                                {{ $role['gaps_summary'] }}
                                            </p>
                                            @if ($role['weeks_estimate'])
                                                <p class="mt-1 text-xs text-slate-500">{{ __('panel.career_ladder.estimate') }}: {{ $role['weeks_estimate'] }}</p>
                                            @endif
                                        @else
                                            <div x-show="expandedRoles[@js($roleKey)]" x-cloak>
                                                <p class="text-sm text-slate-600 dark:text-slate-400">
                                                    {{ __('panel.career_ladder.gaps', ['count' => $role['gap_count']]) }}:
                                                    {{ $role['gaps_summary'] }}
                                                </p>
                                                @if ($role['weeks_estimate'])
                                                    <p class="mt-1 text-xs text-slate-500">{{ __('panel.career_ladder.estimate') }}: {{ $role['weeks_estimate'] }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 flex-wrap gap-2">
                                        <form method="POST" action="{{ route('panel.career-ladder.select') }}">
                                            @csrf
                                            <input type="hidden" name="mode" value="role">
                                            <input type="hidden" name="role_id" value="{{ $role['id'] }}">
                                            @if ($isRoleSelected)
                                                <button
                                                    type="button"
                                                    disabled
                                                    aria-current="true"
                                                    class="panel-btn-ladder-selected cursor-default rounded-xl px-4 py-2 text-sm font-medium text-white"
                                                >
                                                    {{ __('panel.career_ladder.role_selected') }}
                                                </button>
                                            @else
                                                <button
                                                    type="submit"
                                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                                                >
                                                    {{ __('panel.career_ladder.select_role') }}
                                                </button>
                                            @endif
                                        </form>
                                        @if ($isRoleCollapsed)
                                            <button
                                                type="button"
                                                @click="expandedRoles[@js($roleKey)] = !expandedRoles[@js($roleKey)]"
                                                class="panel-outline-btn"
                                                :aria-expanded="Boolean(expandedRoles[@js($roleKey)])"
                                            >
                                                <span x-text="expandedRoles[@js($roleKey)] ? @js(__('panel.career_ladder.swot_hide')) : @js(__('panel.career_ladder.swot_show'))"></span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @if ($isRoleSelected)
                                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                        @include('app.partials.career-ladder-swot', ['role' => $role])
                                    </div>
                                @elseif (! $hasLadderSelection)
                                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                        @include('app.partials.career-ladder-swot', ['role' => $role])
                                    </div>
                                @else
                                    <div x-show="expandedRoles[@js($roleKey)]" x-cloak class="mt-4 grid gap-2 sm:grid-cols-2">
                                        @include('app.partials.career-ladder-swot', ['role' => $role])
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
