import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { careerChat } from '../../resources/js/panel-career-chat.js';
import { careerInterview } from '../../resources/js/panel-career-interview.js';
import { careerApplications } from '../../resources/js/panel-applications.js';

globalThis.document = { querySelector: () => null };

describe('account-backed engagement panels', () => {
    it('chat sends the user message and appends persisted AI reply', async () => {
        let sent;
        globalThis.fetch = async (_url, options) => { sent = JSON.parse(options.body); return { ok: true, json: async () => ({ id: 'a1', role: 'assistant', content: 'Hedef görevi tamamla', meta: {} }) }; };
        const state = careerChat([], '/chat', '/chat', { failed: 'failed' });
        state.selectMode('career'); state.text = 'Bugün ne yapmalıyım?'; await state.send();
        assert.deepEqual(sent, { message: 'Bugün ne yapmalıyım?', mode: 'career' }); assert.equal(state.messages[0].role, 'user'); assert.equal(state.messages[1].content, 'Hedef görevi tamamla');
    });

    it('requires a mode for an empty chat and restores persisted mode metadata after reload', async () => {
        let requests = 0;
        const empty = careerChat([], '/chat', '/chat/new', { failed: 'failed' }, {}, {
            fetch: async () => { requests += 1; return { ok: true, status: 201, json: async () => ({}) }; },
        });
        empty.text = 'Bu mesaj gönderilmemeli';
        await empty.send();
        assert.equal(requests, 0);
        assert.equal(empty.modeSelected, false);

        const restored = careerChat([
            { id: 'm1', role: 'user', content: 'CV analizi', meta: { mode: 'cv' } },
            { id: 'm2', role: 'assistant', content: 'Yanıt', meta: { mode: 'cv' } },
        ], '/chat', '/chat/new', { failed: 'failed' });
        assert.equal(restored.mode, 'cv');
        assert.equal(restored.modeSelected, true);
    });

    it('chat polls a job action, keeps approval explicit, and opens the new CV version', async () => {
        const requests = [];
        let navigated = '';
        const action = { type: 'job_cv_draft', job_id: 'job-1', status: 'queued' };
        const state = careerChat([], '/chat', '/chat/clear', { failed: 'failed' }, {
            jobStatusUrl: '/jobs/__JOB__', createCvVersionUrl: '/jobs/__JOB__/cv-version', editorUrl: '/cv?cvVersion=__VERSION__',
        }, {
            sleep: async () => {},
            navigate: (url) => { navigated = url; },
            fetch: async (url, options = {}) => {
                requests.push([url, options]);
                if (url === '/chat') return { ok: true, status: 201, json: async () => ({ id: 'a1', role: 'assistant', content: 'Önizleme hazır', meta: { action } }) };
                if (url === '/jobs/job-1') return { ok: true, status: 200, json: async () => ({ id: 'job-1', status: 'ready', title: 'Analyst', match_score: 72, matched_skills: ['SQL'], missing_skills: ['Power BI'], cv_suggestions: [{ id: 's1', safe_to_apply: true, title: 'SQL', reason: 'İlan', suggested_text: 'SQL raporu' }] }) };
                return { ok: true, status: 201, json: async () => ({ id: 'version-1' }) };
            },
        });
        state.selectMode('cv');
        state.text = 'CVmi bu uzun ilan metnine göre oluşturmak istiyorum. SQL ve raporlama deneyimi arıyoruz.';

        await state.send();
        const hydrated = state.messages[1].meta.action;
        assert.equal(hydrated.status, 'ready');
        assert.deepEqual(hydrated.selected, ['s1']);
        assert.equal(navigated, '');

        await state.createCvVersion(hydrated);
        assert.deepEqual(JSON.parse(requests.at(-1)[1].body), { suggestion_ids: ['s1'], source_cv_version_id: null });
        assert.equal(navigated, '/cv?cvVersion=version-1');
    });

    it('chat message viewport scrolls instead of growing the panel page', () => {
        const state = careerChat([], '/chat', '/clear', { failed: 'failed' });
        state.$refs = { messages: { scrollTop: 0, scrollHeight: 640 } };
        state.$nextTick = (callback) => callback();
        state.scrollToBottom();
        assert.equal(state.$refs.messages.scrollTop, 640);
    });

    it('starts a new chat, keeps the archived thread in history, and opens it read-only', async () => {
        const archived = { id: 'thread-old', title: 'Eski kariyer konuşması', message_count: 2, updated_at: '2026-07-20T20:00:00Z' };
        const state = careerChat(
            [{ id: 'm1', role: 'user', content: 'Eski kariyer konuşması', meta: {} }],
            '/chat',
            '/chat/new',
            { failed: 'failed' },
            { initialThreads: [], historyHasMore: false, historyUrl: '/chat/history', historyDetailUrl: '/chat/history/__THREAD__' },
            {
                fetch: async (url) => {
                    if (url === '/chat/new') return { ok: true, status: 201, json: async () => ({ archived, thread: { id: 'thread-new' } }) };
                    return { ok: true, status: 200, json: async () => ({ thread: archived, messages: [{ id: 'm1', role: 'user', content: 'Eski kariyer konuşması', meta: {} }] }) };
                },
            },
        );

        await state.startNewChat();
        assert.deepEqual(state.messages, []);
        assert.equal(state.mode, null);
        assert.equal(state.modeSelected, false);
        assert.equal(state.threads[0].title, 'Eski kariyer konuşması');
        await state.openHistory(archived);
        assert.equal(state.historyOpen, true);
        assert.equal(state.selectedThread.messages[0].content, 'Eski kariyer konuşması');
    });

    it('loads chat history in 20-record pages without duplicating entries', async () => {
        const initial = Array.from({ length: 20 }, (_, index) => ({ id: `thread-${index}`, title: `Sohbet ${index}`, message_count: 2 }));
        let requestedUrl = '';
        const state = careerChat([], '/chat', '/chat/new', { failed: 'failed' }, {
            initialThreads: initial,
            historyHasMore: true,
            historyUrl: '/chat/history',
        }, {
            fetch: async (url) => {
                requestedUrl = url;
                return { ok: true, status: 200, json: async () => ({ items: [{ id: 'thread-20', title: 'Sohbet 20', message_count: 2 }], has_more: false }) };
            },
        });

        await state.loadMoreHistory();
        assert.equal(requestedUrl, '/chat/history?offset=20&limit=20');
        assert.equal(state.threads.length, 21);
        assert.equal(state.historyHasMore, false);
    });

    it('interview starts from AI questions and submits the exact answer', async () => {
        const requests = [];
        globalThis.fetch = async (url, options) => {
            requests.push([url, options]);
            return { ok: true, json: async () => url === '/start'
                ? ({ id: 'i1', questions: [{ id: 'q1', question: 'Örnek?', competency: 'SQL' }] })
                : (url.startsWith('/history') ? ({ items: [] }) : ({ score: 88, feedback: 'Güçlü', improvements: [] })) };
        };
        const state = careerInterview({ initial: null, history: [], startUrl: '/start', scoreUrlTemplate: '/interviews/__INTERVIEW_ID__/answer', historyUrl: '/history', detailUrlTemplate: '/history/__INTERVIEW_ID__', retryUrlTemplate: '/interviews/__INTERVIEW_ID__/retry', labels: { failed: 'failed' } });
        await state.start(); state.answer = 'Execution plan ile sorguyu ölçüp indeks ekledim.'; await state.score();
        assert.deepEqual(JSON.parse(requests[0][1].body), { language: 'tr' }); assert.equal(state.selectedLanguage, 'tr');
        assert.equal(state.result.score, 88); assert.equal(requests[2][0], '/interviews/i1/answer'); assert.equal(JSON.parse(requests[2][1].body).question_id, 'q1');
    });

    it('interview sends the language selected in modal state', async () => {
        let sent;
        globalThis.fetch = async (url, options) => {
            if (url === '/start') sent = JSON.parse(options.body);
            return { ok: true, json: async () => url === '/start' ? ({ id: 'i-en', questions: [{ id: 'q1' }] }) : ({ items: [] }) };
        };
        const state = careerInterview({ initial: null, history: [], startUrl: '/start', scoreUrlTemplate: '/interviews/__INTERVIEW_ID__/answer', historyUrl: '/history', detailUrlTemplate: '/history/__INTERVIEW_ID__', retryUrlTemplate: '/interviews/__INTERVIEW_ID__/retry', labels: { failed: 'failed' } });
        state.selectedLanguage = 'en'; state.showLangModal = true; await state.start();
        assert.deepEqual(sent, { language: 'en' }); assert.equal(state.selectedLanguage, 'en'); assert.equal(state.showLangModal, false);
    });

    it('rejects malformed or duplicate interview questions instead of opening a blank session', async () => {
        globalThis.fetch = async () => ({ ok: true, json: async () => ({ id: 'i-bad', questions: [{ id: 'q1' }, { id: 'q1' }] }) });
        const state = careerInterview({ initial: null, history: [], startUrl: '/start', labels: { failed: 'failed' } });
        await state.start('tr');
        assert.equal(state.interview, null); assert.equal(state.error, 'failed');
    });

    it('keeps a newly started interview usable when archived-history refresh fails', async () => {
        globalThis.fetch = async (url) => url === '/start'
            ? { ok: true, json: async () => ({ id: 'i2', questions: [{ id: 'q2' }] }) }
            : { ok: false, json: async () => ({ message: 'History unavailable' }) };
        const state = careerInterview({
            initial: { id: 'i1', questions: [{ id: 'q1' }] }, history: [], startUrl: '/start', historyUrl: '/history', labels: { failed: 'failed' },
        });
        state.init(); await state.start('tr');
        assert.equal(state.interview.id, 'i2'); assert.equal(state.error, 'History unavailable');
    });

    it('refreshes history after a first visible start so a hidden previous session appears', async () => {
        globalThis.fetch = async (url) => ({ ok: true, json: async () => url === '/start'
            ? ({ id: 'i2', questions: [{ id: 'q2' }] })
            : ({ items: [{ id: 'i1', status: 'archived' }] }) });
        const state = careerInterview({ initial: null, history: [], startUrl: '/start', historyUrl: '/history', labels: { failed: 'failed' } });
        await state.start('tr');
        assert.equal(state.interview.id, 'i2'); assert.equal(state.history[0].id, 'i1');
    });

    it('blocks a duplicate start while the first AI request is in progress', async () => {
        let releaseStart;
        let startRequests = 0;
        const startGate = new Promise((resolve) => { releaseStart = resolve; });
        globalThis.fetch = async (url) => {
            if (url === '/start') {
                startRequests += 1;
                await startGate;
                return { ok: true, json: async () => ({ id: 'i1', questions: [{ id: 'q1' }] }) };
            }
            return { ok: true, json: async () => ({ items: [] }) };
        };
        const state = careerInterview({ initial: null, history: [], startUrl: '/start', historyUrl: '/history', labels: { failed: 'failed' } });
        const first = state.start('tr'); const duplicate = state.start('tr');
        releaseStart(); await Promise.all([first, duplicate]);
        assert.equal(startRequests, 1); assert.equal(state.interview.id, 'i1');
    });

    it('interview navigation has boundaries and restores per-question drafts and scores', () => {
        const state = careerInterview({
            initial: { id: 'i1', questions: [{ id: 'q1' }, { id: 'q2' }, { id: 'q3' }], answers: [] },
            history: [], labels: { progress: 'Question __CURRENT__ / __TOTAL__' },
        });
        state.init();
        state.answer = 'First answer draft'; state.result = { score: 70 }; state.next();
        assert.equal(state.idx, 1); assert.equal(state.progressLabel(), 'Question 2 / 3');
        state.answer = 'Second answer draft'; state.next(); state.next();
        assert.equal(state.idx, 2);
        state.previous(); state.previous();
        assert.equal(state.idx, 0); assert.equal(state.answer, 'First answer draft'); assert.equal(state.result.score, 70);
    });

    it('moves a completed interview to history and loads answer details', async () => {
        const requests = [];
        const summary = { id: 'i1', target_role: 'Analyst', status: 'completed', question_count: 1, answered_count: 1, average_score: 88 };
        const detail = { ...summary, questions: [{ id: 'q1', question: 'Örnek?' }], answers: [{ question_id: 'q1', answer: 'Somut cevap', score: 88, feedback: 'Güçlü' }] };
        globalThis.fetch = async (url, options = {}) => {
            requests.push([url, options]);
            if (url === '/interviews/i1/answer') return { ok: true, json: async () => ({ score: 88, feedback: 'Güçlü', completed: true, interview_status: 'completed' }) };
            if (url === '/history?limit=20&offset=0') return { ok: true, json: async () => ({ items: [summary] }) };
            return { ok: true, json: async () => detail };
        };
        const state = careerInterview({
            initial: { id: 'i1', questions: [{ id: 'q1', question: 'Örnek?' }], answers: [] },
            history: [], startUrl: '/start', scoreUrlTemplate: '/interviews/__INTERVIEW_ID__/answer', historyUrl: '/history', detailUrlTemplate: '/history/__INTERVIEW_ID__', retryUrlTemplate: '/interviews/__INTERVIEW_ID__/retry', labels: { failed: 'failed', completed: 'done' },
        });
        state.init(); state.answer = 'Execution plan ile sorguyu ölçüp indeks ekledim.'; await state.score();
        assert.equal(state.interview, null); assert.equal(state.notice, 'done'); assert.equal(state.openHistoryId, 'i1');
        assert.equal(state.answerFor(state.details.i1, 'q1').score, 88);
        assert.deepEqual(requests.map(([url]) => url), ['/interviews/i1/answer', '/history?limit=20&offset=0', '/history/i1']);
    });

    it('retries the same historical interview as a clean active session', async () => {
        globalThis.fetch = async (url) => ({ ok: true, json: async () => url.includes('/retry')
            ? { id: 'i2', retry_of_id: 'i1', questions: [{ id: 'q1', question: 'Aynı soru' }], answers: [] }
            : { items: [{ id: 'i1', status: 'completed' }] } });
        const state = careerInterview({ initial: null, history: [{ id: 'i1' }], historyUrl: '/history', retryUrlTemplate: '/interviews/__INTERVIEW_ID__/retry', labels: { failed: 'failed' } });
        await state.retry({ id: 'i1' });
        assert.equal(state.interview.id, 'i2'); assert.equal(state.question.question, 'Aynı soru'); assert.equal(state.answer, '');
    });

    it('application is server-created and moves between persisted stages', async () => {
        globalThis.fetch = async (_url, options) => { const body = JSON.parse(options.body); return { ok: true, json: async () => ({ id: 'app1', company: body.company || 'Acme', role: body.role || 'Analyst', stage: body.stage || 'applied', next_action: '' }) }; };
        const state = careerApplications([], '/applications', '/applications/__APPLICATION_ID__', { applied: 'Applied', interview: 'Interview', offer: 'Offer', rejected: 'Rejected', defaultNext: 'Follow', failed: 'failed' }); state.company = 'Acme'; state.role = 'Analyst'; await state.add(); await state.move(state.items[0], 'interview');
        assert.equal(state.items.length, 1); assert.equal(state.items[0].stage, 'interview'); assert.equal(state.columns().find((column) => column.id === 'interview').items.length, 1);
        assert.deepEqual(state.columns().map((column) => column.id), ['applied', 'interview', 'offer', 'rejected']);
    });
});
