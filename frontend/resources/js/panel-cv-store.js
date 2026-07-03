export const PANEL_CV_STORAGE_KEY = 'panel-cv-state';

function formatAnalyzedAt(locale) {
    const d = new Date();
    if (locale === 'en') {
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    return d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Demo AI radar verisi — görsel şölen; gerçek analiz API'si bağlanınca güncellenecek. */
export function demoSkillAnalysis(locale) {
    const lang = locale === 'en' ? 'en' : 'tr';

    return {
        overall_match: 72,
        analyzed_at: formatAnalyzedAt(lang),
        target_role: lang === 'en' ? 'Junior Data Analyst' : 'Junior Veri Analisti',
        skills: lang === 'en'
            ? [
                { label: 'SQL', score: 85, target: 90 },
                { label: 'Python', score: 72, target: 85 },
                { label: 'Excel', score: 88, target: 80 },
                { label: 'Statistics', score: 68, target: 75 },
                { label: 'Visualization', score: 45, target: 70 },
                { label: 'Communication', score: 76, target: 80 },
                { label: 'English', score: 62, target: 75 },
                { label: 'Domain knowledge', score: 58, target: 65 },
            ]
            : [
                { label: 'SQL', score: 85, target: 90 },
                { label: 'Python', score: 72, target: 85 },
                { label: 'Excel', score: 88, target: 80 },
                { label: 'İstatistik', score: 68, target: 75 },
                { label: 'Görselleştirme', score: 45, target: 70 },
                { label: 'İletişim', score: 76, target: 80 },
                { label: 'İngilizce', score: 62, target: 75 },
                { label: 'Alan bilgisi', score: 58, target: 65 },
            ],
    };
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
            skillRadar: demoSkillAnalysis(locale),
            savedAt: new Date().toISOString(),
        };
        writeState(state);

        return state;
    },

    saveUpload(fileName, locale) {
        const state = {
            source: 'upload',
            fileName,
            skillRadar: demoSkillAnalysis(locale),
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

export function panelCvRadar(labels) {
    return {
        labels,
        hasCv: false,
        cvFileName: '',

        init() {
            this.refresh();
            window.addEventListener('panel-cv-updated', () => this.refresh());
        },

        refresh() {
            const state = PanelCvStore.get();
            this.hasCv = Boolean(state?.skillRadar);
            this.cvFileName = state?.fileName ?? '';
        },

        cvFileDisplay() {
            return (this.labels.cv_file || '').replace(':name', this.cvFileName || 'cv');
        },

        clearCv() {
            PanelCvStore.clear();
            this.refresh();
        },
    };
}

export function profileCvUpload(locale) {
    return {
        fileName: null,
        locale,

        init() {
            const state = PanelCvStore.get();
            if (state?.fileName) {
                this.fileName = state.fileName;
            }
        },

        onFileSelect(event) {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }
            this.fileName = file.name;
            PanelCvStore.saveUpload(file.name, this.locale);
        },

        removeCv() {
            this.fileName = null;
            PanelCvStore.clear();
        },
    };
}
