@extends('app.layouts.app')

@section('title', __('panel.skill_passport.title'))

@section('content')
<div class="mx-auto max-w-6xl"
    x-data="skillPassport({{ Js::from($passport['items']) }}, @js(route('panel.tasks.evidence', ['taskId' => '__TASK_ID__'])), @js(route('panel.tasks.status', ['taskId' => '__TASK_ID__'])), @js([
        'link' => __('panel.skill_passport.evidence_kind_link'),
        'file' => __('panel.skill_passport.evidence_kind_file'),
        'submit' => __('panel.skill_passport.add_button'),
        'clear' => __('panel.skill_passport.clear_evidence'),
        'target_required' => __('panel.skill_passport.target_required'),
        'evidence_file' => __('panel.skill_passport.evidence_submitted_file'),
        'status' => [
            'verified' => __('panel.skill_passport.status_verified'),
            'review' => __('panel.skill_passport.status_review'),
            'revision' => __('panel.skill_passport.status_rejected'),
            'missing' => __('panel.skill_passport.status_missing'),
            'waiting' => __('panel.skill_passport.status_waiting'),
        ],
    ]), {{ Js::from([
        'hasTarget' => ! empty($selectedTarget['id'] ?? null),
        'targetId' => $selectedTarget['id'] ?? null,
        'skillEvidenceUrl' => route('panel.skill-passport.evidence'),
        'skillEvidenceClearUrl' => route('panel.skill-passport.evidence.clear'),
    ]) }})">

    <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="mb-1 text-2xl font-bold">{{ __('panel.skill_passport.title') }}</h1>
            <p class="text-slate-600 dark:text-slate-400">{{ __('panel.skill_passport.subtitle') }}</p>
        </div>
        <a href="{{ route('panel.cv-builder') }}" class="panel-btn-secondary text-sm">{{ __('panel.skill_passport.update_cv') }}</a>
    </header>

    @if (empty($selectedTarget['id'] ?? null))
        <p class="mb-4 rounded-xl border border-sky-500/30 bg-sky-500/10 p-4 text-sm text-sky-900 dark:text-sky-100" role="status">
            {{ __('panel.skill_passport.target_required') }}
            <a href="{{ route('panel.roadmap') }}" class="ml-1 font-medium text-emerald-700 underline dark:text-emerald-300">{{ __('panel.skill_passport.go_roadmap') }}</a>
        </p>
    @endif

    @if (! empty($careerEngineError))
        <p class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200" role="status">{{ $careerEngineError }}</p>
    @endif

    <section class="mb-8 grid gap-4 md:grid-cols-3">
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.skill_passport.score') }}</p>
            <p class="text-4xl font-bold text-emerald-600 dark:text-emerald-400" x-text="'%' + passportScore()">%{{ $passport['score'] }}</p>
            <div class="mt-3 h-2 rounded-full bg-slate-200 dark:bg-slate-800">
                <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" :style="'width: ' + passportScore() + '%'"></div>
            </div>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.skill_passport.verified') }}</p>
            <p class="text-4xl font-bold" x-text="verifiedCount() + '/' + items.length">{{ $passport['verified'] }}/{{ $passport['total'] }}</p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.skill_passport.verified_hint') }}</p>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.skill_passport.gaps') }}</p>
            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                @forelse ($passport['gaps'] as $gap)
                    <li>{{ $gap }}</li>
                @empty
                    <li class="panel-muted">{{ __('panel.skill_passport.no_gaps') }}</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-3">
            <p class="panel-muted text-sm">{{ __('panel.skill_passport.click_skill_hint') }}</p>

            <template x-for="item in items" :key="item.skill">
                <article class="panel-card cursor-pointer p-5 transition hover:border-emerald-500/40"
                    :class="selectedSkill === item.skill ? selectedStatusClass(item.status) : ''"
                    :data-status="item.status"
                    @click="selectSkill(item.skill)">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold" x-text="item.skill"></h2>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300" x-text="item.level"></span>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(item.status)" x-text="statusLabel(item.status)"></span>
                            </div>
                            <p class="text-sm text-slate-800 dark:text-slate-200" x-text="item.evidence_ref || item.evidence"></p>
                            <p class="panel-muted mt-1 text-xs" x-text="item.impact"></p>
                            <p x-show="item.status === 'missing'" x-cloak class="mt-2 rounded-lg border border-red-500/30 bg-red-500/10 p-2 text-xs font-semibold text-red-700 dark:text-red-200" role="alert">
                                {{ __('panel.skill_passport.status_missing') }}
                            </p>
                            <p x-show="item.status === 'revision'" x-cloak class="mt-2 rounded-lg border border-red-500/30 bg-red-500/10 p-2 text-xs text-red-700 dark:text-red-200" role="alert">
                                <span class="font-semibold">{{ __('panel.skill_passport.status_missing') }}.</span>
                                <span x-text="item.feedback || '{{ __('panel.skill_passport.rejected_hint') }}'"></span>
                            </p>
                        </div>
                        <span class="rounded-xl border border-slate-200 px-3 py-1 text-xs dark:border-slate-700" x-text="item.type"></span>
                    </div>
                </article>
            </template>

            <p x-show="!items.length" x-cloak class="panel-card border-dashed p-6 text-center text-sm text-slate-500">
                {{ __('panel.skill_passport.empty_analysis') }}
            </p>
        </div>

        <aside class="panel-card h-fit space-y-4 p-5 lg:sticky lg:top-6">
            <template x-if="selectedItem()">
                <div class="space-y-4">
                    <div>
                        <p class="panel-muted text-xs uppercase tracking-wide">{{ __('panel.skill_passport.skill_label') }}</p>
                        <h2 class="text-lg font-semibold" x-text="selectedItem().skill"></h2>
                        <p class="panel-muted mt-1 text-sm" x-text="selectedItem().impact"></p>
                    </div>

                    <template x-if="selectedItem().status === 'verified'">
                        <p class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                            {{ __('panel.skill_passport.verified_message') }}
                        </p>
                    </template>

                    <p x-show="selectedItem().status === 'revision'" x-cloak class="rounded-xl border border-red-500/40 bg-red-500/10 p-3 text-xs text-red-700 dark:text-red-200">
                        <span class="font-semibold">{{ __('panel.skill_passport.rejected_message') }}</span>
                        <span class="mt-1 block" x-text="selectedItem().feedback || '{{ __('panel.skill_passport.rejected_hint') }}'"></span>
                    </p>

                    <p x-show="selectedItem().status === 'review'" x-cloak class="rounded-xl bg-sky-500/10 px-4 py-3 text-xs text-sky-800 dark:text-sky-200">
                        {{ __('panel.skill_passport.status_review') }}
                    </p>

                    <template x-if="canUpload(selectedItem())">
                        <div class="space-y-3">
                            <p class="text-sm font-medium">{{ __('panel.skill_passport.add_title') }}</p>
                            <p x-show="selectedItem().task_title" class="panel-muted text-xs" x-text="selectedItem().task_title"></p>
                            <p class="panel-muted text-xs">{{ __('panel.skill_passport.evidence_types_hint') }}</p>
                            <p x-show="error" x-cloak class="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-700 dark:text-red-200" x-text="error" role="alert"></p>
                            <form class="space-y-3" @submit.prevent="submitEvidence()">
                                <select x-model="evidence.kind" class="panel-input w-full">
                                    <option value="link">{{ __('panel.skill_passport.evidence_kind_link') }}</option>
                                    <option value="file">{{ __('panel.skill_passport.evidence_kind_file') }}</option>
                                </select>
                                <input x-show="evidence.kind === 'link'" x-model="evidence.url" type="url" class="panel-input-block" placeholder="https://github.com/... veya sertifika / portföy linki">
                                <input x-show="evidence.kind === 'file'" @change="evidence.file = $event.target.files[0] || null" type="file" class="panel-input-block" accept="application/pdf,image/png,image/jpeg">
                                <div class="flex flex-col gap-2">
                                    <button type="submit" :disabled="submitting" class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60">
                                        <span x-show="!submitting">{{ __('panel.skill_passport.add_button') }}</span>
                                        <span x-show="submitting" x-cloak>…</span>
                                    </button>
                                    <button type="button" x-show="canClear(selectedItem())" @click="clearEvidence()" :disabled="clearing" class="panel-btn-danger w-full text-sm">
                                        <span x-show="!clearing">{{ __('panel.skill_passport.clear_evidence') }}</span>
                                        <span x-show="clearing" x-cloak>…</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </template>

                    <template x-if="selectedItem() && !canUpload(selectedItem()) && selectedItem().status !== 'verified' && !hasTarget">
                        <div class="space-y-3">
                            <p class="text-sm">{{ __('panel.skill_passport.target_required') }}</p>
                            <a href="{{ route('panel.roadmap') }}" class="panel-btn-secondary inline-flex text-sm">{{ __('panel.skill_passport.go_roadmap') }}</a>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="!selectedItem()">
                <p class="panel-muted text-sm">{{ __('panel.skill_passport.select_skill_hint') }}</p>
            </template>
        </aside>
    </section>
</div>
@endsection
