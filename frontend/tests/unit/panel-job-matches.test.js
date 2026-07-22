import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { panelJobMatches } from '../../resources/js/panel-job-matches.js';

describe('panelJobMatches', () => {
    it('keeps failed analysis details and the exact CV provenance after a reload', () => {
        const state = panelJobMatches([{
            id: 'failed-job',
            status: 'failed',
            error_message: 'İlan URL\'si okunamadı',
            source_analysis_id: 'analysis-1',
            source_cv_file_name: 'aday-cv.pdf',
        }], {}, { id: 'analysis-2', status: 'ready' });

        assert.equal(state.jobs[0].status, 'failed');
        assert.equal(state.jobs[0].error_message, 'İlan URL\'si okunamadı');
        assert.equal(state.jobs[0].source_analysis_id, 'analysis-1');
        assert.equal(state.jobs[0].source_cv_file_name, 'aday-cv.pdf');
    });

    it('queues pasted listing text and keeps the completed result in the same page state', async () => {
        const listing = 'SQL, Python ve Power BI bilen bir veri analisti arıyoruz. Raporlama deneyimi zorunludur.';
        const state = panelJobMatches([], {
            analyzeUrl: '/analyze',
            errors: { generic: 'error' },
        }, { id: 'cv-1', status: 'ready', skills: [], radar: [] });
        state.jobText = listing;

        const requests = [];
        state.request = async (url, options) => {
            requests.push({ url, body: JSON.parse(options.body) });
            return { id: 'job-1', status: 'queued' };
        };
        state.poll = async (job) => {
            Object.assign(job, {
                status: 'ready',
                title: 'Veri Analisti',
                match_score: 72,
                matched_skills: ['SQL'],
                missing_skills: ['Power BI'],
            });
        };

        await state.addJob();

        assert.deepEqual(requests, [{
            url: '/analyze',
            body: { source_url: null, job_text: listing },
        }]);
        assert.equal(state.jobs[0].status, 'ready');
        assert.equal(state.jobs[0].title, 'Veri Analisti');
        assert.equal(state.jobs[0].match_score, 72);
        assert.equal(state.jobText, '');
        assert.equal(state.loading, false);
    });

    it('writes a polled result into the live collection when the caller holds a detached job object', async () => {
        const state = panelJobMatches([{ id: 'job-1', status: 'queued' }], {
            statusUrl: '/jobs/__JOB__',
            errors: { generic: 'error', timeout: 'timeout' },
        });
        state.wait = async () => {};
        state.request = async () => ({
            id: 'job-1', status: 'ready', title: 'Klinik Laboratuvar Kalite Uzmanı', match_score: 60,
            matched_skills: ['HPLC'], missing_skills: ['ISO 15189'],
        });

        await state.poll({ ...state.jobs[0] }, false);

        assert.equal(state.jobs[0].status, 'ready');
        assert.equal(state.jobs[0].title, 'Klinik Laboratuvar Kalite Uzmanı');
        assert.equal(state.jobs[0].match_score, 60);
    });

    it('blocks job analysis until the latest CV analysis is ready', async () => {
        const listing = 'SQL, Python ve Power BI bilen bir veri analisti arıyoruz. Raporlama deneyimi zorunludur.';
        const state = panelJobMatches([], {
            analyzeUrl: '/analyze',
            errors: { generic: 'error' },
        }, { id: 'cv-1', status: 'running', skills: [], radar: [] });
        state.jobText = listing;
        let requested = false;
        state.request = async () => { requested = true; };

        await state.addJob();

        assert.equal(state.cvReady, false);
        assert.equal(requested, false);
    });

    it('polls a pending CV and enables job analysis when the CV becomes ready', async () => {
        const state = panelJobMatches([], {
            cvStatusUrl: '/cv/__ANALYSIS__',
            errors: { generic: 'error', timeout: 'timeout' },
        }, { id: 'cv-1', status: 'running', skills: [], radar: [] });
        state.wait = async () => {};
        state.request = async (url) => {
            assert.equal(url, '/cv/cv-1');
            return {
                id: 'cv-1',
                status: 'ready',
                skills: [{ name: 'SQL', score: 80 }],
                radar: [{ label: 'SQL', score: 80, target: 90 }],
            };
        };

        await state.pollCv();

        assert.equal(state.cvReady, true);
        assert.deepEqual(state.cv.skills, [{ name: 'SQL', score: 80 }]);
        assert.equal(state.cv.readiness, 80);
    });

    it('keeps a pending CV non-terminal when live status polling has a client error', async () => {
        const state = panelJobMatches([], {
            errors: { generic: 'error', timeout: 'timeout' },
        }, { id: 'cv-1', status: 'running', skills: [], radar: [] });
        state.pollCv = async () => { throw new Error('network unavailable'); };

        state.init();
        await Promise.resolve();
        await Promise.resolve();

        assert.equal(state.cv.status, 'running');
        assert.equal(state.cv.error_message, 'network unavailable');
        assert.equal(state.cvReady, false);
    });

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
