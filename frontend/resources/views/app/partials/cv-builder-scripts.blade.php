<script>
function cvBuilder(initial, uiLabels, panelLocale, serverHasCv = false, serverFileName = '', analyzeBuilderUrl = '', clearUrl = '', statusUrl = '', archivePdfUrl = '', restoredFromHistory = false, streamUrl = '', serverAnalysisStatus = '', serverAnalysisId = '') {
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
        pdfFileName: '',
        archivePdfUrl,
        restoredFromHistory,
        saveStatus: 'idle',
        hasReadyAnalysis: serverHasCv,
        serverAnalysisStatus,
        serverAnalysisId,
        analyzeError: null,
        radarExpanded: window.readCvRadarExpanded?.(serverAnalysisId) ?? true,
        cvFileName: serverFileName || '',
        analyzeBuilderUrl,
        clearUrl,
        resetOpen: false,
        resetScope: 'all',
        resetWorking: false,
        resetError: '',
        statusUrl,
        streamUrl,
        cvFileLabel: @js(__('panel.skill_radar.cv_file', ['name' => ':name'])),
        optionalSectionPick: '',
        cvVersions: [],
        activeLoadedVersionId: null,
        showVersionCreateModal: false,
        newVersionName: '',
        newVersionLang: 'tr',
        newVersionIsMain: false,
        versionError: '',
        listVersionsUrl: @js(route('panel.cv.versions.list')),
        createVersionUrl: @js(route('panel.cv.versions.create')),
        updateVersionUrl: @js(route('panel.cv.versions.update', ['id' => '__ID__'])),
        deleteVersionUrl: @js(route('panel.cv.versions.delete', ['id' => '__ID__'])),
        previewVersionModalOpen: false,
        previewVersionData: null,
        renamingVersionId: null,
        renameInput: '',
        _skipLocalesSync: false,
        _versionsInitialized: false,

        init() {
            if (serverHasCv) {
                this.cvFileName = serverFileName || this.cvFileName;
            }

            // F5 / sayfa yükleme: localStorage taslak verisini yoksay;
            // Ana CV sürümü fetchVersions() tamamlandığında editöre otomatik yüklenecek.
            this.normalizeAllLocales();
            window.addEventListener('panel-cv-updated', () => this.syncFromStore());
            this.fetchVersions();
            this.resumePendingAnalysis();
        },

        async resumePendingAnalysis() {
            if (!['queued', 'running'].includes(this.serverAnalysisStatus) || !this.serverAnalysisId || !window.waitForCvAnalysis) {
                return;
            }
            try {
                await window.waitForCvAnalysis(this.serverAnalysisId, {
                    statusUrl: this.statusUrl,
                    streamUrl: this.streamUrl,
                    locale: this.panelLocale,
                });
                window.location.reload();
            } catch (error) {
                this.serverAnalysisStatus = 'failed';
                this.analyzeError = error?.message || this.uiLabels[this.panelLocale]?.analyze_failed || 'CV analizi başarısız';
            }
        },

        syncFromStore() {
            const saved = window.PanelCvStore?.get();

            if (this._skipLocalesSync) {
                this._skipLocalesSync = false;
                return;
            }

            if (saved?.source === 'builder' && saved.locales) {
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

        analysisPending() {
            return this.saveStatus === 'saving' || ['queued', 'running'].includes(this.serverAnalysisStatus);
        },

        onRadarToggle(event) {
            this.radarExpanded = event.target.open;
            window.persistCvRadarExpanded?.(this.serverAnalysisId, this.radarExpanded);
        },

        uid() { return 'id-' + Math.random().toString(36).slice(2, 9); },

        async waitForPreviewRender() {
            await this.$nextTick();
            await new Promise((resolve) => {
                requestAnimationFrame(() => requestAnimationFrame(resolve));
            });
            await new Promise((resolve) => setTimeout(resolve, 350));
        },

        async saveCv() {
            if (!this.analyzeBuilderUrl) {
                return;
            }

            this.saveStatus = 'saving';
            this.analyzeError = null;
            const previousPreviewLang = this.previewLang;

            try {
                if (typeof window.renderHarvardCvPdf !== 'function') {
                    throw new Error('PDF exporter missing');
                }

                const language = this.editLang;
                this.previewLang = language;
                await this.waitForPreviewRender();
                const preview = document.getElementById('harvard-preview');
                const rawName = this.locales[language]?.personal?.full_name || 'CV';
                const safeName = `${rawName} CV`.trim().replace(/[\\/:*?"<>|]/g, '-');
                const filename = `${safeName || 'CV'}.pdf`;
                const blob = await window.renderHarvardCvPdf(preview, filename);
                const form = new FormData();
                form.append('pdf', blob, filename);
                form.append('display_name', filename);
                form.append('language', language);
                form.append('locales', JSON.stringify(this.locales));
                this.cvFileName = filename;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(this.analyzeBuilderUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: form,
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || this.uiLabels[this.panelLocale]?.analyze_failed || 'CV analizi başarısız');
                }

                if (payload.status === 'queued' && payload.analysis_id && (this.streamUrl || this.statusUrl) && window.waitForCvAnalysis) {
                    const completed = await window.waitForCvAnalysis(payload.analysis_id, {
                        statusUrl: this.statusUrl,
                        streamUrl: this.streamUrl,
                        locale: this.panelLocale,
                    });
                    const radar = completed.skill_radar || {
                        overall_match: completed.radar?.reduce((sum, item) => sum + Number(item.score || 0), 0) / Math.max(completed.radar?.length || 1, 1),
                        target_role: completed.current_role || '',
                        skills: completed.radar || [],
                    };
                    window.PanelCvStore?.saveBuilder(this.locales, this.panelLocale);
                    window.PanelCvStore?.saveFromAnalysis(payload.file_name, this.panelLocale, radar);
                } else if (payload.skill_radar || (payload.status === 'ready' && payload.radar)) {
                    const radar = payload.skill_radar || {
                        overall_match: payload.radar.reduce((sum, item) => sum + Number(item.score || 0), 0) / Math.max(payload.radar.length, 1),
                        target_role: payload.current_role || '',
                        skills: payload.radar,
                    };
                    window.PanelCvStore?.saveBuilder(this.locales, this.panelLocale);
                    window.PanelCvStore?.saveFromAnalysis(payload.file_name, this.panelLocale, radar);
                }

                this.saveStatus = 'saved';
                this.previewLang = previousPreviewLang;
                window.location.reload();
            } catch (err) {
                this.analyzeError = err?.message || 'CV analizi başarısız';
                this.saveStatus = 'idle';
                this.previewLang = previousPreviewLang;
            }
        },

        async clearCvAnalysis() {
            if (!this.clearUrl || this.resetWorking) return;
            this.resetWorking = true;
            this.resetError = '';
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            try {
                const response = await fetch(this.clearUrl, {
                    method: 'POST',
                    headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}), 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ scope: this.resetScope }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || @js(__('panel.skill_radar.reset_failed')));
                window.PanelCvStore?.clear();
                window.location.reload();
            } catch (error) {
                this.resetError = error?.message || @js(__('panel.skill_radar.reset_failed'));
                this.resetWorking = false;
            }
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
            const rawName = this.locales[this.previewLang]?.personal?.full_name || 'cv';
            this.pdfFileName = this.pdfFileName || `${rawName} CV`;
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
            const chosen = this.pdfFileName.trim().replace(/[\\/:*?"<>|]/g, '-');
            if (!chosen) {
                this.pdfExportError = this.uiLabels[this.panelLocale].pdf_file_name_required;
                this.pdfExportStatus = 'error'; this.pdfExportingLang = null; this.previewLang = previous; return;
            }
            const filename = (chosen.toLowerCase().endsWith('.pdf') ? chosen : chosen + '.pdf');

            try {
                if (typeof window.renderHarvardCvPdf !== 'function' || typeof window.downloadPdfBlob !== 'function') {
                    throw new Error('PDF exporter missing');
                }
                const blob = await window.renderHarvardCvPdf(el, filename);
                const form = new FormData();
                form.append('pdf', blob, filename); form.append('display_name', filename); form.append('language', lang); form.append('builder_data', JSON.stringify(this.locales));
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(this.archivePdfUrl, { method: 'POST', headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}), Accept: 'application/json' }, body: form });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || this.uiLabels[this.panelLocale].pdf_archive_error);
                window.downloadPdfBlob(blob, filename);
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
        },

        async fetchVersions() {
            try {
                const response = await fetch(this.listVersionsUrl);
                if (response.ok) {
                    this.cvVersions = await response.json();

                    // İlk yükleme: is_main === true olan sürümü editöre otomatik yükle
                    if (!this.restoredFromHistory && !this._versionsInitialized) {
                        this._versionsInitialized = true;
                        const mainVersion = this.cvVersions.find(v => v.is_main === true);
                        if (mainVersion) {
                            this.locales[mainVersion.language] = JSON.parse(JSON.stringify(mainVersion.payload));
                            this.normalizeAllLocales();
                            this.editLang = mainVersion.language;
                            this.previewLang = mainVersion.language;
                            this.activeLoadedVersionId = mainVersion.id;
                        }
                    } else if (!this._versionsInitialized) {
                        this._versionsInitialized = true;
                    }
                }
            } catch (err) {
                // handle error
            }
        },

        openCreateVersionModal() {
            this.newVersionName = '';
            this.newVersionLang = this.editLang;
            this.newVersionIsMain = false;
            this.versionError = '';
            this.showVersionCreateModal = true;
        },

        async createVersionFromCurrent() {
            if (!this.newVersionName.trim()) {
                this.versionError = 'Lütfen bir sürüm adı girin.';
                return;
            }
            this.versionError = '';
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(this.createVersionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({
                        version_name: this.newVersionName,
                        language: this.newVersionLang,
                        is_main: this.newVersionIsMain,
                        payload: this.locales[this.newVersionLang]
                    })
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Sürüm oluşturulamadı');
                }
                this.showVersionCreateModal = false;
                await this.fetchVersions();
            } catch (err) {
                this.versionError = err.message;
            }
        },

        async loadVersion(version) {
            if (!confirm('Seçilen sürümün içeriği editöre yüklenecektir. Kaydedilmemiş değişiklikler kaybolabilir. Devam etmek istiyor musunuz?')) {
                return;
            }
            this.locales[version.language] = JSON.parse(JSON.stringify(version.payload));
            this.normalizeAllLocales();
            this.editLang = version.language;
            this.previewLang = version.language;
            // Aktif yüklenen sürümü sadece manuel "Editöre Yükle" ile güncelle
            this.activeLoadedVersionId = version.id;
            if (window.PanelCvStore) {
                window.PanelCvStore.saveBuilder(this.locales, this.panelLocale);
            }
            alert('Sürüm başarıyla yüklendi!');
        },

        async setVersionMain(version) {
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = this.updateVersionUrl.replace('__ID__', version.id);
                const response = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({
                        is_main: true
                    })
                });
                if (response.ok) {
                    await this.fetchVersions();
                } else {
                    const data = await response.json();
                    alert(data.message || 'Ana sürüm ayarlanamadı');
                }
            } catch (err) {
                alert('Bir hata oluştu');
            }
        },

        async deleteVersion(version) {
            if (!confirm('Bu sürümü silmek istediğinize emin misiniz?')) {
                return;
            }
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = this.deleteVersionUrl.replace('__ID__', version.id);
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    }
                });
                if (response.ok) {
                    await this.fetchVersions();
                } else {
                    alert('Sürüm silinemedi');
                }
            } catch (err) {
                alert('Bir hata oluştu');
            }
        },

        openVersionPreview(version) {
            this.previewVersionData = version;
            this.previewVersionModalOpen = true;
        },

        closeVersionPreview() {
            this.previewVersionModalOpen = false;
            this.previewVersionData = null;
        },

        startRename(version) {
            this.renamingVersionId = version.id;
            this.renameInput = version.version_name;
            this.$nextTick(() => {
                const el = this.$root.querySelector('#rename-version-input-' + version.id);
                if (el) {
                    el.focus();
                    el.select();
                }
            });
        },

        cancelRename() {
            this.renamingVersionId = null;
            this.renameInput = '';
        },

        async confirmRename(version) {
            const name = this.renameInput.trim();
            if (!name || name === version.version_name) {
                this.cancelRename();
                return;
            }
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const url = this.updateVersionUrl.replace('__ID__', version.id);
                const response = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({
                        version_name: name
                    })
                });
                if (response.ok) {
                    this.cancelRename();
                    await this.fetchVersions();
                } else {
                    const data = await response.json();
                    alert(data.message || 'Yeniden adlandırma başarısız');
                }
            } catch (err) {
                alert('Bir hata oluştu');
            }
        }
    };
}
</script>
