<div :class="mode === 'preview' ? 'lg:col-span-2' : ''">
    <div data-cv-preview-toolbar
        :class="mode === 'preview' ? 'lg:ml-auto lg:w-[calc(50%-1rem)]' : ''"
        class="panel-card mb-4 flex min-h-[98px] flex-col gap-3 p-4">
        <p class="panel-muted text-sm" x-text="uiLabels[panelLocale].save_hint"></p>
        <div data-cv-preview-actions class="flex flex-nowrap gap-2">
            <button type="button" @click="saveCv()"
                class="inline-flex rounded-xl bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="saveStatus === 'saving' || !hasUnsavedChanges"
                x-text="saveStatus === 'saving' ? uiLabels[panelLocale].analyzing : (saveStatus === 'saved' ? uiLabels[panelLocale].saved : uiLabels[panelLocale].save)">
            </button>
            <button type="button" @click="togglePreview()"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                x-text="mode === 'edit' ? uiLabels[panelLocale].preview : uiLabels[panelLocale].edit">
            </button>
            <button type="button" @click="openPdfModal()"
                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="pdfExportStatus === 'exporting'"
                x-text="uiLabels[panelLocale].download_pdf">
            </button>
        </div>
    </div>

    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-500" x-text="uiLabels[panelLocale].preview_note"></p>
        <div data-preview-language-selector x-show="mode === 'preview'" x-cloak class="flex items-center gap-2">
            <span class="text-xs text-slate-500" x-text="uiLabels[panelLocale].preview_lang + ':'"></span>
            <button type="button" @click="setPreviewLanguage('tr')"
                :class="previewLang === 'tr' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                class="rounded-md px-2 py-1 text-xs font-medium" x-text="uiLabels[panelLocale].tab_tr"></button>
            <button type="button" @click="setPreviewLanguage('en')"
                :class="previewLang === 'en' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                class="rounded-md px-2 py-1 text-xs font-medium" x-text="uiLabels[panelLocale].tab_en"></button>
        </div>
    </div>

    <div x-show="mode === 'preview'" x-cloak class="min-h-[44rem] rounded-lg border border-slate-300 bg-white shadow-lg dark:border-slate-700">
        <div x-show="pdfPreviewLoading" class="flex min-h-[44rem] items-center justify-center p-8 text-sm text-slate-600 dark:text-slate-300" role="status">
            <span x-text="uiLabels[panelLocale].pdf_exporting"></span>
        </div>
        <p x-show="pdfPreviewError" x-cloak class="m-6 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200" x-text="pdfPreviewError" role="alert"></p>
        <iframe x-show="pdfPreviewUrl && !pdfPreviewLoading" :src="pdfPreviewUrl" data-cv-pdf-preview :title="uiLabels[panelLocale].pdf_preview_title" class="h-[70rem] w-full rounded-lg" type="application/pdf"></iframe>
    </div>

    <div x-show="mode !== 'preview'" id="harvard-preview" class="harvard-cv rounded-lg border border-slate-300 p-8 shadow-lg">
        <h1 x-text="locales[previewLang].personal.full_name || uiLabels[previewLang].sections.default_name"></h1>
        <p class="contact">
            <span x-show="locales[previewLang].personal.email" x-text="locales[previewLang].personal.email"></span>
            <span x-show="locales[previewLang].personal.phone"> · <span x-text="locales[previewLang].personal.phone"></span></span>
            <span x-show="locales[previewLang].personal.location"> · <span x-text="locales[previewLang].personal.location"></span></span>
            <span x-show="locales[previewLang].personal.linkedin"> · <span x-text="locales[previewLang].personal.linkedin"></span></span>
        </p>

        <template x-if="locales[previewLang].personal.summary">
            <div>
                <h2 x-text="uiLabels[previewLang].sections.summary"></h2>
                <p x-text="locales[previewLang].personal.summary"></p>
            </div>
        </template>

        <template x-if="locales[previewLang].education.length">
            <div>
                <h2 x-text="uiLabels[previewLang].sections.education"></h2>
                <template x-for="edu in locales[previewLang].education" :key="edu.id">
                    <div class="mb-2">
                        <div class="entry-header">
                            <span x-text="edu.institution"></span>
                            <span x-text="edu.location"></span>
                        </div>
                        <div class="entry-sub">
                            <span x-text="edu.degree"></span>
                            <span x-text="edu.start + ' - ' + edu.end"></span>
                        </div>
                        <p x-show="edu.details" x-text="edu.details" class="text-[10pt]"></p>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="locales[previewLang].experience.length">
            <div>
                <h2 x-text="uiLabels[previewLang].sections.experience"></h2>
                <template x-for="exp in locales[previewLang].experience" :key="exp.id">
                    <div class="mb-2">
                        <div class="entry-header">
                            <span x-text="exp.organization"></span>
                            <span x-text="exp.location"></span>
                        </div>
                        <div class="entry-sub">
                            <span x-text="exp.title"></span>
                            <span x-text="exp.start + ' - ' + exp.end"></span>
                        </div>
                        <ul>
                            <template x-for="b in exp.bullets.filter(x => x.trim())" :key="b">
                                <li x-text="b"></li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="locales[previewLang].skills.length">
            <div>
                <h2 x-text="uiLabels[previewLang].sections.skills"></h2>
                <template x-for="skill in locales[previewLang].skills" :key="skill.id">
                    <p><strong x-text="skill.category + ':'"></strong> <span x-text="skill.items"></span></p>
                </template>
            </div>
        </template>

        <template x-if="locales[previewLang].projects.length">
            <div>
                <h2 x-text="uiLabels[previewLang].sections.projects"></h2>
                <template x-for="prj in locales[previewLang].projects" :key="prj.id">
                    <div class="mb-2">
                        <div class="entry-header">
                            <span x-text="prj.name"></span>
                            <span x-text="prj.start + (prj.end ? ' - ' + prj.end : '')"></span>
                        </div>
                        <p x-show="prj.link" class="text-[10pt]" x-text="prj.link"></p>
                        <p x-text="prj.description"></p>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="locales[previewLang].certificates.length">
            <div>
                <h2 x-text="uiLabels[previewLang].sections.certificates"></h2>
                <template x-for="cert in locales[previewLang].certificates" :key="cert.id">
                    <p><span x-text="cert.name"></span>, <span x-text="cert.issuer"></span> (<span x-text="cert.date"></span>)</p>
                </template>
            </div>
        </template>

        @include('app.partials.cv-builder-optional-preview')
    </div>
</div>
