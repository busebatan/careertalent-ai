<script>
function cvBuilder(initial, uiLabels, panelLocale, serverHasCv = false, serverFileName = '', analyzeBuilderUrl = '', clearUrl = '', statusUrl = '', archivePdfUrl = '', restoredFromHistory = false, streamUrl = '', serverAnalysisStatus = '', serverAnalysisId = '', builderImportNoticeDismissUrl = '', pdfRenderUrl = '') {
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
        pdfRenderUrl,
        pdfBlobCache: new Map(),
        pdfPreviewUrl: '',
        pdfPreviewLoading: false,
        pdfPreviewError: '',
        pdfPreviewRequestId: 0,
        restoredFromHistory,
        builderImportNoticeOpen: true,
        builderImportNoticeBusy: false,
        builderImportNoticeError: '',
        builderImportNoticeDismissUrl,
        saveStatus: 'idle',
        builderHydrating: true,
        cleanBuilderSnapshot: '',
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
        versionActionModalOpen: false,
        versionActionKind: '',
        versionActionVersion: null,
        versionActionTitle: '',
        versionActionDescription: '',
        versionActionConfirmLabel: '',
        versionActionBusy: false,
        versionActionError: '',
        versionNotice: '',
        versionNoticeTone: 'success',
        _versionNoticeTimer: null,
        _skipLocalesSync: false,
        _versionsInitialized: false,

        async init() {
            if (serverHasCv) {
                this.cvFileName = serverFileName || this.cvFileName;
            }

            // F5 / sayfa yükleme: localStorage taslak verisini yoksay;
            // Ana CV sürümü fetchVersions() tamamlandığında editöre otomatik yüklenecek.
            this.normalizeAllLocales();
            this.$watch('locales', () => this.invalidatePdfCache());
            window.addEventListener('panel-cv-updated', () => this.syncFromStore());
            await this.fetchVersions();
            this.markBuilderClean();
            this.builderHydrating = false;
            this.resumePendingAnalysis();
        },

        get hasUnsavedChanges() {
            return !this.builderHydrating
                && (window.PanelCvStore?.builderChanged(this.locales, this.cleanBuilderSnapshot) ?? false);
        },

        markBuilderClean() {
            this.cleanBuilderSnapshot = window.PanelCvStore?.snapshotBuilder(this.locales)
                ?? JSON.stringify(this.locales || {});
        },

        async dismissBuilderImportNotice() {
            if (!this.builderImportNoticeDismissUrl || this.builderImportNoticeBusy) {
                return;
            }
            this.builderImportNoticeBusy = true;
            this.builderImportNoticeError = '';
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            try {
                const response = await fetch(this.builderImportNoticeDismissUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.builder_import_notice_dismissed !== true) {
                    throw new Error(payload.message || this.uiLabels[this.panelLocale]?.import_notice_close_failed);
                }
                this.builderImportNoticeOpen = false;
            } catch (error) {
                this.builderImportNoticeError = error?.message
                    || this.uiLabels[this.panelLocale]?.import_notice_close_failed
                    || 'Bildirim kapatılamadı.';
            } finally {
                this.builderImportNoticeBusy = false;
            }
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

        setEditLanguage(language) {
            if (!['tr', 'en'].includes(language)) {
                return;
            }
            this.editLang = language;
            this.previewLang = language;
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

        pdfSnapshotKey(language) {
            return `${language}:${JSON.stringify(this.locales)}`;
        },

        invalidatePdfCache() {
            this.pdfBlobCache.clear();
            this.clearPdfPreviewUrl();
        },

        clearPdfPreviewUrl() {
            if (this.pdfPreviewUrl) {
                URL.revokeObjectURL(this.pdfPreviewUrl);
                this.pdfPreviewUrl = '';
            }
        },

        async getServerPdfBlob(language) {
            const key = this.pdfSnapshotKey(language);
            const cached = this.pdfBlobCache.get(key);
            if (cached) {
                return cached;
            }
            if (typeof window.requestServerCvPdf !== 'function') {
                throw new Error('PDF exporter missing');
            }

            const snapshot = JSON.parse(JSON.stringify(this.locales));
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const blob = await window.requestServerCvPdf(this.pdfRenderUrl, {
                language,
                locales: snapshot,
                csrfToken: token,
            });

            // Kullanıcı istek sürerken içerik değiştirdiyse eski PDF hiç kullanılmaz.
            if (key !== this.pdfSnapshotKey(language)) {
                return this.getServerPdfBlob(language);
            }

            this.pdfBlobCache.set(key, blob);
            return blob;
        },

        async showPdfPreview(language = this.previewLang) {
            const requestId = ++this.pdfPreviewRequestId;
            this.pdfPreviewLoading = true;
            this.pdfPreviewError = '';
            this.previewLang = language;
            this.mode = 'preview';
            try {
                const blob = await this.getServerPdfBlob(language);
                if (requestId !== this.pdfPreviewRequestId || this.previewLang !== language) {
                    return;
                }
                this.clearPdfPreviewUrl();
                this.pdfPreviewUrl = URL.createObjectURL(blob);
            } catch (error) {
                if (requestId === this.pdfPreviewRequestId) {
                    this.pdfPreviewError = error?.message || this.uiLabels[this.panelLocale]?.pdf_error;
                }
            } finally {
                if (requestId === this.pdfPreviewRequestId) {
                    this.pdfPreviewLoading = false;
                }
            }
        },

        async togglePreview() {
            if (this.mode === 'preview') {
                this.pdfPreviewRequestId += 1;
                this.mode = 'edit';
                this.pdfPreviewLoading = false;
                this.pdfPreviewError = '';
                this.clearPdfPreviewUrl();
                return;
            }
            await this.showPdfPreview(this.previewLang);
        },

        async setPreviewLanguage(language) {
            this.previewLang = language;
            if (this.mode === 'preview') {
                await this.showPdfPreview(language);
            }
        },

        async saveCv() {
            if (!this.analyzeBuilderUrl || !this.hasUnsavedChanges) {
                return;
            }

            this.saveStatus = 'saving';
            this.analyzeError = null;
            try {
                const language = this.editLang;
                const rawName = this.locales[language]?.personal?.full_name || 'CV';
                const safeName = `${rawName} CV`.trim().replace(/[\/:*?"<>|]/g, '-');
                const filename = `${safeName || 'CV'}.pdf`;
                const blob = await this.getServerPdfBlob(language);
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
                window.location.reload();
            } catch (err) {
                this.analyzeError = err?.message || 'CV analizi başarısız';
                this.saveStatus = 'idle';
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
            const chosen = this.pdfFileName.trim().replace(/[\/:*?"<>|]/g, '-');
            if (!chosen) {
                this.pdfExportError = this.uiLabels[this.panelLocale].pdf_file_name_required;
                this.pdfExportStatus = 'error'; this.pdfExportingLang = null; return;
            }
            const filename = (chosen.toLowerCase().endsWith('.pdf') ? chosen : chosen + '.pdf');

            try {
                if (typeof window.downloadPdfBlob !== 'function') {
                    throw new Error('PDF downloader missing');
                }
                const blob = await this.getServerPdfBlob(lang);
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
                            const linkedVersions = window.PanelCvStore?.linkedBuilderVersions(this.cvVersions, mainVersion)
                                ?? [mainVersion];
                            linkedVersions.forEach((version) => {
                                if (['tr', 'en'].includes(version.language) && version.payload) {
                                    this.locales[version.language] = JSON.parse(JSON.stringify(version.payload));
                                }
                            });
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

        showVersionNotice(message, tone = 'success') {
            this.versionNotice = message || '';
            this.versionNoticeTone = tone;
            if (this._versionNoticeTimer) {
                clearTimeout(this._versionNoticeTimer);
            }
            this._versionNoticeTimer = setTimeout(() => {
                this.versionNotice = '';
                this._versionNoticeTimer = null;
            }, 5000);
        },

        requestVersionLoad(version) {
            this.versionActionKind = 'load';
            this.versionActionVersion = version;
            this.versionActionTitle = this.panelLocale === 'en' ? 'Load version into editor?' : 'Sürüm editöre yüklensin mi?';
            this.versionActionDescription = this.panelLocale === 'en'
                ? 'Unsaved changes in the editor will be replaced by the selected version.'
                : 'Editördeki kaydedilmemiş değişiklikler seçilen sürümle değiştirilecek.';
            this.versionActionConfirmLabel = this.panelLocale === 'en' ? 'Load into editor' : 'Editöre yükle';
            this.versionActionError = '';
            this.versionActionModalOpen = true;
        },

        requestVersionDelete(version) {
            this.versionActionKind = 'delete';
            this.versionActionVersion = version;
            this.versionActionTitle = this.panelLocale === 'en' ? 'Delete this version?' : 'Bu sürüm silinsin mi?';
            this.versionActionDescription = this.panelLocale === 'en'
                ? `"${version.version_name}" will be permanently removed.`
                : `"${version.version_name}" kalıcı olarak silinecek.`;
            this.versionActionConfirmLabel = this.panelLocale === 'en' ? 'Delete version' : 'Sürümü sil';
            this.versionActionError = '';
            this.versionActionModalOpen = true;
        },

        closeVersionActionModal() {
            if (this.versionActionBusy) {
                return;
            }
            this.versionActionModalOpen = false;
            this.versionActionKind = '';
            this.versionActionVersion = null;
            this.versionActionError = '';
        },

        async confirmVersionAction() {
            const version = this.versionActionVersion;
            if (!version || this.versionActionBusy) {
                return;
            }
            if (this.versionActionKind === 'load') {
                this.loadVersion(version);
                this.closeVersionActionModal();
                this.showVersionNotice(
                    this.panelLocale === 'en' ? 'Version loaded into the editor.' : 'Sürüm editöre yüklendi.'
                );
                return;
            }
            if (this.versionActionKind === 'delete') {
                await this.deleteVersion(version);
            }
        },

        loadVersion(version) {
            this.locales[version.language] = JSON.parse(JSON.stringify(version.payload));
            this.normalizeAllLocales();
            this.editLang = version.language;
            this.previewLang = version.language;
            // Aktif yüklenen sürümü sadece manuel "Editöre Yükle" ile güncelle
            this.activeLoadedVersionId = version.id;
            this.markBuilderClean();
            if (window.PanelCvStore) {
                window.PanelCvStore.saveBuilder(this.locales, this.panelLocale);
            }
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
                    this.showVersionNotice(
                        this.panelLocale === 'en' ? 'Main CV version updated.' : 'Ana CV sürümü güncellendi.'
                    );
                } else {
                    const data = await response.json();
                    this.showVersionNotice(data.message || 'Ana sürüm ayarlanamadı.', 'error');
                }
            } catch (err) {
                this.showVersionNotice(
                    this.panelLocale === 'en' ? 'The main version could not be updated.' : 'Ana sürüm güncellenemedi.',
                    'error'
                );
            }
        },

        async deleteVersion(version) {
            this.versionActionBusy = true;
            this.versionActionError = '';
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
                    this.versionActionBusy = false;
                    this.closeVersionActionModal();
                    this.showVersionNotice(
                        this.panelLocale === 'en' ? 'CV version deleted.' : 'CV sürümü silindi.'
                    );
                } else {
                    const data = await response.json().catch(() => ({}));
                    this.versionActionError = data.message
                        || (this.panelLocale === 'en' ? 'The version could not be deleted.' : 'Sürüm silinemedi.');
                }
            } catch (err) {
                this.versionActionError = this.panelLocale === 'en'
                    ? 'The version could not be deleted.'
                    : 'Sürüm silinemedi.';
            } finally {
                this.versionActionBusy = false;
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
                    this.showVersionNotice(
                        this.panelLocale === 'en' ? 'Version name updated.' : 'Sürüm adı güncellendi.'
                    );
                } else {
                    const data = await response.json();
                    this.showVersionNotice(data.message || 'Yeniden adlandırma başarısız.', 'error');
                }
            } catch (err) {
                this.showVersionNotice(
                    this.panelLocale === 'en' ? 'The version could not be renamed.' : 'Sürüm yeniden adlandırılamadı.',
                    'error'
                );
            }
        }
    };
}
</script>
