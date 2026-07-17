import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { profileSocialLinks } from '../../resources/js/panel-profile-links.js';

const values = new Map();
globalThis.localStorage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    clear: () => values.clear(),
};

describe('profileSocialLinks', () => {
    it('keeps LinkedIn outside and stores profession-specific links dynamically', () => {
        localStorage.clear();
        const state = profileSocialLinks([{ platform: 'GitHub', url: 'https://github.com/user' }], 'profile-a');
        state.init();
        state.addLink();
        state.links[1].platform = 'Behance';
        state.links[1].url = 'https://behance.net/designer';
        state.persist();

        const restored = profileSocialLinks([], 'profile-a');
        restored.init();
        assert.deepEqual(restored.links.map(({ platform, url }) => ({ platform, url })), [
            { platform: 'GitHub', url: 'https://github.com/user' },
            { platform: 'Behance', url: 'https://behance.net/designer' },
        ]);
    });

    it('always keeps one row and limits the list to eight links', () => {
        localStorage.clear();
        const state = profileSocialLinks([], 'profile-b');
        state.init();
        for (let index = 0; index < 12; index += 1) state.addLink();
        assert.equal(state.links.length, 8);
        for (const link of [...state.links]) state.removeLink(link);
        assert.equal(state.links.length, 1);
    });

    it('filters platform options and selects from the custom dropdown', () => {
        localStorage.clear();
        const state = profileSocialLinks([], 'profile-c');
        state.init();
        const link = state.links[0];
        link.platform = 'git';
        assert.deepEqual(state.filteredPlatforms(link), ['GitHub', 'GitLab']);
        state.selectPlatform(link, 'GitHub');
        assert.equal(link.platform, 'GitHub');
        assert.equal(state.openLinkId, null);
    });

    it('opens upward when there is less space below than above', () => {
        localStorage.clear();
        const state = profileSocialLinks([], 'profile-d');
        state.init();
        const link = state.links[0];
        state.$el = {
            querySelector: () => ({
                getBoundingClientRect: () => ({ top: 500, bottom: 820 }),
            }),
        };
        Object.defineProperty(globalThis, 'innerHeight', { configurable: true, value: 900 });
        state.syncDropdownPlacement(link.id);
        assert.equal(state.dropdownUp, true);
    });
});
