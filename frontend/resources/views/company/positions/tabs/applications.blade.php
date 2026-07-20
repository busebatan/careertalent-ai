@php
    $applications = $positionDetail['applications'] ?? [];
    $canManageApplications = in_array('applications.write', $permissions, true);
    $stages = ['new','assessment_pending','assessment_in_progress','technical_review','shortlisted','interview','offer','hired','rejected','withdrawn'];
    $completionStatuses = [
        'not_requested' => __('company.applications.completion_not_requested'),
        'queued' => __('company.applications.completion_queued'),
        'processing' => __('company.applications.completion_processing'),
        'completed' => __('company.applications.completion_completed'),
        'failed' => __('company.applications.completion_failed'),
    ];
    $missingDocumentLabels = ['cv' => __('company.applications.missing_document_cv')];
@endphp
<section class="panel-card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 p-6 dark:border-slate-800"><div><h2 class="text-lg font-semibold">{{ __('company_positions.sections.candidate_list') }}</h2><p class="panel-muted mt-1 text-sm">{{ count($applications) }} {{ __('company_positions.metrics.applications') }}</p></div></div>
    @if($applications === [])
        <p class="panel-muted p-8 text-center">{{ __('company.applications.empty') }}</p>
    @else
        <div class="overflow-x-auto"><table class="min-w-[1100px] w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-900"><tr><th class="px-5 py-4">{{ __('company.applications.candidate') }}</th><th class="px-4 py-4">{{ __('company.applications.stage') }}</th><th class="px-4 py-4">{{ __('company.applications.completion_status') }}</th><th class="px-4 py-4">{{ __('company.applications.missing_documents') }}</th><th class="px-5 py-4">{{ __('company.applications.last_action_at') }}</th>@if($canManageApplications)<th class="px-5 py-4">{{ __('company.applications.review') }}</th>@endif</tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            @foreach($applications as $application)
                @php
                    $completionStatus = $application['completion_status'] ?? null;
                    $completionLabel = is_string($completionStatus)
                        ? ($completionStatuses[$completionStatus] ?? __('company.applications.completion_unknown'))
                        : '—';
                    $missingDocuments = collect($application['missing_documents'] ?? [])
                        ->filter(fn ($document) => is_string($document) && $document !== '')
                        ->map(fn ($document) => $missingDocumentLabels[$document] ?? __('company.applications.missing_document_unknown'))
                        ->implode(', ');
                @endphp
                <tr class="align-top">
                    <td class="px-5 py-4"><strong>{{ $application['candidate_name'] }}</strong><span class="panel-muted block text-xs">{{ $application['candidate_email'] ?? '' }}</span></td>
                    <td class="px-4 py-4">{{ __('company.applications.stage_'.($application['stage'] ?? 'new')) }}</td>
                    <td class="px-4 py-4">{{ $completionLabel }}</td>
                    <td class="px-4 py-4">{{ $missingDocuments !== '' ? $missingDocuments : '—' }}</td>
                    <td class="px-5 py-4">{{ !empty($application['last_action_at']) ? \Carbon\Carbon::parse($application['last_action_at'])->format('d.m.Y H:i') : '—' }}</td>
                    @if($canManageApplications)
                        <td class="px-5 py-4"><details><summary class="company-accent-text cursor-pointer font-semibold">{{ __('company.applications.review_summary') }}</summary><form class="mt-3 grid min-w-72 gap-2" method="post" action="{{ route('company.positions.applications.update', ['position' => $position['id'], 'application' => $application['id']]) }}">@csrf @method('PATCH')<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><select class="panel-input-block" name="stage">@foreach($stages as $stage)<option value="{{ $stage }}" @selected(($application['stage'] ?? 'new')===$stage)>{{ __('company.applications.stage_'.$stage) }}</option>@endforeach</select><input class="panel-input-block" name="decision" placeholder="{{ __('company.applications.decision_placeholder') }}"><textarea class="panel-input-block min-h-20" name="note" placeholder="{{ __('company.applications.note_placeholder') }}"></textarea><button class="company-btn-primary" type="submit">{{ __('company.applications.save') }}</button></form></details></td>
                    @endif
                </tr>
            @endforeach
        </tbody></table></div>
    @endif
</section>
