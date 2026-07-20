export const PANEL_CV_STORAGE_KEY = 'panel-cv-state';
const CV_ANALYSIS_MAX_POLLS = 180;
const CV_MAX_BYTES = 5 * 1024 * 1024;

const CV_UPLOAD_MESSAGES = {
    tr: {
        invalid: 'Yalnızca PDF dosyası yükleyebilirsin.',
        tooLarge: 'Dosya en fazla 5 MB olabilir.',
    },
    en: {
        invalid: 'Only PDF files are allowed.',
        tooLarge: 'File must be 5 MB or smaller.',
    },
};

export function isPdfCvFile(file) {
    if (!file) {
        return false;
    }

    const name = String(file.name || '').toLowerCase();
    const type = String(file.type || '').toLowerCase();

    return type === 'application/pdf' || name.endsWith('.pdf');
}

export function validateCvUploadFile(file, locale = 'tr') {
    const messages = CV_UPLOAD_MESSAGES[locale === 'en' ? 'en' : 'tr'];

    if (!isPdfCvFile(file)) {
        return messages.invalid;
    }

    if (file.size > CV_MAX_BYTES) {
        return messages.tooLarge;
    }

    return null;
}

function formatAnalyzedAt(locale) {
    const d = new Date();
    if (locale === 'en') {
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    return d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function readState() {
    try {
        const raw = localStorage.getItem(PANEL_CV_STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeState(state) {
    localStorage.setItem(PANEL_CV_STORAGE_KEY, JSON.stringify(state));
    window.dispatchEvent(new CustomEvent('panel-cv-updated', { detail: state }));
}

export async function pollCvAnalysis(analysisId, statusUrl, locale, onProgress = null) {
    const url = statusUrl.replace('__ANALYSIS_ID__', encodeURIComponent(analysisId));
    for (let attempt = 0; attempt < CV_ANALYSIS_MAX_POLLS; attempt += 1) {
        await new Promise((resolve) => setTimeout(resolve, attempt === 0 ? 0 : 1000));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'CV analizi başarısız');
        onProgress?.(payload);
        if (payload.status === 'ready') return payload;
        if (payload.status === 'failed') throw new Error(payload.error_message || payload.message || 'CV analizi başarısız');
    }
    throw new Error(locale === 'en' ? 'CV analysis is still running. Refresh this page to check again.' : 'CV analizi hâlâ sürüyor. Durumu kontrol etmek için sayfayı yenile.');
}

function parseSsePayload(event) {
    if (!event?.data) {
        return {};
    }

    try {
        return JSON.parse(event.data);
    } catch {
        return {};
    }
}

export function watchCvAnalysisViaSse(analysisId, streamUrl, locale, onProgress = null) {
    const url = streamUrl.replace('__ANALYSIS_ID__', encodeURIComponent(analysisId));

    return new Promise((resolve, reject) => {
        if (typeof EventSource === 'undefined') {
            reject(new Error('sse_unavailable'));
            return;
        }

        const source = new EventSource(url);
        let settled = false;

        const finish = (handler, value) => {
            if (settled) {
                return;
            }

            settled = true;
            source.close();
            handler(value);
        };

        source.addEventListener('status', (event) => {
            onProgress?.(parseSsePayload(event));
        });

        source.addEventListener('complete', (event) => {
            const payload = parseSsePayload(event);
            onProgress?.(payload);
            finish(resolve, payload);
        });

        source.addEventListener('failed', (event) => {
            const payload = parseSsePayload(event);
            finish(reject, new Error(payload.error_message || payload.message || 'CV analizi başarısız'));
        });

        source.addEventListener('error', (event) => {
            const payload = parseSsePayload(event);
            finish(reject, new Error(payload.message || 'CV analizi başarısız'));
        });

        source.addEventListener('timeout', (event) => {
            const payload = parseSsePayload(event);
            finish(
                reject,
                new Error(
                    payload.message
                        || (locale === 'en'
                            ? 'CV analysis is still running. Refresh this page to check again.'
                            : 'CV analizi hâlâ sürüyor. Durumu kontrol etmek için sayfayı yenile.'),
                ),
            );
        });

        source.onerror = () => {
            if (settled) {
                return;
            }

            settled = true;
            source.close();
            reject(new Error('sse_unavailable'));
        };
    });
}

export async function waitForCvAnalysis(analysisId, { statusUrl, streamUrl, locale, onProgress = null } = {}) {
    if (streamUrl) {
        try {
            return await watchCvAnalysisViaSse(analysisId, streamUrl, locale, onProgress);
        } catch (error) {
            if (error?.message !== 'sse_unavailable') {
                throw error;
            }
        }
    }

    if (!statusUrl) {
        throw new Error(locale === 'en' ? 'CV analysis status endpoint is missing.' : 'CV analiz durumu uç noktası eksik.');
    }

    return pollCvAnalysis(analysisId, statusUrl, locale, onProgress);
}

function reloadAfterCvAnalysis(redirectUrl) {
    if (redirectUrl) {
        window.location.href = redirectUrl;
        return;
    }

    window.location.reload();
}

export const PanelCvStore = {
    get: readState,

    saveBuilder(locales, locale) {
        const persistedLocales = JSON.parse(JSON.stringify(locales));
        const rawName = (persistedLocales?.tr?.personal?.full_name || persistedLocales?.en?.personal?.full_name || 'cv').trim();
        const fileName = `${(rawName || 'cv').replace(/\s+/g, '-').toLowerCase()}-builder.json`;
        const state = {
            source: 'builder',
            fileName,
            locales: persistedLocales,
            savedAt: new Date().toISOString(),
        };
        writeState(state);

        return state;
    },

    saveFromAnalysis(fileName, locale, skillRadar) {
        const state = {
            source: 'upload',
            fileName,
            skillRadar: {
                ...skillRadar,
                analyzed_at: skillRadar.analyzed_at ?? formatAnalyzedAt(locale === 'en' ? 'en' : 'tr'),
            },
            savedAt: new Date().toISOString(),
        };
        writeState(state);

        return state;
    },

    clear() {
        localStorage.removeItem(PANEL_CV_STORAGE_KEY);
        window.dispatchEvent(new CustomEvent('panel-cv-updated', { detail: null }));
    },
};

export function panelCvRadar(labels, serverHasCv = false, serverFileName = '', clearUrl = '') {
    return {
        labels,
        hasCv: Boolean(serverHasCv),
        cvFileName: serverFileName || '',
        clearUrl,
        resetOpen: false,
        resetScope: 'all',
        resetWorking: false,
        resetError: '',

        init() {
            this.hasCv = Boolean(serverHasCv);
            this.cvFileName = serverFileName || '';
        },

        refresh() {
            this.hasCv = Boolean(serverHasCv);
            this.cvFileName = serverFileName || '';
        },

        cvFileDisplay() {
            return (this.labels.cv_file || '').replace(':name', this.cvFileName || 'cv');
        },

        async clearCv() {
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
                if (!response.ok) throw new Error(payload.message || 'Kariyer verileri temizlenemedi');
                PanelCvStore.clear();
                window.location.reload();
            } catch (error) {
                this.resetError = error?.message || 'Kariyer verileri temizlenemedi';
                this.resetWorking = false;
            }
        },
    };
}

export function profileCvUpload(locale, analyzeUrl, statusUrl = '', redirectUrl = '', historyAnalyzeUrl = '', streamUrl = '') {
    return {
        fileName: null,
        locale,
        analyzeUrl,
        statusUrl,
        streamUrl,
        redirectUrl,
        historyAnalyzeUrl,
        loading: false,
        historyLoadingId: null,
        dragOver: false,
        error: null,

        init() {
            this.fileName = null;
        },

        onDragOver(event) {
            event.preventDefault();
            if (this.loading) {
                return;
            }

            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }

            this.dragOver = true;
        },

        onDragLeave(event) {
            event.preventDefault();
            const related = event.relatedTarget;
            if (!event.currentTarget.contains(related)) {
                this.dragOver = false;
            }
        },

        async onDrop(event) {
            event.preventDefault();
            this.dragOver = false;
            if (this.loading) {
                return;
            }

            const file = event.dataTransfer?.files?.[0];
            if (file) {
                await this.handleCvFile(file);
            }
        },

        async onFileSelect(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }

            await this.handleCvFile(file);
            event.target.value = '';
        },

        async handleCvFile(file) {
            const validationError = validateCvUploadFile(file, this.locale);
            if (validationError) {
                this.error = validationError;
                this.fileName = null;
                return;
            }

            this.fileName = file.name;
            this.error = null;
            this.loading = true;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const body = new FormData();
                body.append('cv', file);

                const response = await fetch(this.analyzeUrl, {
                    method: 'POST',
                    headers: token ? { 'X-CSRF-TOKEN': token, Accept: 'application/json' } : { Accept: 'application/json' },
                    body,
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || 'CV analizi başarısız');
                }

                if (payload.status === 'queued' && payload.analysis_id && (this.streamUrl || this.statusUrl)) {
                    this.loading = true;
                    const completed = await waitForCvAnalysis(payload.analysis_id, {
                        statusUrl: this.statusUrl,
                        streamUrl: this.streamUrl,
                        locale: this.locale,
                    });
                    const completedRadar = completed.skill_radar || {
                        overall_match: completed.radar?.reduce((sum, item) => sum + Number(item.score || 0), 0) / Math.max(completed.radar?.length || 1, 1),
                        target_role: completed.current_role || '',
                        skills: completed.radar || [],
                    };
                    PanelCvStore.saveFromAnalysis(file.name, this.locale, completedRadar);
                    reloadAfterCvAnalysis(this.redirectUrl);
                    return;
                }

                const radar = payload.skill_radar || (payload.status === 'ready' && payload.radar ? {
                    overall_match: payload.radar.reduce((sum, item) => sum + Number(item.score || 0), 0) / Math.max(payload.radar.length, 1),
                    target_role: payload.current_role || '',
                    skills: payload.radar,
                } : null);
                if (radar) PanelCvStore.saveFromAnalysis(file.name, this.locale, radar);

                if (payload.redirect) {
                    reloadAfterCvAnalysis(payload.redirect);
                    return;
                }

                if (radar) {
                    reloadAfterCvAnalysis(this.redirectUrl);
                }
            } catch (err) {
                this.error = err?.message || 'CV analizi başarısız';
                this.fileName = null;
            } finally {
                this.loading = false;
            }
        },

        async analyzeHistory(documentId) {
            if (!this.historyAnalyzeUrl || this.historyLoadingId) return;
            this.error = null;
            this.historyLoadingId = documentId;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            try {
                const response = await fetch(this.historyAnalyzeUrl.replace('__DOCUMENT_ID__', encodeURIComponent(documentId)), {
                    method: 'POST', headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}), Accept: 'application/json' },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'CV analizi başlatılamadı');
                const completed = await waitForCvAnalysis(payload.analysis_id, {
                    statusUrl: this.statusUrl,
                    streamUrl: this.streamUrl,
                    locale: this.locale,
                });
                PanelCvStore.saveFromAnalysis(completed.file_name || 'cv', this.locale, {
                    overall_match: completed.radar?.reduce((sum, item) => sum + Number(item.score || 0), 0) / Math.max(completed.radar?.length || 1, 1),
                    target_role: completed.current_role || '', skills: completed.radar || [], analyzed_at: completed.created_at,
                });
                reloadAfterCvAnalysis(this.redirectUrl);
            } catch (err) {
                this.error = err?.message || 'CV analizi başlatılamadı';
                this.historyLoadingId = null;
            }
        },

        removeCv() {
            this.fileName = null;
            this.error = null;
            PanelCvStore.clear();
        },
    };
}
