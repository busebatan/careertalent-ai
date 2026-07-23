<!-- CV Sürümleri (CV Center) Yönetimi -->
<div data-cv-version-manager class="panel-card my-8 p-6">
    <div class="mb-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div class="min-w-0">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-950 dark:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>
                </svg>
                {{ app()->getLocale() === 'en' ? 'My Resume Versions' : 'Özgeçmiş Sürümlerim' }}
            </h2>
            <p class="panel-muted mt-1 text-sm">
                {{ app()->getLocale() === 'en' ? 'Manage different versions of your CV for different roles or languages.' : 'Farklı roller veya diller için özgeçmiş sürümlerinizi oluşturun ve yönetin.' }}
            </p>
        </div>
        <button type="button" @click="openCreateVersionModal()"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-500">
            <i data-lucide="plus" class="h-4 w-4"></i>
            {{ app()->getLocale() === 'en' ? 'Save Current as New Version' : 'Mevcut Taslağı Yeni Sürüm Olarak Kaydet' }}
        </button>
    </div>

    <div data-cv-version-notice x-show="versionNotice" x-cloak role="status"
        class="mb-4 rounded-xl border px-4 py-3 text-sm"
        :class="versionNoticeTone === 'error'
            ? 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-200'
            : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200'"
        x-text="versionNotice">
    </div>

    <template x-if="cvVersions.length === 0">
        <div class="panel-entry border-dashed p-8 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 h-8 w-8 text-slate-400"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 2H6a2 2 0 0 0-2 2v13"/><path d="M9 22h9a2 2 0 0 0 2-2V7l-5-5H9a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2Z"/><path d="M14 2v5h5"/>
            </svg>
            <p class="panel-muted text-sm">{{ app()->getLocale() === 'en' ? 'You have no custom CV versions yet. Create one by clicking the button above.' : 'Henüz özel bir CV sürümünüz bulunmuyor. Yukarıdaki butona tıklayarak ilk sürümünüzü oluşturabilirsiniz.' }}</p>
        </div>
    </template>

    <template x-if="cvVersions.length > 0">
        <div class="grid gap-4 lg:grid-cols-2">
            <template x-for="version in cvVersions" :key="version.id">
                <article data-cv-version-card class="panel-entry relative flex flex-col justify-between gap-4 p-5 transition"
                    :class="
                        version.id === activeLoadedVersionId
                            ? 'border-sky-500/40 bg-sky-500/5 dark:bg-sky-500/10'
                            : version.is_main
                                ? 'border-emerald-500/40 bg-emerald-500/5 dark:bg-emerald-500/10'
                                : ''
                    ">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <template x-if="renamingVersionId !== version.id">
                                <div class="flex min-w-0 flex-1 items-center gap-1.5">
                                    <h3 class="cursor-pointer truncate font-semibold text-slate-950 transition hover:text-emerald-600 dark:text-white dark:hover:text-emerald-400"
                                        @click="startRename(version)"
                                        :title="panelLocale === 'en' ? 'Click to rename' : 'Düzenlemek için tıklayın'"
                                        x-text="version.version_name"></h3>
                                    <button type="button" @click="startRename(version)"
                                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-emerald-600 dark:hover:bg-slate-800 dark:hover:text-emerald-400"
                                        :title="panelLocale === 'en' ? 'Rename version' : 'Sürüm adını düzenle'">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <template x-if="renamingVersionId === version.id">
                                <div class="flex min-w-0 flex-1 items-center gap-1">
                                    <input type="text" :id="'rename-version-input-' + version.id"
                                        x-model="renameInput"
                                        @keydown.enter="confirmRename(version)"
                                        @keydown.escape="cancelRename()"
                                        class="panel-input-block w-full px-2 py-1.5 text-xs font-semibold" />
                                    <button type="button" @click="confirmRename(version)"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white transition hover:bg-emerald-500"
                                        :title="panelLocale === 'en' ? 'Save' : 'Kaydet'">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                    <button type="button" @click="cancelRename()"
                                        class="panel-btn-secondary inline-flex h-8 w-8 shrink-0 items-center justify-center p-0"
                                        :title="panelLocale === 'en' ? 'Cancel' : 'İptal'">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            </template>

                            <div class="flex flex-wrap justify-end gap-1.5">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    x-text="version.language"></span>
                                <template x-if="version.is_main">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        {{ app()->getLocale() === 'en' ? 'Main' : 'Ana' }}
                                    </span>
                                </template>
                                <template x-if="version.id === activeLoadedVersionId">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-500/15 px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:text-sky-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        {{ app()->getLocale() === 'en' ? 'In Editor' : 'Editörde' }}
                                    </span>
                                </template>
                            </div>
                        </div>
                        <p class="panel-muted mt-2 text-xs">
                            {{ app()->getLocale() === 'en' ? 'Saved: ' : 'Kayıt: ' }}
                            <span x-text="new Date(version.created_at).toLocaleDateString(panelLocale, {day: 'numeric', month: 'short', year: 'numeric'})"></span>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <button type="button" @click="requestVersionLoad(version)"
                            class="panel-btn-secondary inline-flex items-center gap-1.5 px-3 py-2 text-xs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M7 17 17 7"/><path d="M7 7h10v10"/>
                            </svg>
                            {{ app()->getLocale() === 'en' ? 'Load to Editor' : 'Editöre Yükle' }}
                        </button>
                        <template x-if="!version.is_main">
                            <button type="button" @click="setVersionMain(version)"
                                class="panel-btn-secondary inline-flex items-center gap-1.5 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m20 6-11 11-5-5"/>
                                </svg>
                                {{ app()->getLocale() === 'en' ? 'Set as Main' : 'Ana Yap' }}
                            </button>
                        </template>
                        <button type="button" @click="openVersionPreview(version)"
                            class="panel-btn-secondary inline-flex items-center gap-1.5 px-3 py-2 text-xs"
                            :title="panelLocale === 'en' ? 'Quick Preview' : 'Hızlı Önizle'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.1 12a10.8 10.8 0 0 1 19.8 0 10.8 10.8 0 0 1-19.8 0Z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                            {{ app()->getLocale() === 'en' ? 'Preview' : 'Önizle' }}
                        </button>
                        <button type="button" @click="requestVersionDelete(version)"
                            class="ml-auto inline-flex h-9 w-9 items-center justify-center rounded-xl border border-red-500/30 text-red-600 transition hover:bg-red-500/10 dark:text-red-300"
                            :title="panelLocale === 'en' ? 'Delete version' : 'Sürümü sil'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>
                            </svg>
                        </button>
                    </div>
                </article>
            </template>
        </div>
    </template>
</div>

{{-- ===== CV Sürümü Hızlı Önizleme Modali ===== --}}
<template x-teleport="body">
    <div
        x-show="previewVersionModalOpen"
        x-cloak
        @keydown.escape.window="closeVersionPreview()"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        role="dialog" aria-modal="true" aria-labelledby="cv-version-preview-title"
        style="display:none;">

        {{-- Backdrop --}}
        <div
            x-show="previewVersionModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeVersionPreview()"
            class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm">
        </div>

        {{-- Modal Paneli --}}
        <div
            x-show="previewVersionModalOpen"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="panel-card relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden p-0">

            {{-- Modal Başlık --}}
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 id="cv-version-preview-title" class="truncate text-sm font-semibold text-slate-950 dark:text-white" x-text="previewVersionData?.version_name || ''"></h3>
                        <p class="panel-muted text-xs">
                            {{ app()->getLocale() === 'en' ? 'Read-only preview · Editing is not affected' : 'Salt okunur önizleme · Editör taslağı etkilenmez' }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="closeVersionPreview()"
                    class="shrink-0 inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition dark:hover:bg-slate-800 dark:hover:text-slate-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            {{-- Modal İçerik (scroll) --}}
            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

                {{-- Kişisel Bilgiler --}}
                <template x-if="previewVersionData?.payload?.personal">
                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ app()->getLocale() === 'en' ? 'Personal Info' : 'Kişisel Bilgiler' }}</p>
                        <div class="panel-entry space-y-1.5 p-4">
                            <p class="text-base font-bold text-slate-900 dark:text-white" x-text="previewVersionData.payload.personal.full_name || '—'"></p>
                            <div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                                <span x-show="previewVersionData.payload.personal.email" x-text="previewVersionData.payload.personal.email"></span>
                                <span x-show="previewVersionData.payload.personal.phone" x-text="previewVersionData.payload.personal.phone"></span>
                                <span x-show="previewVersionData.payload.personal.location" x-text="previewVersionData.payload.personal.location"></span>
                                <span x-show="previewVersionData.payload.personal.linkedin" x-text="previewVersionData.payload.personal.linkedin"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Özet --}}
                <template x-if="previewVersionData?.payload?.personal?.summary">
                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ app()->getLocale() === 'en' ? 'Summary' : 'Özet' }}</p>
                        <div class="panel-entry p-4">
                            <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-300" x-text="previewVersionData.payload.personal.summary"></p>
                        </div>
                    </div>
                </template>

                {{-- Deneyimler --}}
                <template x-if="previewVersionData?.payload?.experience?.length > 0">
                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ app()->getLocale() === 'en' ? 'Experience' : 'Deneyimler' }}</p>
                        <div class="space-y-3">
                            <template x-for="exp in previewVersionData.payload.experience" :key="exp.id">
                                <div class="panel-entry p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-1">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white" x-text="exp.title || '—'"></p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500" x-text="[exp.start, exp.end].filter(Boolean).join(' – ') || ''"></p>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="[exp.organization, exp.location].filter(Boolean).join(', ')"></p>
                                    <template x-if="exp.bullets?.length > 0">
                                        <ul class="mt-2 space-y-0.5 pl-4 list-disc text-xs text-slate-600 dark:text-slate-400">
                                            <template x-for="(b, idx) in exp.bullets" :key="idx">
                                                <li x-text="b" x-show="b"></li>
                                            </template>
                                        </ul>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Eğitimler --}}
                <template x-if="previewVersionData?.payload?.education?.length > 0">
                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ app()->getLocale() === 'en' ? 'Education' : 'Eğitimler' }}</p>
                        <div class="space-y-2">
                            <template x-for="edu in previewVersionData.payload.education" :key="edu.id">
                                <div class="panel-entry p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-1">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white" x-text="edu.institution || '—'"></p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500" x-text="[edu.start, edu.end].filter(Boolean).join(' – ') || ''"></p>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="[edu.degree, edu.location].filter(Boolean).join(' · ')"></p>
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400" x-show="edu.details" x-text="edu.details"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Beceriler --}}
                <template x-if="previewVersionData?.payload?.skills?.length > 0">
                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ app()->getLocale() === 'en' ? 'Skills' : 'Beceriler' }}</p>
                        <div class="panel-entry space-y-1.5 p-4">
                            <template x-for="skill in previewVersionData.payload.skills" :key="skill.id">
                                <div class="flex gap-2 text-xs">
                                    <span class="shrink-0 font-semibold text-slate-700 dark:text-slate-300" x-text="skill.category ? skill.category + ':' : ''"></span>
                                    <span class="text-slate-500 dark:text-slate-400" x-text="skill.items"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Projeler --}}
                <template x-if="previewVersionData?.payload?.projects?.length > 0">
                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ app()->getLocale() === 'en' ? 'Projects' : 'Projeler' }}</p>
                        <div class="space-y-2">
                            <template x-for="prj in previewVersionData.payload.projects" :key="prj.id">
                                <div class="panel-entry p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-1">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white" x-text="prj.name || '—'"></p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500" x-text="[prj.start, prj.end].filter(Boolean).join(' – ') || ''"></p>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400" x-text="prj.description"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Sertifikalar --}}
                <template x-if="previewVersionData?.payload?.certificates?.length > 0">
                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ app()->getLocale() === 'en' ? 'Certificates' : 'Sertifikalar' }}</p>
                        <div class="panel-entry space-y-1.5 p-4">
                            <template x-for="cert in previewVersionData.payload.certificates" :key="cert.id">
                                <div class="flex flex-wrap items-center gap-x-3 text-xs">
                                    <span class="font-semibold text-slate-900 dark:text-white" x-text="cert.name"></span>
                                    <span class="text-slate-500 dark:text-slate-400" x-text="cert.issuer"></span>
                                    <span class="text-slate-400 dark:text-slate-500" x-text="cert.date"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

            </div>{{-- /scroll --}}

            {{-- Modal Alt --}}
            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60">
                <p class="text-xs text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    {{ app()->getLocale() === 'en' ? 'Your current draft in the editor remains unchanged.' : 'Editördeki mevcut taslağınız bu önizlemeden etkilenmez.' }}
                </p>
                <button type="button" @click="closeVersionPreview()"
                    class="panel-btn-secondary inline-flex items-center gap-2 text-sm">
                    {{ app()->getLocale() === 'en' ? 'Close' : 'Kapat' }}
                </button>
            </div>

        </div>{{-- /panel --}}
    </div>{{-- /overlay --}}
</template>
{{-- ===== /CV Sürümü Hızlı Önizleme Modali ===== --}}

{{-- ===== CV Sürümü İşlem Onayı ===== --}}
<template x-teleport="body">
    <div data-cv-version-action-modal x-show="versionActionModalOpen" x-cloak
        @keydown.escape.window="closeVersionActionModal()"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 p-4"
        role="dialog" aria-modal="true" aria-labelledby="cv-version-action-title">
        <div class="panel-card w-full max-w-md space-y-5 p-6"
            @click.outside="closeVersionActionModal()">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                    :class="versionActionKind === 'delete'
                        ? 'bg-red-500/10 text-red-600 dark:text-red-300'
                        : 'bg-sky-500/10 text-sky-600 dark:text-sky-300'">
                    <svg x-show="versionActionKind === 'delete'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/>
                    </svg>
                    <svg x-show="versionActionKind !== 'delete'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 id="cv-version-action-title" class="text-lg font-semibold text-slate-950 dark:text-white"
                        x-text="versionActionTitle"></h2>
                    <p class="panel-muted mt-1 text-sm" x-text="versionActionDescription"></p>
                </div>
            </div>

            <p x-show="versionActionError" x-cloak role="alert"
                class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200"
                x-text="versionActionError"></p>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="panel-btn-secondary"
                    @click="closeVersionActionModal()" :disabled="versionActionBusy">
                    {{ app()->getLocale() === 'en' ? 'Cancel' : 'İptal' }}
                </button>
                <button type="button" @click="confirmVersionAction()" :disabled="versionActionBusy"
                    class="rounded-xl px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                    :class="versionActionKind === 'delete' ? 'bg-red-600 hover:bg-red-500' : 'bg-emerald-600 hover:bg-emerald-500'">
                    <span x-show="!versionActionBusy" x-text="versionActionConfirmLabel"></span>
                    <span x-show="versionActionBusy">
                        {{ app()->getLocale() === 'en' ? 'Processing…' : 'İşleniyor…' }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
{{-- ===== /CV Sürümü İşlem Onayı ===== --}}
