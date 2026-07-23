@extends('company.layouts.app')

@section('title', __('company_positions.ats.title'))

@section('content')

@php
    $terms = collect($atsConfig['terms'] ?? [])
        ->map(fn($value, $key) => is_int($key) ? $value : $key.'='.$value)
        ->implode("\n");

    $canWrite = in_array(
        'ats_config.write',
        $companyMembership['permissions'] ?? [],
        true
    );
@endphp

<div class="mx-auto max-w-5xl">

    <header class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight">
            {{ __('company_positions.ats.title') }}
        </h1>

        <p class="panel-muted mt-2 max-w-3xl">
            {{ __('company_positions.ats.subtitle') }}
        </p>
    </header>

    <section class="panel-card p-6">

        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/[0.06] p-4 text-sm text-emerald-800 dark:text-emerald-200">
            {{ __('company_positions.ats.rule') }}
        </div>

        <form
            class="mt-6 grid gap-6 md:grid-cols-2"
            method="post"
            action="{{ route('company.ats.update') }}"
        >

            @csrf
            @method('PATCH')

            <label class="text-sm">

                {{ __('company_positions.fields.ats_provider') }}

                <select
                    class="panel-input-block mt-2"
                    name="provider"
                    @disabled(!$canWrite)
                >

                    @foreach([
                        'generic'=>'Genel / Özel ATS',
                        'greenhouse'=>'Greenhouse',
                        'lever'=>'Lever',
                        'workable'=>'Workable',
                        'sap_successfactors'=>'SAP SuccessFactors',
                        'teamtailor'=>'Teamtailor',
                        'custom'=>'Kurum İçi'
                    ] as $value=>$label)

                        <option
                            value="{{ $value }}"
                            @selected(old('provider', $atsConfig['provider'] ?? 'generic') === $value)
                        >
                            {{ $label }}
                        </option>

                    @endforeach

                </select>

            </label>

            <label class="text-sm">

                {{ __('company_positions.fields.ats_system_name') }}

                <input
                    class="panel-input-block mt-2"
                    name="system_name"
                    value="{{ old('system_name', $atsConfig['system_name'] ?? '') }}"
                    @disabled(!$canWrite)
                >

            </label>

            <label class="text-sm">

                {{ __('company_positions.fields.ats_terms') }}

                <textarea
                    class="panel-input-block mt-2 min-h-72 font-mono text-sm"
                    name="terms"
                    @disabled(!$canWrite)
                >{{ old('terms', $terms) }}</textarea>

                <span class="panel-muted mt-2 block text-xs">
                    {{ __('company_positions.fields.ats_terms_hint') }}
                </span>

            </label>

            <label class="text-sm">

                {{ __('company_positions.fields.ats_notes') }}

                <textarea
                    class="panel-input-block mt-2 min-h-72"
                    name="notes"
                    @disabled(!$canWrite)
                >{{ old('notes', $atsConfig['notes'] ?? '') }}</textarea>

            </label>

            <label class="text-sm md:col-span-2">

                {{ __('company_positions.fields.candidate_analysis_instructions') }}

                <textarea
                    class="panel-input-block mt-2 min-h-40"
                    name="candidate_analysis_instructions"
                    @disabled(!$canWrite)
                >{{ old('candidate_analysis_instructions', $atsConfig['candidate_analysis_instructions'] ?? '') }}</textarea>

                <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-900">

                    <h3 class="font-semibold">
                        AI Candidate Analysis
                    </h3>

                    <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600 dark:text-slate-300">
                        <li>Explain how candidate skills should be evaluated.</li>
                        <li>Specify required evidence before recommending candidates.</li>
                        <li>Define company-specific hiring priorities.</li>
                        <li>Describe rejection conditions if needed.</li>
                    </ul>

                </div>

            </label>

            @if($canWrite)

                <div class="md:col-span-2 flex justify-end">

                    <button
                        class="company-btn-primary"
                        type="submit"
                    >
                        {{ __('company_positions.actions.save') }}
                    </button>

                </div>

            @endif

        </form>

    </section>

</div>

@endsection