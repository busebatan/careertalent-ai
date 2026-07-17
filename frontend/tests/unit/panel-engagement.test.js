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
        const state = careerChat([], '/chat', '/chat', { failed: 'failed' }); state.text = 'Bugün ne yapmalıyım?'; await state.send();
        assert.deepEqual(sent, { message: 'Bugün ne yapmalıyım?' }); assert.equal(state.messages[0].role, 'user'); assert.equal(state.messages[1].content, 'Hedef görevi tamamla');
    });

    it('interview starts from AI questions and submits the exact answer', async () => {
        const requests = [];
        globalThis.fetch = async (url, options) => { requests.push([url, options]); return { ok: true, json: async () => url === '/start' ? ({ id: 'i1', questions: [{ id: 'q1', question: 'Örnek?', competency: 'SQL' }] }) : ({ score: 88, feedback: 'Güçlü', improvements: [] }) }; };
        const state = careerInterview(null, '/start', '/interviews/__INTERVIEW_ID__/answer', { failed: 'failed' }); await state.start(); state.answer = 'Execution plan ile sorguyu ölçüp indeks ekledim.'; await state.score();
        assert.equal(state.result.score, 88); assert.equal(requests[1][0], '/interviews/i1/answer'); assert.equal(JSON.parse(requests[1][1].body).question_id, 'q1');
    });

    it('application is server-created and moves between persisted stages', async () => {
        globalThis.fetch = async (_url, options) => { const body = JSON.parse(options.body); return { ok: true, json: async () => ({ id: 'app1', company: body.company || 'Acme', role: body.role || 'Analyst', stage: body.stage || 'applied', next_action: '' }) }; };
        const state = careerApplications([], '/applications', '/applications/__APPLICATION_ID__', { applied: 'Applied', interview: 'Interview', offer: 'Offer', rejected: 'Rejected', defaultNext: 'Follow', failed: 'failed' }); state.company = 'Acme'; state.role = 'Analyst'; await state.add(); await state.move(state.items[0], 'interview');
        assert.equal(state.items.length, 1); assert.equal(state.items[0].stage, 'interview'); assert.equal(state.columns().find((column) => column.id === 'interview').items.length, 1);
        assert.deepEqual(state.columns().map((column) => column.id), ['applied', 'interview', 'offer', 'rejected']);
    });
});
