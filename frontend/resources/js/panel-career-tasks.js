function csrfHeaders() { const token = document.querySelector('meta[name="csrf-token"]')?.content; return token ? { 'X-CSRF-TOKEN': token } : {}; }

async function responsePayload(response, fallback) { const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.message || payload.detail || fallback); return payload; }

export function careerTasks(initialTasks, personalTasks, evidenceUrlTemplate, statusUrlTemplate, labels, endpoints = {}, baselineReadiness = 0) {
    const baseline = Math.max(0, Math.min(100, Number(baselineReadiness) || 0));
    return {
        tasks: [], evidenceUrlTemplate, statusUrlTemplate, labels, endpoints, baselineReadiness: baseline, evidence: {}, submitting: {}, error: '', newTaskTitle: '',
        init() {
            this.tasks = (initialTasks || []).map((task) => ({ ...JSON.parse(JSON.stringify(task)), source: 'ai', done: ['completed', 'accepted'].includes(task.status), note: task.note || '', showNote: false }));
            for (const task of (personalTasks || [])) this.tasks.push({ ...task, source: 'custom', done: Boolean(task.completed), note: task.note || '', status: task.completed ? 'completed' : 'personal', hint: '', training_suggestions: [], showNote: false });
            this.reorderTasks();
        },
        reorderTasks() { this.tasks = [...this.tasks.filter((task) => !task.done), ...this.tasks.filter((task) => task.done)]; },
        async request(url, method, body = null) { const response = await fetch(url, { method, headers: { ...csrfHeaders(), 'Content-Type': 'application/json', Accept: 'application/json' }, ...(body ? { body: JSON.stringify(body) } : {}) }); return responsePayload(response, 'İşlem tamamlanamadı'); },
        async toggleTask(task) {
            const previousDone = task.done;
            const previousStatus = task.status;
            task.done = !task.done;
            task.status = task.done ? 'completed' : (task.source === 'custom' ? 'personal' : 'pending');
            this.reorderTasks();
            try {
                if (task.source === 'custom') {
                    const updated = await this.request(this.endpoints.update.replace('__TASK_ID__', encodeURIComponent(task.id)), 'PATCH', { completed: task.done });
                    Object.assign(task, updated);
                    task.status = updated.completed ? 'completed' : 'personal';
                    task.done = Boolean(updated.completed);
                } else {
                    const updated = await this.request(this.endpoints.statusUpdate.replace('__TASK_ID__', encodeURIComponent(task.id)), 'PATCH', { status: task.done ? 'completed' : 'pending' });
                    Object.assign(task, updated);
                    task.done = ['completed', 'accepted'].includes(updated.status);
                }
                this.reorderTasks();
            } catch (error) {
                task.done = previousDone;
                task.status = previousStatus;
                this.reorderTasks();
                this.error = error.message;
            }
        },
        get doneCount() { return this.tasks.filter((task) => task.done).length; },
        get totalCount() { return this.tasks.length; },
        get readiness() {
            const total = this.totalCount;
            if (!total) return this.baselineReadiness;
            const progress = this.doneCount / total;
            return Math.round(this.baselineReadiness + ((100 - this.baselineReadiness) * progress));
        },
        get targetReady() { return this.baselineReadiness > 0 && this.totalCount > 0 && this.doneCount === this.totalCount; },
        toggleNote(task) { task.showNote = !task.showNote; },
        async saveNote(task) { const url = task.source === 'custom' ? this.endpoints.update.replace('__TASK_ID__', encodeURIComponent(task.id)) : this.endpoints.note.replace('__TASK_ID__', encodeURIComponent(task.id)); await this.request(url, 'PATCH', { note: task.note }); },
        async addTask() { const title = this.newTaskTitle.trim(); if (!title) return; try { const task = await this.request(this.endpoints.create, 'POST', { title, target_id: this.endpoints.targetId || null }); this.tasks.push({ ...task, done: false, source: 'custom', status: 'personal', hint: '', training_suggestions: [], showNote: false }); this.newTaskTitle = ''; this.reorderTasks(); } catch (error) { this.error = error.message; } },
        async removeTask(task) { if (task.source !== 'custom') return; try { await this.request(this.endpoints.delete.replace('__TASK_ID__', encodeURIComponent(task.id)), 'DELETE'); this.tasks = this.tasks.filter((item) => item.id !== task.id); } catch (error) { this.error = error.message; } },
        form(task) { return this.evidence[task.id] ||= { kind: 'link', url: '', file: null }; },
        async submitEvidence(task) {
            const form = this.form(task); const value = form.kind === 'link' ? form.url.trim() : form.file; if (!value) return;
            this.submitting[task.id] = true; this.error = '';
            try {
                const url = this.evidenceUrlTemplate.replace('__TASK_ID__', encodeURIComponent(task.id)); let response;
                if (form.kind === 'file') { const body = new FormData(); body.append('kind', 'file'); body.append('evidence_file', value); response = await fetch(url, { method: 'POST', headers: { ...csrfHeaders(), Accept: 'application/json' }, body }); }
                else response = await fetch(url, { method: 'POST', headers: { ...csrfHeaders(), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ kind: 'link', url: value }) });
                const payload = await responsePayload(response, 'Kanıt gönderilemedi'); const evidence = payload.evidence || payload; task.status = evidence.status || 'pending'; task.feedback = evidence.feedback || null; form.url = ''; form.file = null;
                if (this.statusUrlTemplate) await this.pollTask(task); this.reorderTasks();
            } catch (error) { this.error = error?.message || 'Kanıt gönderilemedi'; } finally { this.submitting[task.id] = false; }
        },
        async pollTask(task) { const url = this.statusUrlTemplate.replace('__TASK_ID__', encodeURIComponent(task.id)); for (let attempt = 0; attempt < 15; attempt += 1) { await new Promise((resolve) => setTimeout(resolve, 1000)); const response = await fetch(url, { headers: { Accept: 'application/json' } }); if (!response.ok) return; const payload = await response.json().catch(() => ({})); if (payload.status) task.status = payload.status; if (payload.feedback !== undefined) task.feedback = payload.feedback; if (['completed', 'accepted'].includes(task.status)) task.done = true; if (['completed', 'accepted', 'revision_required'].includes(task.status)) return; } },
    };
}
