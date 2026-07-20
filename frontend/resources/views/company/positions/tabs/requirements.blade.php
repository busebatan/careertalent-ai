@php
    $draft = collect($positionDetail['criteria_versions'] ?? [])->firstWhere('status', 'draft');
    $active = $positionDetail['active_criteria_version'] ?? null;
    $ats = $positionDetail['ats_config'] ?? [];
    $analysis = collect($positionDetail['ai_analyses'] ?? [])->sortByDesc('created_at')->first();
    $findingLabels = [
        'ambiguous_requirements' => 'Belirsiz şartlar',
        'contradictions' => 'Birbiriyle çelişen gereksinimler',
        'excessive_experience_expectations' => 'Gereğinden yüksek deneyim beklentisi',
        'weakly_related_requirements' => 'İşle ilgisi zayıf şartlar',
        'measurable_skills' => 'Değerlendirmede ölçülmesi gereken yetenekler',
    ];
@endphp
<div class="space-y-6">
    <section class="grid gap-5 lg:grid-cols-3">
        @foreach(['must_have_skills' => 'must_have', 'preferred_skills' => 'preferred', 'learnable_skills' => 'learnable'] as $field => $label)
            <article class="panel-card p-6"><h2 class="text-lg font-semibold">{{ __('company_positions.sections.'.$label) }}</h2><ul class="mt-4 space-y-2 text-sm">@forelse(($position[$field] ?? []) as $skill)<li class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ $skill }}</li>@empty<li class="panel-muted">—</li>@endforelse</ul></article>
        @endforeach
    </section>

    <section class="panel-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-lg font-semibold">{{ __('company_positions.ats.title') }}</h2><p class="panel-muted mt-1 text-sm">{{ __('company_positions.ats.rule') }}</p></div>@if(in_array('ats_config.view', $permissions, true))<a class="company-btn-secondary" href="{{ route('company.ats') }}">{{ __('company_positions.ats.nav') }}</a>@endif</div>
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <div><h3 class="text-sm font-semibold">{{ __('company_positions.fields.ats_terms') }}</h3><ul class="mt-3 space-y-2 text-sm">@forelse(($ats['effective_terms'] ?? []) as $term)<li class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-900">{{ $term }}</li>@empty<li class="panel-muted">—</li>@endforelse</ul></div>
            <div><h3 class="text-sm font-semibold">Kurum notları</h3><p class="panel-muted mt-3 whitespace-pre-line text-sm">{{ $ats['organization_notes'] ?? '—' }}</p></div>
            <div><h3 class="text-sm font-semibold">Pozisyon notları</h3><p class="panel-muted mt-3 whitespace-pre-line text-sm">{{ $ats['position_notes'] ?? '—' }}</p></div>
        </div>
    </section>

    <section class="panel-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 class="text-lg font-semibold">{{ __('company_positions.ai.title') }}</h2><p class="panel-muted mt-1 max-w-3xl text-sm">{{ __('company_positions.ai.approval_rule') }}</p></div>
            <div class="flex items-center gap-2">@if($analysis)<span class="company-context-card rounded-full px-3 py-1 text-xs font-semibold" data-position-analysis data-status="{{ $analysis['status'] }}" data-status-url="{{ route('company.positions.analysis-status', ['position' => $position['id'], 'analysis' => $analysis['id']]) }}">AI: {{ $analysis['status'] }}</span>@endif @if($active)<span class="company-context-card rounded-full px-3 py-1 text-xs font-semibold">{{ __('company_positions.sections.score_version') }} {{ $active['version_number'] }}</span>@endif</div>
        </div>
        @if(!$draft)
            <div class="mt-5 rounded-xl border border-dashed border-slate-300 p-6 text-center dark:border-slate-700"><p class="panel-muted">{{ __('company_positions.ai.empty') }}</p>@if($canWrite)<form class="mt-4" method="post" action="{{ route('company.positions.analyze', ['position' => $position['id']]) }}">@csrf<button class="company-btn-primary" type="submit">{{ __('company_positions.ai.analyze') }}</button></form>@endif</div>
        @else
            <div class="mt-5 rounded-2xl border border-amber-500/30 bg-amber-500/[0.06] p-5">
                <p class="text-sm font-bold text-amber-700 dark:text-amber-300">{{ __('company_positions.ai.draft_waiting') }} · v{{ $draft['version_number'] }}</p>
                <h3 class="mt-4 font-semibold">{{ __('company_positions.ai.findings') }}</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">@foreach($findingLabels as $key => $label)<div class="rounded-lg bg-white/60 p-3 dark:bg-slate-950/50"><h4 class="text-sm font-semibold">{{ $label }}</h4><ul class="panel-muted mt-2 space-y-1 text-sm">@forelse(($draft['ai_suggestions'][$key] ?? []) as $finding)<li>{{ $finding }}</li>@empty<li>—</li>@endforelse</ul></div>@endforeach</div>
                <div class="mt-5"><h3 class="font-semibold">{{ __('company_positions.sections.weights') }}</h3><ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">@forelse(($draft['ai_suggestions']['recommended_weights'] ?? []) as $name => $weight)<li class="flex justify-between gap-4 rounded-lg bg-white/60 px-3 py-2 dark:bg-slate-950/50"><span>{{ $name }}</span><strong>%{{ $weight }}</strong></li>@empty<li class="panel-muted">—</li>@endforelse</ul></div>
                @if($canWrite)
                    <form class="mt-6" method="post" action="{{ route('company.positions.criteria.update', ['position' => $position['id'], 'criteria' => $draft['id']]) }}">@csrf @method('PATCH')<label class="text-sm font-semibold">Onay öncesi ölçüt taslağı<textarea class="panel-input-block mt-2 min-h-64 font-mono text-xs" name="criteria_json" required>{{ old('criteria_json', json_encode($draft['criteria'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) }}</textarea></label><button class="company-btn-secondary mt-3" type="submit">Ölçüt taslağını kaydet</button></form>
                    <form class="mt-3" method="post" action="{{ route('company.positions.criteria.approve', ['position' => $position['id'], 'criteria' => $draft['id']]) }}">@csrf<button class="company-btn-primary" type="submit">{{ __('company_positions.ai.approve') }}</button></form>
                @endif
            </div>
        @endif
    </section>
</div>
