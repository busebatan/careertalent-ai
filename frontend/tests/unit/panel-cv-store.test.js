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
    };
}

installBrowserMocks();

const { PanelCvStore, PANEL_CV_STORAGE_KEY } = await import('../../resources/js/panel-cv-store.js');

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
