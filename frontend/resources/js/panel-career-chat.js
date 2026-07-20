function csrfHeaders() {
    const token = globalThis.document?.querySelector('meta[name="csrf-token"]')?.content;
    return token ? { 'X-CSRF-TOKEN': token } : {};
}

function endpoint(template, id) {
    return template.replace('__JOB__', encodeURIComponent(id));
}

export function careerChat(initialMessages, sendUrl, newChatUrl, labels, actions = {}, runtime = {}) {
    const fetcher = runtime.fetch || globalThis.fetch;
    const sleep = runtime.sleep || ((ms) => new Promise((resolve) => setTimeout(resolve, ms)));
    const navigate = runtime.navigate || ((url) => globalThis.location?.assign(url));

    return {
        messages: Array.isArray(initialMessages) ? initialMessages : [],
        text: '',
        sending: false,
        error: '',
        sendUrl,
        newChatUrl,
        labels,
        actions,
        cvVersions: [],
        threads: Array.isArray(actions.initialThreads) ? [...actions.initialThreads] : [],
        historyHasMore: Boolean(actions.historyHasMore),
        historyOffset: Array.isArray(actions.initialThreads) ? actions.initialThreads.length : 0,
        historyLoading: false,
        newChatLoading: false,
        historyOpen: false,
        selectedThread: null,
        async init() {
            this.messages.forEach((message) => this.prepareAction(message));
            if (this.messages.some((message) => message.meta?.action?.type === 'job_cv_draft')) {
                await this.loadVersions();
            }
            this.messages.forEach((message) => {
                if (message.meta?.action?.type === 'job_cv_draft') {
                    this.pollAction(message.meta.action).catch((error) => {
                        message.meta.action.error = error.message;
                    });
                }
            });
            this.scrollToBottom();
        },
        prepareAction(message) {
            const action = message?.meta?.action;
            if (!action || action.type !== 'job_cv_draft') return null;
            action.status ||= 'queued';
            action.title ||= '';
            action.company ||= '';
            action.match_score = Number(action.match_score || 0);
            action.matched_skills = Array.isArray(action.matched_skills) ? action.matched_skills : [];
            action.missing_skills = Array.isArray(action.missing_skills) ? action.missing_skills : [];
            action.cv_suggestions = Array.isArray(action.cv_suggestions) ? action.cv_suggestions : [];
            action.selected = Array.isArray(action.selected) ? action.selected : [];
            action.sourceCvVersionId ||= '';
            action.creating = false;
            action.error ||= '';
            return action;
        },
        async loadVersions() {
            if (!this.actions.versionsUrl) return;
            try {
                const response = await fetcher(this.actions.versionsUrl, { headers: { Accept: 'application/json' } });
                if (response.ok) this.cvVersions = await response.json();
            } catch {
                this.cvVersions = [];
            }
        },
        async request(url, options = {}) {
            const response = await fetcher(url, {
                ...options,
                headers: {
                    ...csrfHeaders(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(options.headers || {}),
                },
            });
            const payload = response.status === 204 ? {} : await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || this.labels.failed);
            return payload;
        },
        hydrateAction(action, job) {
            action.status = job.status || 'queued';
            action.title = job.title || '';
            action.company = job.company || '';
            action.match_score = Number(job.match_score || 0);
            action.matched_skills = Array.isArray(job.matched_skills) ? job.matched_skills : [];
            action.missing_skills = Array.isArray(job.missing_skills) ? job.missing_skills : [];
            action.cv_suggestions = Array.isArray(job.cv_suggestions) ? job.cv_suggestions : [];
            action.error = job.error_message || '';
            if (!action.selected.length && action.status === 'ready') {
                action.selected = action.cv_suggestions.filter((item) => item.safe_to_apply).map((item) => item.id);
            }
        },
        async pollAction(action) {
            if (!this.actions.jobStatusUrl || !action?.job_id) return;
            for (let attempt = 0; attempt < 150; attempt += 1) {
                const job = await this.request(endpoint(this.actions.jobStatusUrl, action.job_id), { method: 'GET' });
                this.hydrateAction(action, job);
                this.scrollToBottom();
                if (action.status === 'ready') return;
                if (action.status === 'failed') throw new Error(action.error || this.labels.action_failed || this.labels.failed);
                await sleep(2000);
            }
            throw new Error(this.labels.action_timeout || this.labels.failed);
        },
        async createCvVersion(action) {
            if (action.creating || !action.selected.length || !this.actions.createCvVersionUrl) return;
            action.creating = true;
            action.error = '';
            try {
                const version = await this.request(endpoint(this.actions.createCvVersionUrl, action.job_id), {
                    method: 'POST',
                    body: JSON.stringify({
                        suggestion_ids: action.selected,
                        source_cv_version_id: action.sourceCvVersionId || null,
                    }),
                });
                navigate(this.actions.editorUrl.replace('__VERSION__', encodeURIComponent(version.id)));
            } catch (error) {
                action.error = error?.message || this.labels.failed;
                action.creating = false;
            }
        },
        scrollToBottom() {
            const scroll = () => {
                const container = this.$refs?.messages;
                if (container) container.scrollTop = container.scrollHeight;
            };
            if (typeof this.$nextTick === 'function') this.$nextTick(scroll);
            else scroll();
        },
        async send() {
            const content = this.text.trim();
            if (!content || this.sending) return;
            this.messages.push({ id: `pending-${Date.now()}`, role: 'user', content, meta: {} });
            this.text = '';
            this.sending = true;
            this.error = '';
            this.scrollToBottom();
            try {
                const payload = await this.request(this.sendUrl, { method: 'POST', body: JSON.stringify({ message: content }) });
                this.messages.push(payload);
                const action = this.prepareAction(payload);
                this.scrollToBottom();
                if (action) {
                    await this.loadVersions();
                    await this.pollAction(action);
                }
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.sending = false;
                this.scrollToBottom();
            }
        },
        async startNewChat() {
            if (this.newChatLoading || !this.messages.length) return;
            this.newChatLoading = true;
            this.error = '';
            try {
                const payload = await this.request(this.newChatUrl, { method: 'POST' });
                this.messages = [];
                this.text = '';
                if (payload.archived && !this.threads.some((thread) => thread.id === payload.archived.id)) {
                    this.threads.unshift(payload.archived);
                    this.historyOffset += 1;
                }
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.newChatLoading = false;
                this.scrollToBottom();
            }
        },
        async loadMoreHistory() {
            if (this.historyLoading || !this.historyHasMore || !this.actions.historyUrl) return;
            this.historyLoading = true;
            this.error = '';
            try {
                const separator = this.actions.historyUrl.includes('?') ? '&' : '?';
                const payload = await this.request(`${this.actions.historyUrl}${separator}offset=${this.historyOffset}&limit=20`, { method: 'GET' });
                const known = new Set(this.threads.map((thread) => thread.id));
                const items = Array.isArray(payload.items) ? payload.items : [];
                this.threads.push(...items.filter((thread) => !known.has(thread.id)));
                this.historyOffset += items.length;
                this.historyHasMore = Boolean(payload.has_more);
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.historyLoading = false;
            }
        },
        async openHistory(thread) {
            if (!thread?.id || !this.actions.historyDetailUrl) return;
            this.historyLoading = true;
            this.error = '';
            try {
                const url = this.actions.historyDetailUrl.replace('__THREAD__', encodeURIComponent(thread.id));
                this.selectedThread = await this.request(url, { method: 'GET' });
                this.historyOpen = true;
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.historyLoading = false;
            }
        },
        closeHistory() {
            this.historyOpen = false;
            this.selectedThread = null;
        },
        formatHistoryDate(value) {
            if (!value) return '';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return '';
            return new Intl.DateTimeFormat(this.actions.locale || 'tr-TR', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
        },
    };
}
