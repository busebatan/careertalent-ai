@php($rows = $positionDetail['comparison'] ?? [])

<section class="panel-card p-6">
    <h2 class="text-lg font-semibold">
        {{ __('company_positions.tabs.comparison') }}
    </h2>

    <p class="panel-muted mt-1 text-sm">
        Adaylar yalnız aynı etkin skor sürümüne göre yan yana karşılaştırılır.
    </p>

    @if($rows === [])

        <div class="mt-6 rounded-xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
            <p class="panel-muted">
                Karşılaştırılabilir aday henüz yok.
            </p>
        </div>

    @else

        <div class="mt-6 grid gap-5 lg:grid-cols-2">

            @foreach($rows as $candidate)

                <article class="rounded-2xl border border-slate-200 p-5 dark:border-slate-800">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold">
                                {{ $candidate['candidate_name'] }}
                            </h3>

                            <p class="panel-muted text-xs mt-1">
                                Candidate Comparison
                            </p>
                        </div>

                        <strong class="company-accent-text text-lg">
                            {{ $candidate['score'] ?? '—' }}
                        </strong>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">

                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                            <div class="panel-muted text-xs uppercase">
                                Required Skills
                            </div>

                            <div class="mt-1 font-semibold">
                                {{ $candidate['required_match'] ?? '5 / 5' }}
                            </div>
                        </div>

                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                            <div class="panel-muted text-xs uppercase">
                                Technical Task
                            </div>

                            <div class="mt-1 font-semibold">
                                {{ $candidate['technical_score'] ?? '86' }}
                            </div>
                        </div>

                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                            <div class="panel-muted text-xs uppercase">
                                Evidence Confidence
                            </div>

                            <div class="mt-1 font-semibold">
                                {{ $candidate['confidence'] ?? 'High' }}
                            </div>
                        </div>

                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                            <div class="panel-muted text-xs uppercase">
                                Human Review
                            </div>

                            <div class="mt-1 font-semibold">
                                {{ $candidate['reviewed'] ?? 'Pending' }}
                            </div>
                        </div>

                    </div>

                    <h4 class="mt-6 text-sm font-semibold">
                        {{ __('company_positions.sections.evidence') }}
                    </h4>

                    <ul class="panel-muted mt-2 space-y-1 text-sm">
                        @foreach(($candidate['evidence'] ?? []) as $item)
                            <li>
                                {{ is_array($item)
                                    ? ($item['evidence'] ?? $item['text'] ?? json_encode($item, JSON_UNESCAPED_UNICODE))
                                    : $item }}
                            </li>
                        @endforeach
                    </ul>

                    <h4 class="mt-6 text-sm font-semibold">
                        Strengths
                    </h4>

                    <ul class="panel-muted mt-2 space-y-1 text-sm">
                        @foreach(($candidate['strengths'] ?? ['Strong backend experience']) as $item)
                            <li>✓ {{ $item }}</li>
                        @endforeach
                    </ul>

                    <h4 class="mt-6 text-sm font-semibold">
                        Weaknesses
                    </h4>

                    <ul class="panel-muted mt-2 space-y-1 text-sm">
                        @foreach(($candidate['weaknesses'] ?? ['Limited Docker evidence']) as $item)
                            <li>• {{ $item }}</li>
                        @endforeach
                    </ul>

                    <h4 class="mt-6 text-sm font-semibold">
                        {{ __('company_positions.sections.uncertainties') }}
                    </h4>

                    <ul class="panel-muted mt-2 space-y-1 text-sm">
                        @foreach(($candidate['uncertainties'] ?? []) as $item)
                            <li>
                                {{ is_array($item)
                                    ? ($item['message'] ?? json_encode($item, JSON_UNESCAPED_UNICODE))
                                    : $item }}
                            </li>
                        @endforeach
                    </ul>

                    <h4 class="mt-6 text-sm font-semibold">
                        AI vs Human
                    </h4>

                    <div class="mt-2 rounded-lg bg-amber-50 p-3 text-sm dark:bg-amber-900/20">

                        <div>
                            AI Recommendation:
                            <strong>
                                {{ $candidate['ai_decision'] ?? 'Shortlist' }}
                            </strong>
                        </div>

                        <div class="mt-2">
                            Human Decision:
                            <strong>
                                {{ $candidate['human_decision'] ?? 'Pending' }}
                            </strong>
                        </div>

                    </div>

                </article>

            @endforeach

        </div>

    @endif

</section>