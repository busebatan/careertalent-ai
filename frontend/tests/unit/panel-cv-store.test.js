import assert from 'node:assert/strict';
import { beforeEach, describe, it } from 'node:test';

const storage = new Map();

function installBrowserMocks() {
    globalThis.localStorage = {
        getItem: (key) => (storage.has(key) ? storage.get(key) : null),
        setItem: (key, value) => {
            storage.set(key, value);
        },
        removeItem: (key) => {
            storage.delete(key);
        },
    };

    globalThis.window = {
        dispatchEvent: () => true,
        location: { reload: () => true },
    };
    globalThis.document = {
        querySelector: () => ({ getAttribute: () => 'csrf-token' }),
    };
}

installBrowserMocks();

const {
    PanelCvStore,
    PANEL_CV_STORAGE_KEY,
    isPdfCvFile,
    panelCvRadar,
    persistCvRadarExpanded,
    pollCvAnalysis,
    profileCvUpload,
    readCvRadarExpanded,
    validateCvUploadFile,
    waitForCvAnalysis,
    watchCvAnalysisViaSse,
} = await import('../../resources/js/panel-cv-store.js');

function sampleLocales() {
    return {
        tr: {
            personal: { full_name: 'Ali Veli' },
            enabledOptional: ['languages'],
            optional: {
                languages: [{ id: 'lang-tr', language: 'Türkçe', level: 'Ana dil' }],
            },
        },
        en: {
            personal: { full_name: 'Ali Veli' },
            enabledOptional: ['languages'],
            optional: {
                languages: [{ id: 'lang-en', language: 'English', level: 'Fluent' }],
            },
        },
    };
}

describe('CV radar expansion persistence', () => {
    beforeEach(() => {
        storage.clear();
    });

    it('opens a new analysis and remembers a collapsed analysis after login reloads', () => {
        assert.equal(readCvRadarExpanded('analysis-new'), true);

        persistCvRadarExpanded('analysis-new', false);

        assert.equal(readCvRadarExpanded('analysis-new'), false);
        assert.equal(readCvRadarExpanded('analysis-next'), true);
    });

    it('falls back to open when stored state is malformed or lacks an analysis id', () => {
        localStorage.setItem('panel-cv-radar-expanded', '0');

        assert.equal(readCvRadarExpanded('analysis-new'), true);
        assert.equal(readCvRadarExpanded(''), true);
    });
});

describe('PanelCvStore.saveBuilder', () => {
    beforeEach(() => {
        storage.clear();
    });

    it('persists enabledOptional and optional for both locales', () => {
        const locales = sampleLocales();

        PanelCvStore.saveBuilder(locales, 'tr');
        const saved = PanelCvStore.get();

        assert.equal(saved.source, 'builder');
        assert.deepEqual(saved.locales.tr.enabledOptional, ['languages']);
        assert.equal(saved.locales.tr.optional.languages[0].language, 'Türkçe');
        assert.equal(saved.locales.en.optional.languages[0].language, 'English');
        assert.equal(localStorage.getItem(PANEL_CV_STORAGE_KEY)?.includes('enabledOptional'), true);
    });

    it('deep clones locales so later mutations do not change stored state', () => {
        const locales = sampleLocales();

        PanelCvStore.saveBuilder(locales, 'tr');
        locales.tr.enabledOptional.push('awards');
        locales.tr.optional.awards = [{ id: 'award-1', title: 'Yeni', issuer: '', date: '', details: '' }];

        const saved = PanelCvStore.get();

        assert.deepEqual(saved.locales.tr.enabledOptional, ['languages']);
        assert.equal(saved.locales.tr.optional.awards, undefined);
    });

    it('round-trips multiple optional section keys', () => {
        const locales = sampleLocales();
        locales.tr.enabledOptional.push('awards', 'volunteer');
        locales.tr.optional.awards = [{ id: 'a1', title: 'Birincilik', issuer: 'Üniversite', date: '2024', details: '' }];
        locales.tr.optional.volunteer = [{
            id: 'v1',
            organization: 'TEV',
            role: 'Gönüllü',
            location: 'İstanbul',
            start: '2023',
            end: '2024',
            bullets: ['Eğitim desteği'],
        }];
        locales.en.enabledOptional.push('awards');
        locales.en.optional.awards = [{ id: 'a2', title: 'First place', issuer: 'University', date: '2024', details: '' }];

        PanelCvStore.saveBuilder(locales, 'en');
        const saved = PanelCvStore.get();

        assert.deepEqual(saved.locales.tr.enabledOptional, ['languages', 'awards', 'volunteer']);
        assert.equal(saved.locales.tr.optional.volunteer[0].organization, 'TEV');
        assert.deepEqual(saved.locales.en.enabledOptional, ['languages', 'awards']);
    });
});

describe('panelCvRadar career reset', () => {
    beforeEach(() => {
        storage.clear();
    });

    it('sends the selected reset scope and reloads only after success', async () => {
        let request;
        let reloads = 0;
        globalThis.fetch = async (url, options) => {
            request = { url, options };
            return { ok: true, json: async () => ({ status: 'cleared', scope: 'plan' }) };
        };
        window.location.reload = () => { reloads += 1; };
        PanelCvStore.saveBuilder(sampleLocales(), 'tr');
        const state = panelCvRadar({}, true, 'cv.pdf', '/panel/cv-merkezi/temizle');
        state.resetScope = 'plan';

        await state.clearCv();

        assert.equal(request.url, '/panel/cv-merkezi/temizle');
        assert.equal(request.options.method, 'POST');
        assert.deepEqual(JSON.parse(request.options.body), { scope: 'plan' });
        assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.equal(PanelCvStore.get(), null);
        assert.equal(reloads, 1);
    });

    it('keeps the page and local radar when the reset request fails', async () => {
        let reloads = 0;
        globalThis.fetch = async () => ({ ok: false, json: async () => ({ message: 'Reset failed' }) });
        window.location.reload = () => { reloads += 1; };
        PanelCvStore.saveBuilder(sampleLocales(), 'tr');
        const state = panelCvRadar({}, true, 'cv.pdf', '/panel/cv-merkezi/temizle');

        await state.clearCv();

        assert.equal(state.resetError, 'Reset failed');
        assert.equal(state.resetWorking, false);
        assert.notEqual(PanelCvStore.get(), null);
        assert.equal(reloads, 0);
    });
});

describe('profileCvUpload drag and drop', () => {
    beforeEach(() => {
        storage.clear();
    });

    it('accepts PDF files dropped onto the upload zone', async () => {
        globalThis.fetch = async () => ({
            ok: true,
            json: async () => ({ status: 'ready', skill_radar: { overall_match: 70, skills: [] } }),
        });
        const state = profileCvUpload('tr', '/upload');
        const file = new File(['pdf'], 'cv.pdf', { type: 'application/pdf' });

        await state.onDrop({ preventDefault() {}, dataTransfer: { files: [file] } });

        assert.equal(state.fileName, 'cv.pdf');
        assert.equal(state.error, null);
        assert.equal(PanelCvStore.get().fileName, 'cv.pdf');
    });

    it('rejects non-pdf drops with a localized error', async () => {
        const state = profileCvUpload('tr', '/upload');
        const file = new File(['txt'], 'notes.txt', { type: 'text/plain' });

        await state.onDrop({ preventDefault() {}, dataTransfer: { files: [file] } });

        assert.equal(state.error, 'Yalnızca PDF dosyası yükleyebilirsin.');
        assert.equal(state.fileName, null);
    });

    it('toggles dragOver while dragging over the upload zone', () => {
        const state = profileCvUpload('en', '/upload');
        const zone = { contains() { return false; } };

        state.onDragOver({ preventDefault() {}, currentTarget: zone, dataTransfer: { dropEffect: '' } });
        assert.equal(state.dragOver, true);

        state.onDragLeave({ preventDefault() {}, currentTarget: zone, relatedTarget: null });
        assert.equal(state.dragOver, false);
    });
});

describe('validateCvUploadFile', () => {
    it('flags oversized PDF files', () => {
        const file = new File([new Uint8Array((5 * 1024 * 1024) + 1)], 'big.pdf', { type: 'application/pdf' });
        assert.equal(validateCvUploadFile(file, 'en'), 'File must be 5 MB or smaller.');
        assert.equal(isPdfCvFile(file), true);
    });
});

describe('profileCvUpload archived CV analysis', () => {
    beforeEach(() => {
        storage.clear();
        window.location.href = '';
    });

    it('keeps the roadmap CTA ready after a server-rendered archived analysis reload', () => {
        const state = profileCvUpload('tr', '/upload', '/status/__ANALYSIS_ID__', '', '/history/__DOCUMENT_ID__/analyze', '', true);

        assert.equal(state.historyAnalysisReady, true);
    });

    it('starts a fresh analysis, polls it and exposes the roadmap CTA with exact CV metadata', async () => {
        const requests = [];
        globalThis.fetch = async (url, options = {}) => {
            requests.push({ url, options });
            if (options.method === 'POST') {
                return { ok: true, json: async () => ({ analysis_id: 'analysis-123', status: 'queued' }) };
            }
            return { ok: true, json: async () => ({
                id: 'analysis-123', status: 'ready', file_name: 'İlan CV.pdf', created_at: '2026-07-13T22:56:42Z',
                current_role: 'Veri Analisti', radar: [{ label: 'SQL', score: 72, target: 80 }],
            }) };
        };
        window.location.reload = () => {
            window.location.href = '__reloaded__';
        };
        const state = profileCvUpload('tr', '/upload', '/status/__ANALYSIS_ID__', '', '/history/__DOCUMENT_ID__/analyze');

        await state.analyzeHistory('document-7');

        assert.equal(requests[0].url, '/history/document-7/analyze');
        assert.equal(requests[0].options.method, 'POST');
        assert.equal(requests[1].url, '/status/analysis-123');
        assert.equal(PanelCvStore.get().fileName, 'İlan CV.pdf');
        assert.equal(PanelCvStore.get().skillRadar.skills[0].label, 'SQL');
        assert.equal(state.historyAnalysisReady, true);
        assert.equal(state.historyLoadingId, null);
        assert.equal(window.location.href, '');
    });

    it('keeps the user on history and shows the API error when activation fails', async () => {
        globalThis.fetch = async () => ({ ok: false, json: async () => ({ message: 'CV okunamadı' }) });
        const state = profileCvUpload('tr', '/upload', '/status/__ANALYSIS_ID__', '/panel', '/history/__DOCUMENT_ID__/analyze');

        await state.analyzeHistory('document-8');

        assert.equal(state.error, 'CV okunamadı');
        assert.equal(state.historyLoadingId, null);
        assert.equal(state.historyAnalysisReady, false);
        assert.equal(window.location.href, '');
        assert.equal(PanelCvStore.get(), null);
    });

    it('reloads the current page after the SSE complete event', async () => {
        class FakeEventSource {
            constructor(url) {
                this.url = url;
                this.listeners = {};
                FakeEventSource.last = this;
            }

            addEventListener(type, handler) {
                this.listeners[type] = handler;
            }

            close() {
                this.closed = true;
            }

            emit(type, data) {
                this.listeners[type]?.({ data: JSON.stringify(data) });
            }
        }

        globalThis.EventSource = FakeEventSource;
        globalThis.fetch = async () => ({
            ok: true,
            json: async () => ({ analysis_id: 'analysis-sse-upload', status: 'queued' }),
        });
        let reloads = 0;
        window.location.reload = () => { reloads += 1; };
        const state = profileCvUpload(
            'tr',
            '/upload',
            '/status/__ANALYSIS_ID__',
            '',
            '',
            '/stream/__ANALYSIS_ID__',
        );

        try {
            const pending = state.handleCvFile(new File(['cv'], 'cv.pdf', { type: 'application/pdf' }));
            await new Promise((resolve) => setImmediate(resolve));
            FakeEventSource.last.emit('complete', {
                id: 'analysis-sse-upload',
                status: 'ready',
                file_name: 'cv.pdf',
                radar: [{ label: 'SQL', score: 90, target: 90 }],
            });
            await pending;

            assert.equal(FakeEventSource.last.url, '/stream/analysis-sse-upload');
            assert.equal(FakeEventSource.last.closed, true);
            assert.equal(reloads, 1);
        } finally {
            delete globalThis.EventSource;
        }
    });
});

describe('waitForCvAnalysis', () => {
    it('falls back to polling when SSE is unavailable', async () => {
        const originalSetTimeout = globalThis.setTimeout;
        globalThis.setTimeout = (callback) => {
            callback();
            return 0;
        };
        globalThis.fetch = async () => ({
            ok: true,
            json: async () => ({ status: 'ready', id: 'analysis-fallback' }),
        });

        const result = await waitForCvAnalysis('analysis-fallback', {
            statusUrl: '/status/__ANALYSIS_ID__',
            streamUrl: '/stream/__ANALYSIS_ID__',
            locale: 'tr',
        });

        globalThis.setTimeout = originalSetTimeout;
        assert.equal(result.id, 'analysis-fallback');
    });
});

describe('watchCvAnalysisViaSse', () => {
    it('resolves when the complete event arrives', async () => {
        class FakeEventSource {
            constructor(url) {
                this.url = url;
                this.listeners = {};
                FakeEventSource.last = this;
            }

            addEventListener(type, handler) {
                this.listeners[type] = handler;
            }

            close() {
                this.closed = true;
            }

            emit(type, data) {
                this.listeners[type]?.({ data: JSON.stringify(data) });
            }
        }

        globalThis.EventSource = FakeEventSource;

        const pending = watchCvAnalysisViaSse('analysis-sse', '/stream/__ANALYSIS_ID__', 'tr');
        FakeEventSource.last.emit('status', { id: 'analysis-sse', status: 'running' });
        FakeEventSource.last.emit('complete', { id: 'analysis-sse', status: 'ready', radar: [] });
        const result = await pending;

        assert.equal(result.status, 'ready');
        assert.equal(FakeEventSource.last.url, '/stream/analysis-sse');
        assert.equal(FakeEventSource.last.closed, true);
    });
});

describe('pollCvAnalysis', () => {
    it('keeps polling beyond the former one-minute cutoff when analysis remains queued', async () => {
        const originalSetTimeout = globalThis.setTimeout;
        let polls = 0;
        globalThis.setTimeout = (callback) => {
            callback();
            return 0;
        };
        globalThis.fetch = async () => {
            polls += 1;
            return {
                ok: true,
                json: async () => (polls <= 60 ? { status: 'running' } : { status: 'ready', id: 'analysis-after-retry' }),
            };
        };

        const result = await pollCvAnalysis('analysis-after-retry', '/status/__ANALYSIS_ID__', 'tr');

        globalThis.setTimeout = originalSetTimeout;
        assert.equal(polls, 61);
        assert.equal(result.status, 'ready');
    });
});
