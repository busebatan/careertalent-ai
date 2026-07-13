import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

globalThis.document = { querySelector: () => null };

const { careerTasks } = await import('../../resources/js/panel-career-tasks.js');

describe('careerTasks evidence contract', () => {
    it('submits a link and refreshes backend status', async () => {
        const calls = [];
        globalThis.fetch = async (url, options) => {
            calls.push([url, options]);
            if (url.includes('/evidence')) {
                return { ok: true, json: async () => ({ id: 'e-1', status: 'reviewing' }) };
            }
            return { ok: true, json: async () => ({ id: 'task-1', status: 'accepted', feedback: null }) };
        };
        const state = careerTasks([{ id: 'task-1', title: 'SQL case', status: 'pending' }], '/evidence/__TASK_ID__', '/tasks/__TASK_ID__', {});
        state.form(state.tasks[0]).url = 'https://github.com/example/project';

        await state.submitEvidence(state.tasks[0]);

        assert.equal(calls.length, 2);
        assert.equal(state.tasks[0].status, 'accepted');
        assert.equal(state.error, '');
    });

    it('keeps task state unchanged when evidence request fails', async () => {
        globalThis.fetch = async () => ({ ok: false, json: async () => ({ message: 'AI unavailable' }) });
        const state = careerTasks([{ id: 'task-2', title: 'Portfolio', status: 'pending' }], '/evidence/__TASK_ID__', '', {});
        state.form(state.tasks[0]).url = 'https://github.com/example/project';

        await state.submitEvidence(state.tasks[0]);

        assert.equal(state.tasks[0].status, 'pending');
        assert.equal(state.error, 'AI unavailable');
    });
});
