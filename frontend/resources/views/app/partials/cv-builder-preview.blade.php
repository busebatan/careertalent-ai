<div :class="mode === 'preview' ? 'lg:col-span-2' : ''">
    <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-500" x-text="uiLabels[panelLocale].preview_note"></p>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500" x-text="uiLabels[panelLocale].preview_lang + ':'"></span>
            <button type="button" @click="previewLang = 'tr'"
                :class="previewLang === 'tr' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                class="rounded-md px-2 py-1 text-xs font-medium" x-text="uiLabels[panelLocale].tab_tr"></button>
            <button type="button" @click="previewLang = 'en'"
                :class="previewLang === 'en' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                class="rounded-md px-2 py-1 text-xs font-medium" x-text="uiLabels[panelLocale].tab_en"></button>
        </div>
    </div>

    <div id="harvard-preview" class="harvard-cv rounded-lg border border-slate-300 p-8 shadow-lg">
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
