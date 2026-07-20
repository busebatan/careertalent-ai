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

    it('opens apply modal, fetches CV versions, and auto-selects the main version', async () => {
        const state = panelJobMatches([
            { id: 'job-1', status: 'ready', apply_status: null }
        ], {});
        state.jobs[0].selected = ['sug-1'];

        const requestedUrls = [];
        state.request = async (url) => {
            requestedUrls.push(url);
            return [
                { id: 'cv-1', version_name: 'Main CV', is_main: true, language: 'tr' },
                { id: 'cv-2', version_name: 'English CV', is_main: false, language: 'en' }
            ];
        };

        await state.applyJob(state.jobs[0]);

        assert.equal(state.showApplyModal, true);
        assert.equal(state.loadingVersions, false);
        assert.equal(state.selectedCvVersionId, 'cv-1');
        assert.deepEqual(requestedUrls, ['/panel/cv-merkezi/surumler']);
    });

    it('submits selected cv_version_id when application is confirmed', async () => {
        const config = {
            applyUrl: '/apply/__JOB__',
            errors: { generic: 'error' }
        };
        const state = panelJobMatches([
            { id: 'job-1', status: 'ready', apply_status: null }
        ], config);
        state.jobs[0].selected = ['sug-1'];

        state.activeJobForApply = state.jobs[0];
        state.selectedCvVersionId = 'cv-selected';
        state.showApplyModal = true;

        const requestCalls = [];
        state.request = async (url, options) => {
            requestCalls.push({ url, body: JSON.parse(options.body) });
            return { id: 'job-1', apply_status: 'queued' };
        };
        state.poll = async () => { };

        await state.confirmApply();

        assert.equal(state.showApplyModal, false);
        assert.equal(requestCalls.length, 1);
        assert.equal(requestCalls[0].url, '/apply/job-1');
        assert.deepEqual(requestCalls[0].body, {
            suggestion_ids: ['sug-1'],
            cv_version_id: 'cv-selected'
        });
    });
});

