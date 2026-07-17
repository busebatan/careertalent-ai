function requestHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return { ...(token ? { 'X-CSRF-TOKEN': token } : {}), 'Content-Type': 'application/json', Accept: 'application/json' };
}

export function careerApplications(initial, createUrl, updateUrlTemplate, labels) {
    return {
        items: Array.isArray(initial) ? initial : [], company: '', role: '', busy: false, error: '', stages: ['applied', 'interview', 'offer', 'rejected'],
        columns() { return this.stages.map((id) => ({ id, label: labels[id], items: this.items.filter((item) => item.stage === id) })); },
        async add() {
            if (!this.company.trim() || !this.role.trim()) return; this.busy = true;
            try { const r = await fetch(createUrl, { method: 'POST', headers: requestHeaders(), body: JSON.stringify({ company: this.company, role: this.role, next_action: labels.defaultNext }) }); const p = await r.json().catch(() => ({})); if (!r.ok) throw new Error(p.message || labels.failed); this.items.unshift(p); this.company = ''; this.role = ''; }
            catch (e) { this.error = e?.message || labels.failed; } finally { this.busy = false; }
        },
        async move(item, stage) {
            const url = updateUrlTemplate.replace('__APPLICATION_ID__', encodeURIComponent(item.id));
            const r = await fetch(url, { method: 'PATCH', headers: requestHeaders(), body: JSON.stringify({ stage }) }); const p = await r.json().catch(() => ({}));
            if (r.ok) Object.assign(item, p); else this.error = p.message || labels.failed;
        },
    };
}
