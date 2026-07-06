@extends('app.layouts.app')

@section('title', __('panel.skill_passport.title'))

@section('content')
<div class="mx-auto max-w-6xl" x-data="{ items: {{ Js::from($passport['items']) }}, newSkill: '', newEvidence: '', addEvidence() { if (!this.newSkill.trim() || !this.newEvidence.trim()) return; this.items.push({ skill: this.newSkill, level: 'Demo', evidence: this.newEvidence, type: 'Portfolio', status: 'review', impact: '{{ __('panel.skill_passport.new_impact') }}' }); this.newSkill = ''; this.newEvidence = ''; } }">
    <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="mb-1 text-2xl font-bold">{{ __('panel.skill_passport.title') }}</h1>
            <p class="text-slate-600 dark:text-slate-400">{{ __('panel.skill_passport.subtitle') }}</p>
        </div>
        <a href="{{ route('panel.cv-builder') }}" class="panel-btn-secondary text-sm">{{ __('panel.skill_passport.update_cv') }}</a>
    </header>

    <section class="mb-8 grid gap-4 md:grid-cols-3">
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.skill_passport.score') }}</p>
            <p class="text-4xl font-bold text-emerald-600 dark:text-emerald-400">%{{ $passport['score'] }}</p>
            <div class="mt-3 h-2 rounded-full bg-slate-200 dark:bg-slate-800"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $passport['score'] }}%"></div></div>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.skill_passport.verified') }}</p>
            <p class="text-4xl font-bold">{{ $passport['verified'] }}/{{ $passport['total'] }}</p>
            <p class="panel-muted mt-1 text-xs">{{ __('panel.skill_passport.verified_hint') }}</p>
        </div>
        <div class="panel-card p-6">
            <p class="panel-muted text-sm">{{ __('panel.skill_passport.gaps') }}</p>
            <ul class="mt-2 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                @foreach ($passport['gaps'] as $gap)
                    <li>• {{ $gap }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-3">
            <template x-for="item in items" :key="item.skill + item.evidence">
                <article class="panel-card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold" x-text="item.skill"></h2>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300" x-text="item.level"></span>
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="item.status === 'verified' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : (item.status === 'review' ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-red-500/15 text-red-700 dark:text-red-300')" x-text="item.status"></span>
                            </div>
                            <p class="text-sm text-slate-800 dark:text-slate-200" x-text="item.evidence"></p>
                            <p class="panel-muted mt-1 text-xs" x-text="item.impact"></p>
                        </div>
                        <span class="rounded-xl border border-slate-200 px-3 py-1 text-xs dark:border-slate-700" x-text="item.type"></span>
                    </div>
                </article>
            </template>
        </div>

        <form class="panel-card h-fit space-y-4 p-5" @submit.prevent="addEvidence()">
            <h2 class="font-semibold">{{ __('panel.skill_passport.add_title') }}</h2>
            <label class="block text-sm">
                <span class="mb-1 block text-xs text-slate-500">{{ __('panel.skill_passport.skill_label') }}</span>
                <input x-model="newSkill" class="w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="SQL, Power BI...">
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs text-slate-500">{{ __('panel.skill_passport.evidence_label') }}</span>
                <textarea x-model="newEvidence" rows="4" class="w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="GitHub linki, proje çıktısı, sertifika..."></textarea>
            </label>
            <button class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('panel.skill_passport.add_button') }}</button>
            <p class="panel-muted text-xs">{{ __('panel.skill_passport.demo_note') }}</p>
        </form>
    </section>
</div>
@endsection
