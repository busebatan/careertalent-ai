@php($config = $position['evaluation_config'] ?? [])

<div class="grid gap-6 lg:grid-cols-2">

    <section class="panel-card p-6">
        <h2 class="text-lg font-semibold">
            {{ __('company_positions.sections.tasks') }}
        </h2>

        <ul class="mt-4 space-y-2 text-sm">
            @forelse(($config['tasks'] ?? []) as $item)
                <li class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                    {{ is_array($item) ? ($item['title'] ?? json_encode($item, JSON_UNESCAPED_UNICODE)) : $item }}
                </li>
            @empty
                <li class="panel-muted">—</li>
            @endforelse
        </ul>
    </section>

    <section class="panel-card p-6">
        <h2 class="text-lg font-semibold">
            {{ __('company_positions.sections.human_review') }}
        </h2>

        <ul class="mt-4 space-y-2 text-sm">
            @forelse(($config['allowed_tools'] ?? []) as $item)
                <li class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                    {{ is_array($item) ? ($item['title'] ?? json_encode($item, JSON_UNESCAPED_UNICODE)) : $item }}
                </li>
            @empty
                <li class="panel-muted">—</li>
            @endforelse
        </ul>
    </section>

    <section class="panel-card p-6">
        <h2 class="text-lg font-semibold">
            {{ __('company_positions.sections.rubric') }}
        </h2>

        <p class="panel-muted mt-4 whitespace-pre-line text-sm">
            {{ $config['rubric'] ?? '—' }}
        </p>
    </section>

    <section class="panel-card p-6">

        <h2 class="text-lg font-semibold">
            Assessment Settings
        </h2>

        <dl class="mt-5 space-y-4 text-sm">

            <div class="flex justify-between">
                <dt class="panel-muted">Duration</dt>
                <dd class="font-semibold">
                    {{ $config['duration_minutes'] ?? '—' }} min
                </dd>
            </div>

            <div class="flex justify-between">
                <dt class="panel-muted">Success Threshold</dt>
                <dd class="font-semibold">
                    {{ isset($config['success_threshold']) ? '%'.$config['success_threshold'] : '—' }}
                </dd>
            </div>

            <div class="flex justify-between">
                <dt class="panel-muted">Human Review</dt>
                <dd class="font-semibold">
                    {{ ($config['human_review_required'] ?? true) ? 'Required' : 'Optional' }}
                </dd>
            </div>

            <div class="flex justify-between">
                <dt class="panel-muted">AI Policy</dt>
                <dd class="font-semibold">
                    {{ $config['ai_policy'] ?? 'Allowed with disclosure' }}
                </dd>
            </div>

            <div class="flex justify-between">
                <dt class="panel-muted">Reusable Result</dt>
                <dd class="font-semibold">
                    {{ ($config['reusable'] ?? false) ? 'Yes' : 'No' }}
                </dd>
            </div>

            <div class="flex justify-between">
                <dt class="panel-muted">Validity</dt>
                <dd class="font-semibold">
                    {{ $config['validity_days'] ?? 180 }} days
                </dd>
            </div>

        </dl>

    </section>

</div>