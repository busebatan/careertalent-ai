function normalizeJob(job) {
    return {
        id: job.id, source_url: job.source_url || '', title: job.title || '', company: job.company || '', source: job.source || '',
        status: job.status || 'queued', match_score: Number(job.match_score || 0), saved: Boolean(job.saved),
        matched_skills: Array.isArray(job.matched_skills) ? job.matched_skills : [],
        missing_skills: Array.isArray(job.missing_skills) ? job.missing_skills : [],
        cv_suggestions: Array.isArray(job.cv_suggestions) ? job.cv_suggestions : [],
        apply_status: job.apply_status || null, result_analysis_id: job.result_analysis_id || null,
        error_message: job.error_message || '', created_at: job.created_at || new Date().toISOString(), selected: [], application_created: Boolean(job.application_created),
    };
}

export function panelJobMatches(seedJobs, config) {
    return {
        jobs: seedJobs.map(normalizeJob), jobUrl: '', jobText: '', loading: false, error: '', config,
        init() {
            this.jobs.forEach(job => {
                if (job.status === 'queued' || job.status === 'running') {
                    this.poll(job, false).catch(err => { this.error = err.message; });
                }
                if (job.apply_status === 'queued' || job.apply_status === 'running') {
                    this.poll(job, true).catch(err => { this.error = err.message; });
                }
            });
        },
        get sortedJobs() { return [...this.jobs].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)); },
        scoreClass(score) { return score >= 70 ? 'text-emerald-600 dark:text-emerald-400' : score >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'; },
        formatDate(iso) { try { return new Intl.DateTimeFormat(this.config.locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)); } catch { return iso; } },
        endpoint(template, job) { return template.replace('__JOB__', encodeURIComponent(job.id)); },
        headers() { return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.config.csrfToken, 'X-Requested-With': 'XMLHttpRequest' }; },
        async request(url, options = {}) {
            const response = await fetch(url, { ...options, headers: this.headers() });
            const payload = response.status === 204 ? {} : await response.json();
            if (!response.ok) throw new Error(payload.message || this.config.errors.generic);
            return payload;
        },
        async addJob() {
            if (this.loading || (!this.jobUrl.trim() && this.jobText.trim().length < 40)) return;
            this.loading = true; this.error = '';
            try {
                let job = normalizeJob(await this.request(this.config.analyzeUrl, { method: 'POST', body: JSON.stringify({ source_url: this.jobUrl.trim() || null, job_text: this.jobText.trim() || null }) }));
                this.jobs.unshift(job); this.jobUrl = ''; this.jobText = '';
                await this.poll(job, false);
            } catch (error) { this.error = error.message; } finally { this.loading = false; }
        },
        async poll(job, applying) {
            for (let attempt = 0; attempt < 150; attempt += 1) {
                await new Promise((resolve) => setTimeout(resolve, 2000));
                const fresh = normalizeJob(await this.request(this.endpoint(this.config.statusUrl, job)));
                fresh.selected = job.selected || [];
                Object.assign(job, fresh);
                const state = applying ? job.apply_status : job.status;
                if (state === 'ready') return;
                if (state === 'failed') throw new Error(job.error_message || this.config.errors.generic);
            }
            throw new Error(this.config.errors.timeout);
        },
        async saveJob(job) { try { Object.assign(job, normalizeJob(await this.request(this.endpoint(this.config.saveUrl, job), { method: 'POST', body: '{}' }))); } catch (error) { this.error = error.message; } },
        async markApplied(job) { try { await this.request(this.endpoint(this.config.appliedUrl, job), { method: 'POST', body: '{}' }); job.application_created = true; } catch (error) { this.error = error.message; } },
        async applyJob(job) {
            if (!job.selected.length || job.apply_status === 'queued' || job.apply_status === 'running') return;
            this.error = '';
            try {
                Object.assign(job, normalizeJob(await this.request(this.endpoint(this.config.applyUrl, job), { method: 'POST', body: JSON.stringify({ suggestion_ids: job.selected }) })));
                await this.poll(job, true);
            } catch (error) { this.error = error.message; }
        },
        async removeJob(job) { try { await this.request(this.endpoint(this.config.deleteUrl, job), { method: 'DELETE' }); this.jobs = this.jobs.filter((item) => item.id !== job.id); } catch (error) { this.error = error.message; } },
    };
}
