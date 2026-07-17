import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

globalThis.document = { querySelector: () => ({ getAttribute: () => 'token' }) };

const { skillPassport } = await import('../../resources/js/panel-skill-passport.js');

describe('skillPassport evidence contract', () => {
    it('maps every passport status to its locked label and selected-card color', () => {
        const labels = { status: { verified: 'Onaylandı', review: 'İnceleniyor', waiting: 'Bekleniyor', missing: 'Kanıt eksik', revision: 'Kanıt eksik' } };
        const state = skillPassport([], '', '', labels, {});
        const cases = [
            ['verified', 'Onaylandı', 'emerald'],
            ['review', 'İnceleniyor', 'sky'],
            ['waiting', 'Bekleniyor', 'amber'],
            ['missing', 'Kanıt eksik', 'red'],
            ['revision', 'Kanıt eksik', 'red'],
        ];

        for (const [status, label, color] of cases) {
            assert.equal(state.statusLabel(status), label);
            assert.match(state.statusClass(status), new RegExp(color));
            assert.match(state.selectedStatusClass(status), new RegExp(color));
        }
    });

    it('selects a skill and opens upload for radar items without task_id', () => {
        const state = skillPassport(
            [{ skill: 'ERP Systems', status: 'missing', score: 50, target: 75 }],
            '/evidence/__TASK_ID__',
            '/status/__TASK_ID__',
            { status: {} },
            { hasTarget: true, targetId: 'target-1', skillEvidenceUrl: '/skill-evidence', skillEvidenceClearUrl: '/skill-evidence' },
        );
        state.selectSkill('ERP Systems');
        assert.equal(state.selectedSkill, 'ERP Systems');
        assert.equal(state.canUpload(state.selectedItem()), true);
    });

    it('submits link evidence through skill endpoint and polls task status', async () => {
        const calls = [];
        globalThis.fetch = async (url, options) => {
            calls.push([url, options]);
            if (url === '/skill-evidence') {
                return { ok: true, json: async () => ({ task: { id: 'task-erp', status: 'reviewing' }, evidence: { feedback: null } }) };
            }
            return { ok: true, json: async () => ({ status: 'revision_required', feedback: 'İsim uyuşmuyor' }) };
        };
        const state = skillPassport(
            [{ skill: 'SQL', status: 'missing', task_id: null, score: 40, target: 80 }],
            '/evidence/__TASK_ID__',
            '/status/__TASK_ID__',
            { status: {} },
            { hasTarget: true, targetId: 'target-1', skillEvidenceUrl: '/skill-evidence', skillEvidenceClearUrl: '/skill-evidence' },
        );
        state.init?.();
        state.selectSkill('SQL');
        state.evidence.kind = 'link';
        state.evidence.url = 'https://github.com/example/sql';
        await state.submitEvidence();
        assert.equal(state.items[0].status, 'revision');
        assert.equal(state.items[0].feedback, 'İsim uyuşmuyor');
        assert.equal(JSON.parse(calls[0][1].body).skill, 'SQL');
    });

    it('clears rejected evidence so user can re-upload', async () => {
        globalThis.fetch = async () => ({ ok: true, json: async () => ({ skill: 'SQL', task: { status: 'pending' } }) });
        const state = skillPassport(
            [{ skill: 'SQL', status: 'revision', evidence_ref: 'bad.pdf', feedback: 'Doğrulanamadı' }],
            '',
            '',
            { status: {} },
            { hasTarget: true, targetId: 'target-1', skillEvidenceUrl: '/skill-evidence', skillEvidenceClearUrl: '/skill-evidence' },
        );
        state.selectSkill('SQL');
        await state.clearEvidence();
        assert.equal(state.items[0].status, 'missing');
        assert.equal(state.items[0].evidence_ref, null);
    });

    it('keeps completed tasks awaiting evidence and pending tasks missing until proof exists', () => {
        const labels = { status: { verified: 'Onaylandı', review: 'İnceleniyor', waiting: 'Bekleniyor', missing: 'Kanıt eksik', revision: 'Kanıt eksik' } };
        const state = skillPassport(
            [{ skill: 'SAP ERP', status: 'missing', task_id: 'task-sap', score: 0, target: 100 }],
            '/evidence/__TASK_ID__',
            '/status/__TASK_ID__',
            labels,
            { hasTarget: true, targetId: 'target-1' },
        );

        state.applyTaskPayload(state.items[0], { id: 'task-sap', status: 'completed', has_evidence: false, evidence_verified: false });
        assert.equal(state.items[0].status, 'waiting');
        assert.match(state.statusClass('waiting'), /amber/);
        assert.match(state.selectedStatusClass('waiting'), /amber/);

        state.applyTaskPayload(state.items[0], { id: 'task-sap', status: 'pending', has_evidence: false, evidence_verified: false });
        assert.equal(state.items[0].status, 'missing');
        assert.match(state.statusClass('missing'), /red/);
        assert.match(state.selectedStatusClass('missing'), /red/);

        state.applyTaskPayload(state.items[0], { id: 'task-sap', status: 'pending', has_evidence: true, evidence_pending: true, evidence_verified: false });
        assert.equal(state.items[0].status, 'review');
        assert.equal(state.statusLabel('review'), 'İnceleniyor');
        assert.match(state.statusClass('review'), /sky/);
        assert.match(state.selectedStatusClass('review'), /sky/);

        state.applyTaskPayload(state.items[0], { id: 'task-sap', status: 'revision_required', has_evidence: true, evidence_verified: false });
        assert.equal(state.items[0].status, 'revision');
        assert.equal(state.statusLabel('revision'), 'Kanıt eksik');
        assert.match(state.statusClass('revision'), /red/);
        assert.match(state.selectedStatusClass('revision'), /red/);

        state.applyTaskPayload(state.items[0], { id: 'task-sap', status: 'completed', has_evidence: true, evidence_verified: true });
        assert.equal(state.items[0].status, 'verified');
        assert.equal(state.statusLabel('verified'), 'Onaylandı');
        assert.match(state.statusClass('verified'), /emerald/);
        assert.match(state.selectedStatusClass('verified'), /emerald/);
    });
});
