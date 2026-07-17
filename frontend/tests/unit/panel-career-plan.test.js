import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { careerAnalysisWatcher, careerPlanWatcher } from '../../resources/js/panel-career-plan.js';

describe('careerPlanWatcher', () => {
    it('polls queued AI plan and reloads when target-specific tasks become active', async () => {
        const states = ['queued', 'active'];
        let reloaded = 0;
        const watcher = careerPlanWatcher(
            { status: 'queued', statusUrl: '/plan/target-b', interval: 1, attempts: 3 },
            {
                sleep: async () => {},
                fetch: async (url) => ({ ok: true, json: async () => ({ target_id: 'target-b', status: states.shift(), task_count: 4, url }) }),
                reload: () => { reloaded += 1; },
            },
        );

        await watcher.start();
        assert.equal(watcher.status, 'active');
        assert.equal(reloaded, 1);
        assert.equal(watcher.error, '');
    });

    it('shows AI failure without reloading stale tasks', async () => {
        let reloaded = 0;
        const watcher = careerPlanWatcher(
            { status: 'queued', statusUrl: '/plan/target-c', failedMessage: 'Plan failed', attempts: 1 },
            {
                sleep: async () => {},
                fetch: async () => ({ ok: true, json: async () => ({ target_id: 'target-c', status: 'failed', message: 'AI output invalid' }) }),
                reload: () => { reloaded += 1; },
            },
        );

        await watcher.start();
        assert.equal(watcher.status, 'failed');
        assert.equal(watcher.error, 'AI output invalid');
        assert.equal(reloaded, 0);
    });
});

describe('careerAnalysisWatcher', () => {
    it('polls queued CV analysis and reloads when analysis becomes ready', async () => {
        const states = ['running', 'ready'];
        let reloaded = 0;
        const watcher = careerAnalysisWatcher(
            { status: 'running', statusUrl: '/analysis/current', interval: 1, attempts: 3 },
            {
                sleep: async () => {},
                fetch: async () => ({ ok: true, json: async () => ({ status: states.shift() }) }),
                reload: () => { reloaded += 1; },
            },
        );

        await watcher.start();
        assert.equal(watcher.status, 'ready');
        assert.equal(reloaded, 1);
        assert.equal(watcher.error, '');
    });
});
