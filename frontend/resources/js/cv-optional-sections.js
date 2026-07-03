export const CV_OPTIONAL_SECTION_KEYS = [
    'awards',
    'volunteer',
    'publications',
    'courses',
    'languages',
    'leadership',
    'affiliations',
    'references',
    'interests',
    'research',
    'additional',
];

/**
 * @param {string} key
 * @param {() => string} uid
 */
export function createOptionalEntry(key, uid) {
    const id = uid();

    switch (key) {
        case 'awards':
            return { id, title: '', issuer: '', date: '', details: '' };
        case 'volunteer':
        case 'leadership':
            return {
                id,
                organization: '',
                role: '',
                location: '',
                start: '',
                end: '',
                bullets: [''],
            };
        case 'publications':
            return { id, title: '', publisher: '', date: '', link: '', description: '' };
        case 'courses':
            return { id, name: '', institution: '', date: '', description: '' };
        case 'languages':
            return { id, language: '', level: '' };
        case 'affiliations':
            return { id, name: '', role: '', start: '', end: '' };
        case 'references':
            return { id, name: '', title: '', organization: '', contact: '' };
        case 'interests':
            return { id, items: '' };
        case 'research':
            return { id, title: '', institution: '', start: '', end: '', description: '' };
        case 'additional':
            return { id, body: '' };
        default:
            return { id, body: '' };
    }
}

/**
 * @param {Record<string, unknown>} locale
 * @param {string} key
 * @param {() => string} uid
 */
export function enableOptionalSectionForLocale(locale, key, uid) {
    if (!Array.isArray(locale.enabledOptional)) {
        locale.enabledOptional = [];
    }

    if (!locale.optional || typeof locale.optional !== 'object') {
        locale.optional = {};
    }

    if (!locale.enabledOptional.includes(key)) {
        locale.enabledOptional.push(key);
        locale.optional[key] = [createOptionalEntry(key, uid)];
    }
}

const BILINGUAL_LOCALES = ['tr', 'en'];

/**
 * @param {Record<string, Record<string, unknown>>} locales
 * @param {string} key
 * @param {() => string} uid
 */
export function enableOptionalSectionForBothLocales(locales, key, uid) {
    for (const lang of BILINGUAL_LOCALES) {
        if (locales[lang]) {
            enableOptionalSectionForLocale(locales[lang], key, uid);
        }
    }
}

/**
 * @param {Record<string, Record<string, unknown>>} locales
 * @param {string} key
 */
export function removeOptionalSectionFromBothLocales(locales, key) {
    for (const lang of BILINGUAL_LOCALES) {
        const locale = locales[lang];
        if (!locale) {
            continue;
        }

        if (Array.isArray(locale.enabledOptional)) {
            locale.enabledOptional = locale.enabledOptional.filter((item) => item !== key);
        }

        if (locale.optional && typeof locale.optional === 'object') {
            delete locale.optional[key];
        }
    }
}

/**
 * @param {Record<string, unknown>} locale
 */
export function normalizeLocaleOptional(locale, uid) {
    if (!Array.isArray(locale.enabledOptional)) {
        locale.enabledOptional = [];
    }

    if (!locale.optional || typeof locale.optional !== 'object') {
        locale.optional = {};
    }

    for (const key of locale.enabledOptional) {
        if (!Array.isArray(locale.optional[key]) || locale.optional[key].length === 0) {
            locale.optional[key] = [createOptionalEntry(key, uid)];
        }
    }
}

/**
 * @param {Record<string, unknown>} entry
 * @param {string} key
 */
export function optionalEntryHasContent(entry, key) {
    if (!entry || typeof entry !== 'object') {
        return false;
    }

    const text = (value) => typeof value === 'string' && value.trim().length > 0;

    switch (key) {
        case 'awards':
            return text(entry.title) || text(entry.issuer) || text(entry.date) || text(entry.details);
        case 'volunteer':
        case 'leadership':
            return (
                text(entry.organization)
                || text(entry.role)
                || text(entry.location)
                || text(entry.start)
                || text(entry.end)
                || (Array.isArray(entry.bullets) && entry.bullets.some((b) => text(b)))
            );
        case 'publications':
            return text(entry.title) || text(entry.publisher) || text(entry.date) || text(entry.link) || text(entry.description);
        case 'courses':
            return text(entry.name) || text(entry.institution) || text(entry.date) || text(entry.description);
        case 'languages':
            return text(entry.language) || text(entry.level);
        case 'affiliations':
            return text(entry.name) || text(entry.role) || text(entry.start) || text(entry.end);
        case 'references':
            return text(entry.name) || text(entry.title) || text(entry.organization) || text(entry.contact);
        case 'interests':
            return text(entry.items);
        case 'research':
            return text(entry.title) || text(entry.institution) || text(entry.start) || text(entry.end) || text(entry.description);
        case 'additional':
            return text(entry.body);
        default:
            return false;
    }
}
