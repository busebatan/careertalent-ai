function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token ? { 'X-CSRF-TOKEN': token } : {};
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { ...csrfHeaders(), 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || payload.detail || 'Kanıt gönderilemedi');
    return payload;
}

async function postForm(url, body) {
    const response = await fetch(url, { method: 'POST', headers: { ...csrfHeaders(), Accept: 'application/json' }, body });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || payload.detail || 'Kanıt gönderilemedi');
    return payload;
}

export function careerTasks(initialTasks, evidenceUrlTemplate, statusUrlTemplate, labels) {
    return {
        tasks: JSON.parse(JSON.stringify(initialTasks || [])),
        evidenceUrlTemplate,
        statusUrlTemplate,
        labels,
        evidence: {},
        submitting: {},
        error: '',

        form(task) {
            return this.evidence[task.id] ||= { kind: 'link', url: '', file: null };
        },

        async submitEvidence(task) {
            const form = this.form(task);
            const value = form.kind === 'link' ? form.url.trim() : form.file;
            if (!value) return;
            this.submitting[task.id] = true;
            this.error = '';
            try {
                let payload;
                const url = this.evidenceUrlTemplate.replace('__TASK_ID__', encodeURIComponent(task.id));
                if (form.kind === 'file') {
                    const body = new FormData();
                    body.append('kind', 'file');
                    body.append('evidence_file', value);
                    payload = await postForm(url, body);
                } else {
                    payload = await postJson(url, { kind: 'link', url: value });
                }
                const evidence = payload.evidence || payload;
                task.status = evidence.status || 'pending';
                task.feedback = evidence.feedback || null;
                form.url = '';
                form.file = null;
                if (this.statusUrlTemplate) await this.pollTask(task);
            } catch (error) {
                this.error = error?.message || 'Kanıt gönderilemedi';
            } finally {
                this.submitting[task.id] = false;
            }
        },

        async pollTask(task) {
            const url = this.statusUrlTemplate.replace('__TASK_ID__', encodeURIComponent(task.id));
            for (let attempt = 0; attempt < 15; attempt += 1) {
                await new Promise((resolve) => setTimeout(resolve, 1000));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const payload = await response.json().catch(() => ({}));
                if (payload.status) task.status = payload.status;
                if (payload.feedback !== undefined) task.feedback = payload.feedback;
                if (['completed', 'accepted', 'revision_required'].includes(task.status)) return;
            }
        },
    };
}
