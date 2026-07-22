import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { careerAnalysisWatcher, careerDataReset, careerPlanWatcher } from '../../resources/js/panel-career-plan.js';

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

describe('careerDataReset', () => {
    it('posts the selected scope, clears local analysis state and reloads', async () => {
        const requests = [];
        let cleared = 0;
        let reloaded = 0;
        const state = careerDataReset(
            { clearUrl: '/career/reset', errorMessage: 'Temizlenemedi' },
            {
                csrfToken: () => 'csrf-token',
                fetch: async (url, options) => {
                    requests.push({ url, options });
                    return { ok: true, json: async () => ({ status: 'cleared' }) };
                },
                clearLocalCv: () => { cleared += 1; },
                reload: () => { reloaded += 1; },
            },
        );
        state.resetScope = 'all';

        await state.clearCareerData();

        assert.equal(requests[0].url, '/career/reset');
        assert.equal(requests[0].options.method, 'POST');
        assert.deepEqual(JSON.parse(requests[0].options.body), { scope: 'all' });
        assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.equal(cleared, 1);
        assert.equal(reloaded, 1);
    });

    it('keeps the modal open and shows the API error when reset fails', async () => {
        const state = careerDataReset(
            { clearUrl: '/career/reset', errorMessage: 'Temizlenemedi' },
            { fetch: async () => ({ ok: false, json: async () => ({ message: 'Reset reddedildi' }) }) },
        );
        state.resetOpen = true;

        await state.clearCareerData();

        assert.equal(state.resetOpen, true);
        assert.equal(state.resetWorking, false);
        assert.equal(state.resetError, 'Reset reddedildi');
    });
});
