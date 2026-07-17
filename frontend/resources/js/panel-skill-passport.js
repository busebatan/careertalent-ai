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

async function deleteJson(url, body) {
    const response = await fetch(url, {
        method: 'DELETE',
        headers: { ...csrfHeaders(), 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.message || payload.detail || 'Kanıt kaldırılamadı');
    }
    return payload;
}

export function skillPassport(initialItems, evidenceUrlTemplate, statusUrlTemplate, labels, config = {}) {
    return {
        items: JSON.parse(JSON.stringify(initialItems || [])),
        evidenceUrlTemplate,
        statusUrlTemplate,
        labels,
        config,
        selectedSkill: null,
        evidence: { kind: 'link', url: '', file: null },
        submitting: false,
        clearing: false,
        error: '',

        get hasTarget() {
            return Boolean(this.config.hasTarget && this.config.targetId);
        },

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
            if (status === 'waiting') {
                return 'bg-amber-500/15 text-amber-800 dark:text-amber-200';
            }
            if (status === 'revision' || status === 'missing') {
                return 'bg-red-500/15 text-red-700 dark:text-red-300';
            }
            return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
        },

        selectedStatusClass(status) {
            if (status === 'verified') {
                return 'border-emerald-500/60 ring-1 ring-emerald-500/30';
            }
            if (status === 'review') {
                return 'border-sky-500/60 ring-1 ring-sky-500/30';
            }
            if (status === 'waiting') {
                return 'border-amber-500/60 ring-1 ring-amber-500/30';
            }
            return 'border-red-500/60 ring-1 ring-red-500/30';
        },

        canUpload(item) {
            return this.hasTarget && item?.status !== 'verified';
        },

        canClear(item) {
            return this.hasTarget && item && ['review', 'revision'].includes(item.status);
        },

        passportScore() {
            if (!this.items.length) {
                return 0;
            }
            const total = this.items.reduce((sum, item) => sum + (Number(item.score) || 0), 0);
            return Math.round(total / this.items.length);
        },

        verifiedCount() {
            return this.items.filter((item) => item.status === 'verified').length;
        },

        applyTaskPayload(item, payload) {
            const task = payload?.task || payload;
            if (!task || typeof task !== 'object') {
                return;
            }
            if (task.id) {
                item.task_id = task.id;
            }
            if (task.title) {
                item.task_title = task.title;
            }
            if (task.feedback !== undefined) {
                item.feedback = task.feedback;
            }
            if (task.evidence_verified || (task.has_evidence && task.status === 'accepted')) {
                item.status = 'verified';
            } else if (task.status === 'revision_required') {
                item.status = 'revision';
            } else if (task.evidence_pending || task.has_evidence || ['reviewing', 'queued'].includes(task.status)) {
                item.status = 'review';
            } else if (task.status === 'completed') {
                item.status = 'waiting';
            } else if (task.status) {
                item.status = 'missing';
            }
        },

        async submitEvidence() {
            const item = this.selectedItem();
            if (!item || !this.canUpload(item)) {
                return;
            }

            const value = this.evidence.kind === 'link' ? this.evidence.url.trim() : this.evidence.file;
            if (!value) {
                return;
            }

            this.submitting = true;
            this.error = '';

            try {
                let payload;
                if (this.config.skillEvidenceUrl) {
                    if (this.evidence.kind === 'file') {
                        const body = new FormData();
                        body.append('skill', item.skill);
                        body.append('target_id', this.config.targetId);
                        body.append('kind', 'file');
                        body.append('evidence_file', value);
                        payload = await postForm(this.config.skillEvidenceUrl, body);
                    } else {
                        payload = await postJson(this.config.skillEvidenceUrl, {
                            skill: item.skill,
                            target_id: this.config.targetId,
                            kind: 'link',
                            url: value,
                        });
                    }
                } else if (item.task_id) {
                    const url = this.evidenceUrlTemplate.replace('__TASK_ID__', encodeURIComponent(item.task_id));
                    if (this.evidence.kind === 'file') {
                        const body = new FormData();
                        body.append('kind', 'file');
                        body.append('evidence_file', value);
                        payload = await postForm(url, body);
                    } else {
                        payload = await postJson(url, { kind: 'link', url: value });
                    }
                } else {
                    throw new Error(this.labels.target_required || 'Hedef seçilmedi');
                }

                const evidence = payload.evidence || payload;
                item.status = 'review';
                item.feedback = evidence.feedback || null;
                item.evidence_ref = this.evidence.kind === 'link' ? value : (value?.name || this.labels.evidence_file || 'Dosya');
                this.applyTaskPayload(item, payload);
                this.evidence.url = '';
                this.evidence.file = null;
                await this.pollTask(item);
            } catch (error) {
                this.error = error?.message || 'Kanıt gönderilemedi';
            } finally {
                this.submitting = false;
            }
        },

        async clearEvidence(item = null) {
            const targetItem = item || this.selectedItem();
            if (!targetItem || !this.canClear(targetItem)) {
                return;
            }

            this.clearing = true;
            this.error = '';

            try {
                if (this.config.skillEvidenceClearUrl) {
                    await deleteJson(this.config.skillEvidenceClearUrl, {
                        skill: targetItem.skill,
                        target_id: this.config.targetId,
                    });
                }
                targetItem.status = 'missing';
                targetItem.feedback = null;
                targetItem.evidence_ref = null;
                this.evidence = { kind: 'link', url: '', file: null };
            } catch (error) {
                this.error = error?.message || 'Kanıt kaldırılamadı';
            } finally {
                this.clearing = false;
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
                if (payload.evidence_verified || (payload.has_evidence && payload.status === 'accepted')) {
                    item.status = 'verified';
                    item.score = Math.max(item.score || 0, item.target || 0);
                    item.level = `%${item.score}`;
                    item.type = 'AI evidence';
                } else if (payload.status === 'revision_required') {
                    item.status = 'revision';
                } else if (payload.evidence_pending || payload.has_evidence || ['reviewing', 'queued'].includes(payload.status)) {
                    item.status = 'review';
                } else if (payload.status === 'completed') {
                    item.status = 'waiting';
                } else if (payload.status) {
                    item.status = 'missing';
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
