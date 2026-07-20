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
                <div class="relative flex flex-col justify-between rounded-xl border p-4 transition-all hover:shadow-md"
                    :class="version.is_main ? 'border-emerald-500 bg-emerald-50/20 dark:bg-emerald-950/10' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/50'">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-slate-900 dark:text-white" x-text="version.version_name"></h3>
                            <div class="flex gap-1.5">
                                <span class="rounded px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                    :class="version.language === 'tr' ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'"
                                    x-text="version.language"></span>
                                <template x-if="version.is_main">
                                    <span class="rounded bg-emerald-600 px-1.5 py-0.5 text-xs font-semibold text-white tracking-wider">
                                        {{ app()->getLocale() === 'en' ? 'Main' : 'Ana' }}
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
                        <button type="button" @click="deleteVersion(version)"
                            class="ml-auto inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-900/30">
                            <i data-lucide="trash-2" class="h-3 w-3"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
