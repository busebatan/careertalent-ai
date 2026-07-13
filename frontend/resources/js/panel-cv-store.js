export const PANEL_CV_STORAGE_KEY = 'panel-cv-state';

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
    for (let attempt = 0; attempt < 60; attempt += 1) {
        await new Promise((resolve) => setTimeout(resolve, attempt === 0 ? 0 : 1000));
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'CV analizi başarısız');
        onProgress?.(payload);
        if (payload.status === 'ready') return payload;
        if (payload.status === 'failed') throw new Error(payload.error_message || payload.message || 'CV analizi başarısız');
    }
    throw new Error(locale === 'en' ? 'CV analysis timed out' : 'CV analizi zaman aşımına uğradı');
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

export function profileCvUpload(locale, analyzeUrl, statusUrl = '', redirectUrl = '') {
    return {
        fileName: null,
        locale,
        analyzeUrl,
        statusUrl,
        redirectUrl,
        loading: false,
        error: null,

        init() {
            this.fileName = null;
        },

        async onFileSelect(event) {
            const file = event.target.files?.[0];
            if (!file) {
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

                if (payload.status === 'queued' && payload.analysis_id && this.statusUrl) {
                    this.loading = true;
                    const completed = await pollCvAnalysis(payload.analysis_id, this.statusUrl, this.locale);
                    const completedRadar = completed.skill_radar || {
                        overall_match: completed.radar?.reduce((sum, item) => sum + Number(item.score || 0), 0) / Math.max(completed.radar?.length || 1, 1),
                        target_role: completed.current_role || '',
                        skills: completed.radar || [],
                    };
                    PanelCvStore.saveFromAnalysis(file.name, this.locale, completedRadar);
                    if (this.redirectUrl) window.location.href = this.redirectUrl;
                    return;
                }

                const radar = payload.skill_radar || (payload.status === 'ready' && payload.radar ? {
                    overall_match: payload.radar.reduce((sum, item) => sum + Number(item.score || 0), 0) / Math.max(payload.radar.length, 1),
                    target_role: payload.current_role || '',
                    skills: payload.radar,
                } : null);
                if (radar) PanelCvStore.saveFromAnalysis(file.name, this.locale, radar);

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }
            } catch (err) {
                this.error = err?.message || 'CV analizi başarısız';
                this.fileName = null;
            } finally {
                this.loading = false;
                event.target.value = '';
            }
        },

        removeCv() {
            this.fileName = null;
            this.error = null;
            PanelCvStore.clear();
        },
    };
}
