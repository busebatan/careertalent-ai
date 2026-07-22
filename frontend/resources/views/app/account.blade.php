@extends('app.layouts.app')

@section('title', __('panel.nav.account'))

@section('content')
<div class="mx-auto max-w-3xl" x-data="{
    tab: window.location.hash === '#cv-yukle' ? 'cv' : (window.location.hash === '#abonelik' ? 'abonelik' : (window.location.hash === '#gizlilik' ? 'gizlilik' : @js($initialTab ?? 'profil'))),
    selectTab(nextTab) {
        this.tab = nextTab;
        if (nextTab === 'cv') {
            history.replaceState(null, '', '#cv-yukle');
        } else if (window.location.hash === '#cv-yukle') {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    },
}">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.nav.account') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.account.subtitle') }}</p>
    </header>

    <div class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-4 dark:border-slate-800">
        <button type="button" @click="selectTab('profil')"
            :class="tab === 'profil' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.profile.tab_profile') }}
        </button>
        <button type="button" @click="selectTab('giris')"
            :class="tab === 'giris' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.profile.tab_login') }}
        </button>
        <button type="button" @click="selectTab('cv')"
            :class="tab === 'cv' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.profile.tab_cv') }}
        </button>
        <button type="button" @click="selectTab('abonelik')"
            :class="tab === 'abonelik' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.account.tab_subscription') }}
        </button>
        <button type="button" @click="selectTab('gizlilik')"
            :class="tab === 'gizlilik' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.account.tab_privacy') }}
        </button>
    </div>

    <section x-show="tab === 'profil'" x-cloak class="space-y-6">
        <form class="panel-card space-y-4 p-6" @submit.prevent="save()"
            x-data="profileSocialLinks({{ Js::from($profile['social_links'] ?? []) }}, 'panel-profile-links', {{ Js::from($profile) }}, @js(route('panel.account.profile.update')))">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.name') }}</label>
                    <input type="text" x-model="profile.full_name"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.phone') }}</label>
                    <input type="tel" x-model="profile.phone"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.location') }}</label>
                    <input type="text" x-model="profile.location"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.headline') }}</label>
                    <input type="text" x-model="profile.headline"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.linkedin') }}</label>
                    <input type="url" x-model="profile.linkedin"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <div class="mb-2 flex items-end justify-between gap-3">
                        <div><label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('panel.profile.profile_links') }}</label><p class="mt-1 text-xs text-slate-500">{{ __('panel.profile.profile_links_hint') }}</p></div>
                        <button type="button" class="panel-outline-btn" @click="addLink()" :disabled="links.length >= maxLinks">{{ __('panel.profile.add_link') }}</button>
                    </div>
                    <div class="panel-profile-links-stack space-y-2">
                        <template x-for="link in links" :key="link.id">
                            <div class="grid gap-2 sm:grid-cols-[12rem_1fr_auto]">
                                <div
                                    class="panel-platform-combobox"
                                    data-platform-combobox
                                    :data-link-id="link.id"
                                    @click.outside="closeDropdown()"
                                >
                                    <div class="relative">
                                        <input
                                            type="text"
                                            x-model="link.platform"
                                            @focus="onPlatformFocus(link)"
                                            @input="onPlatformInput(link)"
                                            @keydown.escape.prevent="closeDropdown()"
                                            class="panel-input-block panel-platform-trigger"
                                            placeholder="{{ __('panel.profile.platform_placeholder') }}"
                                            role="combobox"
                                            :aria-expanded="isDropdownOpen(link)"
                                            aria-autocomplete="list"
                                            autocomplete="off"
                                        >
                                        <button
                                            type="button"
                                            class="panel-platform-toggle"
                                            @click="toggleDropdown(link)"
                                            :aria-label="@js(__('panel.profile.platform_placeholder'))"
                                            :aria-expanded="isDropdownOpen(link)"
                                        >
                                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div
                                        x-show="isDropdownOpen(link) && filteredPlatforms(link).length"
                                        x-cloak
                                        data-platform-menu
                                        class="panel-platform-menu"
                                        :class="dropdownUp ? 'panel-platform-menu-up' : 'panel-platform-menu-down'"
                                        role="listbox"
                                    >
                                        <template x-for="option in filteredPlatforms(link)" :key="option">
                                            <button
                                                type="button"
                                                class="panel-platform-option"
                                                :class="link.platform === option ? 'panel-platform-option-active' : ''"
                                                @mousedown.prevent="selectPlatform(link, option)"
                                                role="option"
                                                :aria-selected="link.platform === option"
                                                x-text="option"
                                            ></button>
                                        </template>
                                    </div>
                                </div>
                                <input type="url" x-model="link.url" @input.debounce.300ms="persist()" class="panel-input-block" placeholder="https://...">
                                <button type="button" class="panel-btn-danger" @click="removeLink(link)" :aria-label="@js(__('panel.profile.remove_link'))">{{ __('panel.profile.remove') }}</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="panel-profile-save-row flex flex-wrap items-center gap-3 pt-2">
                <button type="submit" :disabled="saving" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-60">{{ __('panel.profile.save_soon') }}</button>
                <span x-show="saved" x-cloak class="text-sm text-emerald-600">{{ __('panel.profile.saved') }}</span>
                <span x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></span>
            </div>
        </form>
    </section>

    <section x-show="tab === 'giris'" x-cloak class="space-y-6">
        <form class="panel-card space-y-4 p-6" onsubmit="return false;">
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.email') }}</label>
                <input type="email" value="{{ $profile['email'] }}"
                    class="panel-input-block">
                <p class="mt-1 text-xs text-slate-500">{{ __('panel.profile.email_hint') }}</p>
            </div>
            <button type="button" disabled class="panel-btn-disabled">
                {{ __('panel.profile.update_email_soon') }}
            </button>
        </form>

        <form class="panel-card space-y-4 p-6" onsubmit="return false;">
            <h2 class="font-semibold">{{ __('panel.profile.change_password') }}</h2>
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.current_password') }}</label>
                <input type="password" autocomplete="current-password"
                    class="panel-input-block">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.new_password') }}</label>
                <input type="password" autocomplete="new-password"
                    class="panel-input-block">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.confirm_password') }}</label>
                <input type="password" autocomplete="new-password"
                    class="panel-input-block">
            </div>
            <button type="button" disabled class="panel-btn-disabled">
                {{ __('panel.profile.update_password_soon') }}
            </button>
        </form>
    </section>

    <section id="cv-yukle" x-show="tab === 'cv'" x-cloak
        data-initial-history-analysis-ready="{{ ! empty($hasReadyHistoryAnalysis) ? 'true' : 'false' }}"
        x-data="profileCvUpload(@js(app()->getLocale()), '', @js(route('panel.cv.analysis-status', ['analysisId' => '__ANALYSIS_ID__'])), '', @js(route('panel.cv-history.analyze', ['documentId' => '__DOCUMENT_ID__'])), @js(route('panel.cv.analysis-stream', ['analysisId' => '__ANALYSIS_ID__'])), @js(! empty($hasReadyHistoryAnalysis)))">
        <div class="panel-card p-6">
            <h2 class="font-semibold">{{ __('panel.profile.cv_history_title') }}</h2>
            <p class="panel-muted mt-1 text-sm">{{ __('panel.profile.cv_history_desc') }}</p>

            <p x-show="historyLoadingId" x-cloak class="mt-4 rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-800 dark:text-sky-200">
                {{ __('panel.profile.cv_analyze_active_working') }}
            </p>
            <div x-show="historyAnalysisReady" x-cloak data-cv-history-analysis-ready
                class="mt-4 flex flex-col gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                role="status">
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ __('panel.profile.cv_analysis_ready') }}</p>
                <a href="{{ route('panel.roadmap') }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    {{ __('panel.profile.cv_go_roadmap') }}
                </a>
            </div>
            <p x-show="error" x-cloak class="mt-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200" x-text="error"></p>

            @if (! empty($cvHistory))
                <ul class="mt-5 divide-y divide-slate-200 border-t border-slate-200 pt-5 dark:divide-slate-800 dark:border-slate-700">
                    @foreach ($cvHistory as $document)
                        <li class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-medium">{{ $document['display_name'] }}</p>
                                    @if ($document['is_current'] ?? false)
                                        <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('panel.profile.cv_current') }}</span>
                                    @endif
                                </div>
                                <p class="panel-muted mt-1 text-xs">{{ ($document['kind'] ?? '') === 'generated' ? __('panel.profile.cv_generated') : __('panel.profile.cv_uploaded') }} · {{ \Illuminate\Support\Carbon::parse($document['created_at'])->format('d.m.Y H:i') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                <button type="button" @click="analyzeHistory(@js($document['id']))" :disabled="historyLoadingId !== null"
                                    class="font-medium text-violet-600 hover:underline disabled:cursor-not-allowed disabled:opacity-60 dark:text-violet-400">
                                    <span x-show="historyLoadingId !== @js($document['id'])">{{ __('panel.profile.cv_analyze_active') }}</span>
                                    <span x-show="historyLoadingId === @js($document['id'])" x-cloak>{{ __('panel.profile.cv_analyze_active_working') }}</span>
                                </button>
                                @if (($document['kind'] ?? '') === 'generated')<a href="{{ route('panel.cv-builder', ['cvDocument' => $document['id']]) }}" class="text-sky-600 hover:underline dark:text-sky-400">{{ __('panel.profile.cv_restore') }}</a>@endif
                                <div x-data="{ deleteDialogOpen: false }">
                                    <button type="button" @click="deleteDialogOpen = true"
                                        class="text-red-600 hover:underline dark:text-red-400">{{ __('panel.profile.cv_delete') }}</button>
                                    <div data-cv-delete-dialog x-show="deleteDialogOpen" x-cloak
                                        @keydown.escape.window="deleteDialogOpen = false"
                                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
                                        role="dialog" aria-modal="true" aria-labelledby="cv-delete-title-{{ $loop->index }}">
                                        <div @click.outside="deleteDialogOpen = false" class="panel-card w-full max-w-md space-y-5 p-6">
                                            <div>
                                                <h2 id="cv-delete-title-{{ $loop->index }}" class="text-lg font-semibold">{{ __('panel.profile.cv_delete_title') }}</h2>
                                                <p class="panel-muted mt-2 text-sm">{{ __('panel.profile.cv_delete_confirm') }}</p>
                                                <p class="mt-3 truncate text-sm font-medium">{{ $document['display_name'] }}</p>
                                            </div>
                                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                                <button type="button" @click="deleteDialogOpen = false" class="panel-btn-secondary">
                                                    {{ __('panel.profile.cv_delete_cancel') }}
                                                </button>
                                                <form method="post" action="{{ route('panel.cv-history.destroy', $document['id']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 sm:w-auto">
                                                        {{ __('panel.profile.cv_delete_action') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500 dark:border-slate-700">{{ __('panel.profile.cv_history_empty') }}</p>
            @endif
        </div>
    </section>

    <section id="abonelik" x-show="tab === 'abonelik'" x-cloak class="space-y-6">
        <div class="panel-card space-y-4 p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-semibold">{{ __('panel.account.subscription_title') }}</h2>
                    <p class="panel-muted mt-1 text-sm">{{ __('panel.account.subscription_desc') }}</p>
                </div>
                <span class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                    {{ __('panel.account.free_plan') }}
                </span>
            </div>
            <button type="button" disabled class="panel-btn-disabled">{{ __('panel.account.manage_subscription_soon') }}</button>
        </div>
    </section>

    <section id="gizlilik" x-show="tab === 'gizlilik'" x-cloak class="space-y-6">
        <div class="panel-card space-y-4 p-6">
            <h2 class="font-semibold">{{ __('panel.account.privacy_title') }}</h2>
            <p class="panel-muted text-sm">{{ __('panel.account.privacy_desc') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <button type="button" disabled class="panel-btn-disabled">{{ __('panel.account.export_data_soon') }}</button>
                <button type="button" disabled class="panel-btn-disabled">{{ __('panel.account.delete_account_soon') }}</button>
            </div>
        </div>
    </section>
</div>
@endsection
