import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

const {
    enableOptionalSectionForBothLocales,
    enableOptionalSectionForLocale,
    removeOptionalSectionFromBothLocales,
} = await import('../../resources/js/cv-optional-sections.js');

function emptyLocales() {
    return {
        tr: { enabledOptional: [], optional: {} },
        en: { enabledOptional: [], optional: {} },
    };
}

describe('cv-optional-sections bilingual helpers', () => {
    it('enableOptionalSectionForBothLocales opens section in TR and EN', () => {
        const locales = emptyLocales();
        let counter = 0;
        const uid = () => `id-${++counter}`;

        enableOptionalSectionForBothLocales(locales, 'languages', uid);

        assert.deepEqual(locales.tr.enabledOptional, ['languages']);
        assert.deepEqual(locales.en.enabledOptional, ['languages']);
        assert.equal(locales.tr.optional.languages[0].language, '');
        assert.equal(locales.en.optional.languages[0].language, '');
        assert.notEqual(locales.tr.optional.languages[0].id, locales.en.optional.languages[0].id);
    });

    it('enableOptionalSectionForLocale is idempotent for the same key', () => {
        const locale = { enabledOptional: [], optional: {} };
        const uid = () => 'same-id';

        enableOptionalSectionForLocale(locale, 'awards', uid);
        enableOptionalSectionForLocale(locale, 'awards', uid);

        assert.deepEqual(locale.enabledOptional, ['awards']);
        assert.equal(locale.optional.awards.length, 1);
    });

    it('removeOptionalSectionFromBothLocales clears section in TR and EN', () => {
        const locales = emptyLocales();
        const uid = () => 'id-1';

        enableOptionalSectionForBothLocales(locales, 'references', uid);
        locales.tr.optional.references[0].name = 'Ayşe Yılmaz';
        locales.en.optional.references[0].name = 'Ayse Yilmaz';

        removeOptionalSectionFromBothLocales(locales, 'references');

        assert.deepEqual(locales.tr.enabledOptional, []);
        assert.deepEqual(locales.en.enabledOptional, []);
        assert.equal(locales.tr.optional.references, undefined);
        assert.equal(locales.en.optional.references, undefined);
    });
});
