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
        @php
            $deletableCvIds = collect($cvHistory ?? [])
                ->filter(fn ($document) => is_array($document) && ! ($document['is_current'] ?? false))
                ->pluck('id')
                ->filter()
                ->values()
                ->all();
        @endphp
        <div data-cv-history-manager class="panel-card p-6"
            x-data="{
                selectedCvIds: [],
                deletableCvIds: @js($deletableCvIds),
                previewOpen: false,
                previewName: '',
                previewUrl: '',
                deleteDialogOpen: false,
                openPreview(name, url) {
                    this.previewName = name;
                    this.previewUrl = url;
                    this.previewOpen = true;
                },
                closePreview() {
                    this.previewOpen = false;
                    this.previewUrl = '';
                    this.previewName = '';
                },
                toggleAll(checked) {
                    this.selectedCvIds = checked ? [...this.deletableCvIds] : [];
                },
            }">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="font-semibold">{{ __('panel.profile.cv_history_title') }}</h2>
                    <p class="panel-muted mt-1 text-sm">{{ __('panel.profile.cv_history_desc') }}</p>
                </div>
                @if (! empty($deletableCvIds))
                    <div class="flex shrink-0 flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                            <input data-cv-history-select-all type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900"
                                :checked="selectedCvIds.length === deletableCvIds.length"
                                x-effect="$el.indeterminate = selectedCvIds.length > 0 && selectedCvIds.length < deletableCvIds.length"
                                @change="toggleAll($event.target.checked)">
                            <span>{{ __('panel.profile.cv_select_all') }}</span>
                        </label>
                        <span x-show="selectedCvIds.length > 0" x-cloak class="panel-muted text-xs">
                            <span x-text="selectedCvIds.length"></span>
                            {{ __('panel.profile.cv_selected_count') }}
                        </span>
                        <button data-cv-history-bulk-delete type="button"
                            @click="deleteDialogOpen = true"
                            :disabled="selectedCvIds.length === 0"
                            class="inline-flex items-center gap-2 rounded-xl border border-red-500/30 px-3 py-2 text-xs font-medium text-red-600 transition hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-40 dark:text-red-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>
                            </svg>
                            {{ __('panel.profile.cv_delete_selected') }}
                        </button>
                    </div>
                @endif
            </div>

            @if (session('cv_status'))
                <p class="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200"
                    role="status">{{ session('cv_status') }}</p>
            @endif
            @if (isset($errors) && $errors->has('cv'))
                <p class="mt-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200"
                    role="alert">{{ $errors->first('cv') }}</p>
            @endif

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
                        <li class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                            @if (($document['kind'] ?? '') === 'uploaded')
                                x-data="cvBuilderImport(@js($document), {
                                    statusUrl: @js(route('panel.cv.builder-draft.status', ['documentId' => $document['id']])),
                                    queueUrl: @js(route('panel.cv.builder-draft.queue', ['documentId' => $document['id']])),
                                    openUrl: @js(route('panel.cv-builder', ['cvDocument' => $document['id']])),
                                    labels: @js(['failed' => __('panel.profile.cv_builder_import_failed'), 'timeout' => __('panel.profile.cv_builder_import_timeout')])
                                })"
                            @endif>
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                @if ($document['is_current'] ?? false)
                                    <input data-cv-history-select data-cv-history-select-disabled type="checkbox" disabled
                                        class="mt-0.5 h-4 w-4 cursor-not-allowed rounded border-slate-300 text-emerald-600 opacity-40 dark:border-slate-600 dark:bg-slate-900"
                                        aria-label="{{ __('panel.profile.cv_current_not_deletable') }}: {{ $document['display_name'] }}"
                                        title="{{ __('panel.profile.cv_current_not_deletable') }}">
                                @else
                                    <input data-cv-history-select type="checkbox" value="{{ $document['id'] }}"
                                        x-model="selectedCvIds"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900"
                                        aria-label="{{ __('panel.profile.cv_delete_selected') }}: {{ $document['display_name'] }}">
                                @endif
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-medium">{{ $document['display_name'] }}</p>
                                        @if ($document['is_current'] ?? false)
                                            <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('panel.profile.cv_current') }}</span>
                                        @endif
                                    </div>
                                    <p class="panel-muted mt-1 text-xs">{{ ($document['kind'] ?? '') === 'generated' ? __('panel.profile.cv_generated') : __('panel.profile.cv_uploaded') }} · {{ \Illuminate\Support\Carbon::parse($document['created_at'])->format('d.m.Y H:i') }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                <button type="button" @click="analyzeHistory(@js($document['id']))" :disabled="historyLoadingId !== null"
                                    class="font-medium text-violet-600 hover:underline disabled:cursor-not-allowed disabled:opacity-60 dark:text-violet-400">
                                    <span x-show="historyLoadingId !== @js($document['id'])">{{ __('panel.profile.cv_analyze_active') }}</span>
                                    <span x-show="historyLoadingId === @js($document['id'])" x-cloak>{{ __('panel.profile.cv_analyze_active_working') }}</span>
                                </button>
                                @if (($document['kind'] ?? '') === 'generated')<a href="{{ route('panel.cv-builder', ['cvDocument' => $document['id']]) }}" class="text-sky-600 hover:underline dark:text-sky-400">{{ __('panel.profile.cv_restore') }}</a>@endif
                                @if (($document['kind'] ?? '') === 'uploaded')
                                    <span x-show="pending" x-cloak class="font-medium text-sky-600 dark:text-sky-400" role="status">
                                        {{ __('panel.profile.cv_builder_import_preparing') }}
                                    </span>
                                    <form x-show="canOpen" x-cloak method="post"
                                        action="{{ route('panel.cv.builder-draft.activate', ['documentId' => $document['id']]) }}">
                                        @csrf
                                        <input type="hidden" name="language" value="{{ app()->getLocale() === 'en' ? 'en' : 'tr' }}">
                                        <button type="submit" class="font-medium text-sky-600 hover:underline dark:text-sky-400">
                                            {{ __('panel.profile.cv_builder_import_open') }}
                                        </button>
                                    </form>
                                    <button x-show="canQueue" x-cloak type="button" @click="queue()" :disabled="busy"
                                        class="font-medium text-sky-600 hover:underline disabled:opacity-60 dark:text-sky-400">
                                        <span x-text="status === 'failed' ? @js(__('panel.profile.cv_builder_import_retry')) : @js(__('panel.profile.cv_builder_import_create'))"></span>
                                    </button>
                                    <span x-show="error" x-cloak x-text="error" class="font-medium text-red-600 dark:text-red-400" role="alert"></span>
                                @endif
                                <button data-cv-history-preview-trigger type="button"
                                    @click="openPreview(@js($document['display_name']), @js(route('panel.cv-history.preview', $document['id'])))"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 text-slate-600 transition hover:border-emerald-500/50 hover:bg-emerald-500/10 hover:text-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:border-slate-700 dark:text-slate-300 dark:hover:text-emerald-300"
                                    aria-label="{{ __('panel.profile.cv_preview') }}: {{ $document['display_name'] }}"
                                    title="{{ __('panel.profile.cv_preview') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M2.1 12a10.8 10.8 0 0 1 19.8 0 10.8 10.8 0 0 1-19.8 0Z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500 dark:border-slate-700">{{ __('panel.profile.cv_history_empty') }}</p>
            @endif

            <div data-cv-history-preview-modal x-show="previewOpen" x-cloak
                @keydown.escape.window="closePreview()"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 p-4"
                role="dialog" aria-modal="true" aria-labelledby="cv-history-preview-title">
                <div class="panel-card flex h-[88vh] w-full max-w-5xl flex-col overflow-hidden p-0"
                    @click.outside="closePreview()">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div class="min-w-0">
                            <h2 id="cv-history-preview-title" class="text-lg font-semibold">{{ __('panel.profile.cv_preview_title') }}</h2>
                            <p class="panel-muted mt-1 truncate text-sm" x-text="previewName"></p>
                        </div>
                        <button type="button" @click="closePreview()"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 dark:hover:bg-slate-800 dark:hover:text-white"
                            aria-label="{{ __('panel.profile.cv_delete_cancel') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                            </svg>
                        </button>
                    </div>
                    <iframe x-show="previewUrl" :src="previewUrl" class="min-h-0 flex-1 bg-slate-100 dark:bg-slate-950"
                        :title="`${@js(__('panel.profile.cv_preview_title'))}: ${previewName}`"
                        type="application/pdf"></iframe>
                </div>
            </div>

            <div data-cv-history-bulk-delete-modal x-show="deleteDialogOpen" x-cloak
                @keydown.escape.window="deleteDialogOpen = false"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 p-4"
                role="dialog" aria-modal="true" aria-labelledby="cv-bulk-delete-title"
                aria-describedby="cv-bulk-delete-description">
                <div @click.outside="deleteDialogOpen = false" class="panel-card w-full max-w-md space-y-5 p-6">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-500/10 text-red-600 dark:text-red-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 id="cv-bulk-delete-title" class="text-lg font-semibold">{{ __('panel.profile.cv_bulk_delete_title') }}</h2>
                            <p id="cv-bulk-delete-description" class="panel-muted mt-2 text-sm"
                                x-text="@js(__('panel.profile.cv_bulk_delete_confirm', ['count' => ':count'])).replace(':count', selectedCvIds.length)"></p>
                        </div>
                    </div>
                    <form method="post" action="{{ route('panel.cv-history.bulk-destroy') }}">
                        @csrf
                        @method('DELETE')
                        <template x-for="documentId in selectedCvIds" :key="documentId">
                            <input type="hidden" name="document_ids[]" :value="documentId">
                        </template>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" @click="deleteDialogOpen = false" class="panel-btn-secondary">
                                {{ __('panel.profile.cv_delete_cancel') }}
                            </button>
                            <button type="submit" :disabled="selectedCvIds.length === 0"
                                class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ __('panel.profile.cv_delete_action') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
