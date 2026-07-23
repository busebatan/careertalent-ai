<!-- CV Sürümleri (CV Center) Yönetimi -->
<div class="my-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-4 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="layers" class="h-5 w-5 text-emerald-600 dark:text-emerald-400"></i>
                {{ app()->getLocale() === 'en' ? 'My Resume Versions' : 'Özgeçmiş Sürümlerim' }}
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ app()->getLocale() === 'en' ? 'Manage different versions of your CV for different roles or languages.' : 'Farklı roller veya diller için özgeçmiş sürümlerinizi oluşturun ve yönetin.' }}
            </p>
        </div>
        <button type="button" @click="openCreateVersionModal()"
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 transition shadow-sm hover:shadow active:scale-95">
            <i data-lucide="plus" class="h-4 w-4"></i>
            {{ app()->getLocale() === 'en' ? 'Save Current as New Version' : 'Mevcut Taslağı Yeni Sürüm Olarak Kaydet' }}
        </button>
    </div>

    <template x-if="cvVersions.length === 0">
        <div class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-slate-500 dark:border-slate-800">
            <p>{{ app()->getLocale() === 'en' ? 'You have no custom CV versions yet. Create one by clicking the button above.' : 'Henüz özel bir CV sürümünüz bulunmuyor. Yukarıdaki butona tıklayarak ilk sürümünüzü oluşturabilirsiniz.' }}</p>
        </div>
    </template>

    <template x-if="cvVersions.length > 0">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <template x-for="version in cvVersions" :key="version.id">
                <div class="relative flex flex-col justify-between rounded-xl border-2 p-4 transition-all hover:shadow-md"
                    :class="
                        (version.is_main && version.id === activeLoadedVersionId)
                            ? 'border-violet-500 bg-gradient-to-br from-emerald-50/30 to-violet-50/30 dark:from-emerald-950/10 dark:to-violet-950/10 shadow-md'
                            : version.id === activeLoadedVersionId
                                ? 'border-violet-500 bg-violet-50/20 dark:bg-violet-950/10 shadow-md'
                                : version.is_main
                                    ? 'border-emerald-500 bg-emerald-50/20 dark:bg-emerald-950/10'
                                    : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/50'
                    ">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <template x-if="renamingVersionId !== version.id">
                                <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                    <h3 class="font-semibold text-slate-900 dark:text-white truncate" x-text="version.version_name"></h3>
                                    <button type="button" @click="startRename(version)"
                                        class="shrink-0 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-emerald-600 dark:hover:bg-slate-800 dark:hover:text-emerald-400 transition"
                                        title="{{ app()->getLocale() === 'en' ? 'Rename' : 'Yeniden Adlandır' }}">
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                    </button>
                                </div>
                            </template>
                            <template x-if="renamingVersionId === version.id">
                                <div class="flex items-center gap-1 flex-1 min-w-0">
                                    <input type="text" :id="'rename-version-input-' + version.id"
                                        x-model="renameInput"
                                        @keydown.enter="confirmRename(version)"
                                        @keydown.escape="cancelRename()"
                                        class="w-full rounded-lg border border-emerald-500 bg-white px-2 py-1 text-xs font-semibold text-slate-900 shadow-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:bg-slate-800 dark:text-white" />
                                    <button type="button" @click="confirmRename(version)"
                                        class="shrink-0 rounded-md bg-emerald-600 p-1 text-white hover:bg-emerald-500 transition shadow-sm"
                                        title="{{ app()->getLocale() === 'en' ? 'Save' : 'Kaydet' }}">
                                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                    </button>
                                    <button type="button" @click="cancelRename()"
                                        class="shrink-0 rounded-md bg-slate-200 p-1 text-slate-700 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-300 transition"
                                        title="{{ app()->getLocale() === 'en' ? 'Cancel' : 'İptal' }}">
                                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                    </button>
                                </div>
                            </template>

                            <div class="flex gap-1.5 flex-wrap justify-end">
                                <span class="rounded px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                    :class="version.language === 'tr' ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'"
                                    x-text="version.language"></span>
                                <template x-if="version.is_main">
                                    <span class="inline-flex items-center gap-0.5 rounded bg-emerald-600 px-1.5 py-0.5 text-xs font-semibold text-white tracking-wider">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        {{ app()->getLocale() === 'en' ? 'Main' : 'Ana' }}
                                    </span>
                                </template>
                                <template x-if="version.id === activeLoadedVersionId">
                                    <span class="inline-flex items-center gap-0.5 rounded bg-gradient-to-r from-violet-600 to-blue-600 px-1.5 py-0.5 text-xs font-semibold text-white tracking-wider shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        {{ app()->getLocale() === 'en' ? 'In Editor' : 'Editörde' }}
                                    </span>
                                </template>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            {{ app()->getLocale() === 'en' ? 'Saved: ' : 'Kayıt: ' }}
                            <span x-text="new Date(version.created_at).toLocaleDateString(panelLocale, {day: 'numeric', month: 'short', year: 'numeric'})"></span>
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <button type="button" @click="loadVersion(version)"
                            class="inline-flex items-center gap-1 rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition dark:bg-sky-950/30 dark:text-sky-300 dark:hover:bg-sky-900/30">
                            <i data-lucide="arrow-up-right" class="h-3 w-3"></i>
                            {{ app()->getLocale() === 'en' ? 'Load to Editor' : 'Editöre Yükle' }}
                        </button>
                        <template x-if="!version.is_main">
                            <button type="button" @click="setVersionMain(version)"
                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition dark:bg-emerald-950/30 dark:text-emerald-300 dark:hover:bg-emerald-900/30">
                                <i data-lucide="check" class="h-3 w-3"></i>
                                {{ app()->getLocale() === 'en' ? 'Set as Main' : 'Ana Yap' }}
                            </button>
                        </template>
                        {{-- Önizle (Preview) butonu --}}
                        <button type="button" @click="openVersionPreview(version)"
                            class="inline-flex items-center gap-1 rounded-lg bg-violet-50 px-2.5 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100 transition dark:bg-violet-950/30 dark:text-violet-300 dark:hover:bg-violet-900/30"
                            :title="panelLocale === 'en' ? 'Quick Preview' : 'Hızlı Önizle'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            {{ app()->getLocale() === 'en' ? 'Preview' : 'Önizle' }}
                        </button>
                        {{-- Silme butonu (SVG trash ikonu ile) --}}
                        <button type="button" @click="deleteVersion(version)"
                            class="ml-auto inline-flex items-center justify-center gap-1 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 active:scale-95 transition dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-900/30"
                            :title="panelLocale === 'en' ? 'Delete version' : 'Sürümü sil'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                <path d="M10 11v6"/>
                                <path d="M14 11v6"/>
                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
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
            class="relative z-10 w-full max-w-2xl max-h-[88vh] flex flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 overflow-hidden">

            {{-- Modal Başlık --}}
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-4 dark:border-slate-800 bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-950/30 dark:to-purple-950/20">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-600 text-white shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate" x-text="previewVersionData?.version_name || ''"></h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
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
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50 space-y-1.5">
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
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
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
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
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
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
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
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50 space-y-1.5">
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
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
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
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50 space-y-1.5">
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
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    {{ app()->getLocale() === 'en' ? 'Close' : 'Kapat' }}
                </button>
            </div>

        </div>{{-- /panel --}}
    </div>{{-- /overlay --}}
</template>
{{-- ===== /CV Sürümü Hızlı Önizleme Modali ===== --}}
