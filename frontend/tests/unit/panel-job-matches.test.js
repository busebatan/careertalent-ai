import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { panelJobMatches } from '../../resources/js/panel-job-matches.js';

describe('panelJobMatches', () => {
    it('resumes queued analysis and apply polling after a page reload', () => {
        const state = panelJobMatches([
            { id: 'analysis-job', status: 'queued' },
            { id: 'apply-job', status: 'ready', apply_status: 'running' },
            { id: 'ready-job', status: 'ready' },
        ], {});
        const calls = [];
        state.poll = async (job, applying) => { calls.push([job.id, applying]); };

        state.init();

        assert.deepEqual(calls, [
            ['analysis-job', false],
            ['apply-job', true],
        ]);
    });
});
