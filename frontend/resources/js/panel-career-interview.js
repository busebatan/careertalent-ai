function headers() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return { ...(token ? { 'X-CSRF-TOKEN': token } : {}), 'Content-Type': 'application/json', Accept: 'application/json' };
}

export function careerInterview(initial, startUrl, scoreUrlTemplate, labels) {
    return {
        interview: initial, idx: 0, answer: '', result: null, busy: false, error: '', startUrl, scoreUrlTemplate, labels,
        get question() { return this.interview?.questions?.[this.idx] || null; },
        async start() {
            this.busy = true; this.error = '';
            try { const r = await fetch(this.startUrl, { method: 'POST', headers: headers(), body: '{}' }); const p = await r.json().catch(() => ({})); if (!r.ok) throw new Error(p.message || labels.failed); this.interview = p; this.idx = 0; this.answer = ''; this.result = null; }
            catch (e) { this.error = e?.message || labels.failed; } finally { this.busy = false; }
        },
        async score() {
            if (!this.question || this.answer.trim().length < 20) return;
            this.busy = true; this.error = '';
            try { const url = this.scoreUrlTemplate.replace('__INTERVIEW_ID__', encodeURIComponent(this.interview.id)); const r = await fetch(url, { method: 'POST', headers: headers(), body: JSON.stringify({ question_id: this.question.id, answer: this.answer }) }); const p = await r.json().catch(() => ({})); if (!r.ok) throw new Error(p.message || labels.failed); this.result = p; }
            catch (e) { this.error = e?.message || labels.failed; } finally { this.busy = false; }
        },
        next() { if (!this.interview?.questions?.length) return; this.idx = (this.idx + 1) % this.interview.questions.length; this.answer = ''; this.result = null; },
    };
}
