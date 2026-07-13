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
    if (!response.ok) {
        throw new Error(payload.message || payload.detail || 'Kanıt gönderilemedi');
    }
    return payload;
}

async function postForm(url, body) {
    const response = await fetch(url, { method: 'POST', headers: { ...csrfHeaders(), Accept: 'application/json' }, body });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.message || payload.detail || 'Kanıt gönderilemedi');
    }
    return payload;
}

export function skillPassport(initialItems, evidenceUrlTemplate, statusUrlTemplate, labels) {
    return {
        items: JSON.parse(JSON.stringify(initialItems || [])),
        evidenceUrlTemplate,
        statusUrlTemplate,
        labels,
        selectedSkill: null,
        evidence: { kind: 'link', url: '', file: null },
        submitting: false,
        error: '',

        selectedItem() {
            if (!this.selectedSkill) {
                return null;
            }
            return this.items.find((item) => item.skill === this.selectedSkill) || null;
        },

        selectSkill(skill) {
            this.selectedSkill = this.selectedSkill === skill ? null : skill;
            this.error = '';
            this.evidence = { kind: 'link', url: '', file: null };
        },

        statusLabel(status) {
            return this.labels.status?.[status] || status;
        },

        statusClass(status) {
            if (status === 'verified') {
                return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
            }
            if (status === 'review') {
                return 'bg-sky-500/15 text-sky-700 dark:text-sky-300';
            }
            if (status === 'revision') {
                return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
            }
            return 'bg-red-500/15 text-red-700 dark:text-red-300';
        },

        canUpload(item) {
            return Boolean(item?.task_id) && !['verified'].includes(item?.status);
        },

        async submitEvidence() {
            const item = this.selectedItem();
            if (!item?.task_id) {
                return;
            }

            const value = this.evidence.kind === 'link' ? this.evidence.url.trim() : this.evidence.file;
            if (!value) {
                return;
            }

            this.submitting = true;
            this.error = '';

            try {
                const url = this.evidenceUrlTemplate.replace('__TASK_ID__', encodeURIComponent(item.task_id));
                let payload;
                if (this.evidence.kind === 'file') {
                    const body = new FormData();
                    body.append('kind', 'file');
                    body.append('evidence_file', value);
                    payload = await postForm(url, body);
                } else {
                    payload = await postJson(url, { kind: 'link', url: value });
                }

                const evidence = payload.evidence || payload;
                item.status = 'review';
                item.feedback = evidence.feedback || null;
                this.evidence.url = '';
                this.evidence.file = null;
                await this.pollTask(item);
            } catch (error) {
                this.error = error?.message || 'Kanıt gönderilemedi';
            } finally {
                this.submitting = false;
            }
        },

        async pollTask(item) {
            if (!item?.task_id || !this.statusUrlTemplate) {
                return;
            }

            const url = this.statusUrlTemplate.replace('__TASK_ID__', encodeURIComponent(item.task_id));
            for (let attempt = 0; attempt < 15; attempt += 1) {
                await new Promise((resolve) => setTimeout(resolve, 1000));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json().catch(() => ({}));
                if (payload.status === 'completed' || payload.status === 'accepted') {
                    item.status = 'verified';
                } else if (payload.status === 'revision_required') {
                    item.status = 'revision';
                } else if (payload.status) {
                    item.status = 'review';
                }
                if (payload.feedback !== undefined) {
                    item.feedback = payload.feedback;
                }
                if (['verified', 'revision'].includes(item.status)) {
                    return;
                }
            }
        },
    };
}
