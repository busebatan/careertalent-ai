<script>
function cvBuilder(initial, uiLabels, panelLocale) {
    return {
        mode: 'edit',
        locales: initial,
        uiLabels,
        panelLocale,
        editLang: panelLocale === 'en' ? 'en' : 'tr',
        previewLang: panelLocale === 'en' ? 'en' : 'tr',
        pdfModalOpen: false,
        pdfExportStatus: 'idle',
        pdfExportingLang: null,
        pdfExportError: '',
        saveStatus: 'idle',
        showRadar: false,
        cvFileName: '',
        cvFileLabel: @js(__('panel.skill_radar.cv_file', ['name' => ':name'])),
        optionalSectionPick: '',
        _skipLocalesSync: false,

        init() {
            const saved = window.PanelCvStore?.get();
            if (saved?.source === 'builder' && saved.locales) {
                this.locales = JSON.parse(JSON.stringify(saved.locales));
                this.showRadar = Boolean(saved.skillRadar);
                this.cvFileName = saved.fileName ?? '';
            } else if (saved?.skillRadar) {
                this.showRadar = true;
                this.cvFileName = saved.fileName ?? '';
            }
            this.normalizeAllLocales();
            window.addEventListener('panel-cv-updated', () => this.syncFromStore());
        },

        syncFromStore() {
            const saved = window.PanelCvStore?.get();
            this.showRadar = Boolean(saved?.skillRadar);
            this.cvFileName = saved?.fileName ?? '';

            if (this._skipLocalesSync) {
                this._skipLocalesSync = false;
                return;
            }

            if (!saved?.skillRadar) {
                return;
            }

            if (saved.source === 'builder' && saved.locales) {
                this.locales = JSON.parse(JSON.stringify(saved.locales));
                this.normalizeAllLocales();
            }
        },

        normalizeAllLocales() {
            const helper = window.CvOptionalSections;
            if (!helper) {
                return;
            }
            ['tr', 'en'].forEach((lang) => helper.normalizeLocaleOptional(this.locales[lang], () => this.uid()));
        },

        optionalSectionLabel(key) {
            return this.uiLabels[this.editLang].sections[key] || key;
        },

        availableOptionalSections() {
            const enabled = this.locales[this.editLang].enabledOptional || [];
            return (window.CvOptionalSections?.keys || []).filter((key) => !enabled.includes(key));
        },

        addOptionalSectionFromDropdown() {
            const key = this.optionalSectionPick;
            if (!key || !window.CvOptionalSections) {
                return;
            }

            window.CvOptionalSections.enableOptionalSectionForBothLocales(
                this.locales,
                key,
                () => this.uid(),
            );
            this.optionalSectionPick = '';
            this.$nextTick(() => {
                const cards = this.$root.querySelectorAll('[data-optional-section]');
                cards[cards.length - 1]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        },

        removeOptionalSection(key) {
            if (window.CvOptionalSections) {
                window.CvOptionalSections.removeOptionalSectionFromBothLocales(this.locales, key);
            }
        },

        addOptionalEntry(key) {
            const lang = this.editLang;
            if (!Array.isArray(this.locales[lang].optional[key])) {
                this.locales[lang].optional[key] = [];
            }
            this.locales[lang].optional[key].push(
                window.CvOptionalSections.createOptionalEntry(key, () => this.uid()),
            );
        },

        removeOptionalEntry(key, id) {
            const lang = this.editLang;
            this.locales[lang].optional[key] = (this.locales[lang].optional[key] || []).filter((entry) => entry.id !== id);
        },

        optionalPreviewVisible(key) {
            const entries = this.locales[this.previewLang].optional?.[key] || [];
            return entries.some((entry) => window.CvOptionalSections?.optionalEntryHasContent(entry, key));
        },

        optionalPreviewEntries(key) {
            const entries = this.locales[this.previewLang].optional?.[key] || [];
            return entries.filter((entry) => window.CvOptionalSections?.optionalEntryHasContent(entry, key));
        },

        cvFileDisplay() {
            return this.cvFileLabel.replace(':name', this.cvFileName || 'cv');
        },

        uid() { return 'id-' + Math.random().toString(36).slice(2, 9); },

        async waitForPreviewRender() {
            await this.$nextTick();
            await new Promise((resolve) => {
                requestAnimationFrame(() => requestAnimationFrame(resolve));
            });
            await new Promise((resolve) => setTimeout(resolve, 350));
        },

        saveCv() {
            if (!window.PanelCvStore) {
                return;
            }
            this.saveStatus = 'saving';
            this._skipLocalesSync = true;
            const state = window.PanelCvStore.saveBuilder(this.locales, this.panelLocale);
            this.showRadar = true;
            this.cvFileName = state.fileName;
            this.saveStatus = 'saved';
            setTimeout(() => {
                if (this.saveStatus === 'saved') {
                    this.saveStatus = 'idle';
                }
            }, 2500);
        },

        addEducation() {
            this.locales[this.editLang].education.push({
                id: this.uid(), institution: '', degree: '', location: '', start: '', end: '', details: ''
            });
        },
        removeEducation(lang, id) {
            this.locales[lang].education = this.locales[lang].education.filter(e => e.id !== id);
        },
        addExperience() {
            this.locales[this.editLang].experience.push({
                id: this.uid(), organization: '', title: '', location: '', start: '', end: '', bullets: ['']
            });
        },
        removeExperience(lang, id) {
            this.locales[lang].experience = this.locales[lang].experience.filter(e => e.id !== id);
        },
        addSkill() {
            this.locales[this.editLang].skills.push({ id: this.uid(), category: '', items: '' });
        },
        removeSkill(lang, id) {
            this.locales[lang].skills = this.locales[lang].skills.filter(s => s.id !== id);
        },
        addProject() {
            this.locales[this.editLang].projects.push({
                id: this.uid(), name: '', link: '', start: '', end: '', description: ''
            });
        },
        removeProject(lang, id) {
            this.locales[lang].projects = this.locales[lang].projects.filter(p => p.id !== id);
        },
        addCertificate() {
            this.locales[this.editLang].certificates.push({ id: this.uid(), name: '', issuer: '', date: '' });
        },
        removeCertificate(lang, id) {
            this.locales[lang].certificates = this.locales[lang].certificates.filter(c => c.id !== id);
        },
        aiPolish(field) {
            const lang = this.editLang;
            const p = this.locales[lang].personal;
            if (field !== 'summary' || !p.summary) return;
            p.summary = lang === 'en'
                ? 'Data analytics professional candidate; delivers measurable outcomes with SQL and Python. Bootcamp student with internship and project experience. ATS keywords: SQL, Python, data visualization, reporting.'
                : 'Veri analitiği odaklı profesyonel aday; SQL ve Python ile ölçülebilir iş sonuçları üreten bootcamp öğrencisi. ATS anahtar kelimeleri: SQL, Python, veri görselleştirme, raporlama.';
        },
        aiPolishExperience(lang, exp) {
            exp.bullets = exp.bullets.map(b => b.trim() ? b.charAt(0).toUpperCase() + b.slice(1) : b);
        },
        aiPolishProject(lang, prj) {
            if (!prj.description) return;
            const suffix = lang === 'en'
                ? ' Expanded with ATS-friendly technical stack and business impact.'
                : ' Teknik stack ve iş etkisi ATS uyumlu cümlelerle genişletilecek.';
            prj.description = prj.description.replace(/\.$/, '') + '.' + suffix;
        },
        openPdfModal() {
            if (this.pdfExportStatus === 'exporting') {
                return;
            }
            this.pdfExportError = '';
            if (this.pdfExportStatus === 'error') {
                this.pdfExportStatus = 'idle';
            }
            this.pdfModalOpen = true;
        },
        closePdfModal() {
            if (this.pdfExportStatus === 'exporting') {
                return;
            }
            this.pdfModalOpen = false;
            this.pdfExportError = '';
            if (this.pdfExportStatus === 'error') {
                this.pdfExportStatus = 'idle';
            }
        },
        async confirmPdfDownload(lang) {
            if (this.pdfExportStatus === 'exporting') {
                return;
            }

            this.pdfExportStatus = 'exporting';
            this.pdfExportingLang = lang;
            this.pdfExportError = '';
            const previous = this.previewLang;
            this.previewLang = lang;
            await this.waitForPreviewRender();

            const el = document.getElementById('harvard-preview');
            const name = (this.locales[lang].personal.full_name || 'cv').replace(/\s+/g, '-').toLowerCase();
            const filename = name + '-cv-' + lang + '.pdf';

            try {
                if (typeof window.exportHarvardCvPdf !== 'function') {
                    throw new Error('exportHarvardCvPdf missing');
                }
                await window.exportHarvardCvPdf(el, filename);
                this.pdfExportStatus = 'done';
                this.pdfModalOpen = false;
            } catch (error) {
                console.error('PDF export failed', error);
                this.pdfExportError = this.uiLabels[this.panelLocale].pdf_error;
                this.pdfExportStatus = 'error';
            } finally {
                this.previewLang = previous;
                this.pdfExportingLang = null;
                setTimeout(() => {
                    if (this.pdfExportStatus === 'done') {
                        this.pdfExportStatus = 'idle';
                    }
                }, 2500);
            }
        }
    };
}
</script>
