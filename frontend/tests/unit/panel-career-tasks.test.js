import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

globalThis.document = { querySelector: () => null };
const values = new Map();
globalThis.localStorage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    clear: () => values.clear(),
};

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
        const state = careerTasks([{ id: 'task-1', title: 'SQL case', status: 'pending' }], [], '/evidence/__TASK_ID__', '/tasks/__TASK_ID__', {}, {});
        state.init();
        state.form(state.tasks[0]).url = 'https://github.com/example/project';

        await state.submitEvidence(state.tasks[0]);

        assert.equal(calls.length, 2);
        assert.equal(state.tasks[0].status, 'accepted');
        assert.equal(state.error, '');
    });

    it('keeps task state unchanged when evidence request fails', async () => {
        globalThis.fetch = async () => ({ ok: false, json: async () => ({ message: 'AI unavailable' }) });
        const state = careerTasks([{ id: 'task-2', title: 'Portfolio', status: 'pending' }], [], '/evidence/__TASK_ID__', '', {}, {});
        state.init();
        state.form(state.tasks[0]).url = 'https://github.com/example/project';

        await state.submitEvidence(state.tasks[0]);

        assert.equal(state.tasks[0].status, 'pending');
        assert.equal(state.error, 'AI unavailable');
    });

    it('persists personal tasks and AI notes through account-backed endpoints', async () => {
        const calls = [];
        globalThis.fetch = async (url, options) => {
            calls.push([url, options]);
            if (url === '/personal') return { ok: true, json: async () => ({ id: 'personal-1', title: 'Mentor görüşmesi', completed: false, note: '' }) };
            return { ok: true, json: async () => ({ id: 'ai-1', note: 'GitHub bağlantısını ekle' }) };
        };
        const endpoints = { create: '/personal', update: '/personal/__TASK_ID__', delete: '/personal/__TASK_ID__', note: '/tasks/__TASK_ID__/note', targetId: 'target-a' };
        const first = careerTasks([{ id: 'ai-1', title: 'SQL', status: 'pending' }], [], '', '', {}, endpoints);
        first.init();
        first.tasks[0].note = 'GitHub bağlantısını ekle';
        await first.saveNote(first.tasks[0]);
        first.newTaskTitle = 'Mentor görüşmesi';
        await first.addTask();

        const restored = careerTasks([{ id: 'ai-1', title: 'SQL', status: 'pending', note: 'GitHub bağlantısını ekle' }], [{ id: 'personal-1', title: 'Mentor görüşmesi', completed: false, note: '' }], '', '', {}, endpoints);
        restored.init();
        assert.equal(restored.tasks.find((task) => task.id === 'ai-1').note, 'GitHub bağlantısını ekle');
        assert.equal(restored.tasks.find((task) => task.source === 'custom').title, 'Mentor görüşmesi');
        assert.equal(calls.length, 2);
        assert.match(calls[0][0], /tasks\/ai-1\/note/);
    });

    it('starts readiness at baseline and grows with completed tasks', () => {
        const tasks = Array.from({ length: 10 }, (_, index) => ({
            id: `task-${index}`,
            title: `Task ${index}`,
            status: index === 0 ? 'completed' : 'pending',
        }));
        const state = careerTasks(tasks, [], '', '', {}, {}, 60);
        state.init();

        assert.equal(state.readiness, 64);
        assert.equal(state.targetReady, false);
    });

    it('marks target ready when all tasks are completed', () => {
        const tasks = Array.from({ length: 3 }, (_, index) => ({
            id: `task-${index}`,
            title: `Task ${index}`,
            status: 'completed',
        }));
        const state = careerTasks(tasks, [], '', '', {}, {}, 60);
        state.init();

        assert.equal(state.readiness, 100);
        assert.equal(state.targetReady, true);
    });

    it('toggles ai tasks through status endpoint without evidence', async () => {
        const calls = [];
        globalThis.fetch = async (url, options) => {
            calls.push([url, options]);
            return { ok: true, json: async () => ({ id: 'ai-1', status: JSON.parse(options.body).status }) };
        };
        const endpoints = {
            statusUpdate: '/tasks/__TASK_ID__/status',
            update: '/personal/__TASK_ID__',
            create: '/personal',
            delete: '/personal/__TASK_ID__',
            note: '/tasks/__TASK_ID__/note',
        };
        const state = careerTasks([{ id: 'ai-1', title: 'CFA course', status: 'pending' }], [], '', '', {}, endpoints);
        state.init();
        await state.toggleTask(state.tasks[0]);

        assert.equal(state.tasks[0].done, true);
        assert.equal(state.tasks[0].status, 'completed');
        assert.match(calls[0][0], /\/tasks\/ai-1\/status/);
        assert.equal(JSON.parse(calls[0][1].body).status, 'completed');
    });
});
