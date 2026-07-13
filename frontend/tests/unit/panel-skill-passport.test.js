import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

globalThis.document = {
    querySelector: () => ({ getAttribute: () => 'test-token' }),
};

const { skillPassport } = await import('../../resources/js/panel-skill-passport.js');

describe('skillPassport evidence contract', () => {
    it('selects and toggles a skill', () => {
        const state = skillPassport([{ skill: 'SQL', status: 'missing', task_id: 'task-1' }], '/evidence/__TASK_ID__', '/status/__TASK_ID__', { status: {} });

        state.selectSkill('SQL');
        assert.equal(state.selectedSkill, 'SQL');
        assert.equal(state.selectedItem()?.skill, 'SQL');

        state.selectSkill('SQL');
        assert.equal(state.selectedSkill, null);
    });

    it('submits link evidence for selected skill task', async () => {
        const calls = [];
        globalThis.fetch = async (url) => {
            calls.push(url);
            if (url.includes('/evidence')) {
                return { ok: true, json: async () => ({ evidence: { status: 'pending', feedback: null } }) };
            }
            return { ok: true, json: async () => ({ status: 'accepted', feedback: 'Looks good' }) };
        };

        const state = skillPassport([{ skill: 'SQL', status: 'missing', task_id: 'task-1' }], '/evidence/__TASK_ID__', '/status/__TASK_ID__', { status: {} });
        state.selectSkill('SQL');
        state.evidence.kind = 'link';
        state.evidence.url = 'https://github.com/example/sql';

        await state.submitEvidence();

        assert.ok(calls.length >= 1);
        assert.equal(state.items[0].status, 'verified');
        assert.equal(state.items[0].feedback, 'Looks good');
    });
});
