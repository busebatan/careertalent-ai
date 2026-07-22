function headers() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return { ...(token ? { 'X-CSRF-TOKEN': token } : {}), 'Content-Type': 'application/json', Accept: 'application/json' };
}

export function careerInterview(config, dependencies = {}) {
    const request = dependencies.fetch || globalThis.fetch;

    return {
        interview: config.initial || null,
        history: Array.isArray(config.history) ? config.history : [],
        idx: 0,
        answer: '',
        result: null,
        drafts: {},
        details: {},
        openHistoryId: null,
        historyLoadingId: null,
        busy: false,
        error: '',
        notice: '',
        selectedLanguage: null,
        showLangModal: false,
        startUrl: config.startUrl,
        scoreUrlTemplate: config.scoreUrlTemplate,
        historyUrl: config.historyUrl,
        detailUrlTemplate: config.detailUrlTemplate,
        retryUrlTemplate: config.retryUrlTemplate,
        labels: config.labels || {},

        init() {
            if (this.interview) this.hydrateInterview(this.interview);
        },

        get question() { return this.interview?.questions?.[this.idx] || null; },
        get questionCount() { return this.interview?.questions?.length || 0; },

        progressFor(index, total) {
            return (this.labels.progress || '__CURRENT__ / __TOTAL__')
                .replace('__CURRENT__', String(index + 1))
                .replace('__TOTAL__', String(total || 0));
        },

        progressLabel() { return this.progressFor(this.idx, this.questionCount); },

        historySummary(item) {
            return (this.labels.historySummary || '__ANSWERED__ / __TOTAL__')
                .replace('__ANSWERED__', String(item.answered_count || 0))
                .replace('__TOTAL__', String(item.question_count || 0));
        },

        statusLabel(status) {
            return status === 'completed'
                ? (this.labels.statusCompleted || status)
                : (this.labels.statusArchived || status);
        },

        formatDate(value) {
            if (!value) return '';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
        },

        isValidInterview(payload) {
            if (!payload || typeof payload.id !== 'string' || payload.id.trim() === '' || !Array.isArray(payload.questions) || payload.questions.length === 0) return false;
            const questionIds = payload.questions.map((item) => item?.id);
            return questionIds.every((id) => typeof id === 'string' && id.trim() !== '')
                && new Set(questionIds).size === questionIds.length;
        },

        hydrateInterview(payload) {
            if (!this.isValidInterview(payload)) throw new Error(this.labels.failed || 'Interview payload is invalid.');
            this.interview = payload;
            this.drafts = {};
            for (const saved of payload?.answers || []) {
                this.drafts[saved.question_id] = { answer: saved.answer || '', result: saved };
            }
            const questions = payload?.questions || [];
            const firstUnanswered = questions.findIndex((item) => !this.drafts[item.id]?.result);
            this.idx = firstUnanswered >= 0 ? firstUnanswered : 0;
            this.loadQuestionState();
        },

        persistQuestionState() {
            if (!this.question) return;
            this.drafts[this.question.id] = { answer: this.answer, result: this.result };
        },

        loadQuestionState() {
            const state = this.question ? this.drafts[this.question.id] : null;
            this.answer = state?.answer || '';
            this.result = state?.result || null;
        },

        goTo(index) {
            if (index < 0 || index >= this.questionCount || index === this.idx) return;
            this.persistQuestionState();
            this.idx = index;
            this.loadQuestionState();
        },

        previous() { this.goTo(this.idx - 1); },
        next() { this.goTo(this.idx + 1); },

        async json(url, options = {}) {
            const response = await request(url, { headers: headers(), ...options });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || this.labels.failed);
            return payload;
        },

        async start(language) {
            if (this.busy) return;
            const selectedPracticeLanguage = language ?? this.selectedLanguage ?? 'tr';
            this.selectedLanguage = selectedPracticeLanguage;
            this.showLangModal = false;
            this.busy = true;
            this.error = '';
            this.notice = '';
            try {
                const payload = await this.json(this.startUrl, {
                    method: 'POST',
                    body: JSON.stringify({ language: selectedPracticeLanguage }),
                });
                this.hydrateInterview(payload);
                await this.refreshHistorySafely();
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.busy = false;
            }
        },

        async score() {
            if (this.busy || !this.question || this.answer.trim().length < 20) return;
            this.busy = true;
            this.error = '';
            this.notice = '';
            const interviewId = this.interview.id;
            const questionId = this.question.id;
            try {
                const payload = await this.json(
                    this.scoreUrlTemplate.replace('__INTERVIEW_ID__', encodeURIComponent(interviewId)),
                    {
                        method: 'POST',
                        body: JSON.stringify({ question_id: questionId, answer: this.answer }),
                    },
                );
                this.result = payload;
                this.drafts[questionId] = { answer: this.answer, result: payload };

                if (payload.completed || payload.interview_status === 'completed') {
                    this.interview = null;
                    this.idx = 0;
                    this.answer = '';
                    this.result = null;
                    this.drafts = {};
                    this.notice = this.labels.completed || '';
                    if (await this.refreshHistorySafely()) {
                        const item = this.history.find((row) => row.id === interviewId);
                        if (item) await this.openHistory(item);
                    }
                }
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.busy = false;
            }
        },

        async refreshHistory() {
            const payload = await this.json(`${this.historyUrl}?limit=20&offset=0`);
            this.history = Array.isArray(payload) ? payload : (payload.items || []);
        },

        async refreshHistorySafely() {
            try {
                await this.refreshHistory();
                return true;
            } catch (error) {
                this.error = error?.message || this.labels.failed;
                return false;
            }
        },

        async toggleHistory(item) {
            if (this.openHistoryId === item.id) {
                this.openHistoryId = null;
                return;
            }
            await this.openHistory(item);
        },

        async openHistory(item) {
            this.openHistoryId = item.id;
            if (this.details[item.id]) return;
            this.historyLoadingId = item.id;
            this.error = '';
            try {
                this.details[item.id] = await this.json(
                    this.detailUrlTemplate.replace('__INTERVIEW_ID__', encodeURIComponent(item.id)),
                );
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.historyLoadingId = null;
            }
        },

        answerFor(detail, questionId) {
            return detail?.answers?.find((item) => item.question_id === questionId) || null;
        },

        async retry(item) {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            this.notice = '';
            try {
                const payload = await this.json(
                    this.retryUrlTemplate.replace('__INTERVIEW_ID__', encodeURIComponent(item.id)),
                    { method: 'POST', body: '{}' },
                );
                this.openHistoryId = null;
                this.hydrateInterview(payload);
                await this.refreshHistorySafely();
                globalThis.document?.querySelector?.('[data-interview-active]')?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
            } catch (error) {
                this.error = error?.message || this.labels.failed;
            } finally {
                this.busy = false;
            }
        },
    };
}
