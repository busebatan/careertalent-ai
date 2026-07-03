export const PANEL_JOB_MATCHES_KEY = 'panel-job-matches';

function readRaw() {
    try {
        const raw = localStorage.getItem(PANEL_JOB_MATCHES_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function normalizeJob(job) {
    return {
        id: job.id,
        url: job.url,
        title: job.title ?? '',
        company: job.company ?? '',
        source: job.source ?? '',
        match_score: Number(job.match_score ?? 0),
        matched_skills: Array.isArray(job.matched_skills) ? job.matched_skills : [],
        missing_skills: Array.isArray(job.missing_skills) ? job.missing_skills : [],
        recommendation: job.recommendation ?? 'wait',
        analyzed_at: job.analyzed_at ?? new Date().toISOString(),
    };
}

export const JobMatchesStore = {
    load(seedJobs) {
        const stored = readRaw();
        if (Array.isArray(stored) && stored.length) {
            return stored.map((job) => normalizeJob(job));
        }

        return seedJobs.map((job) => normalizeJob(job));
    },

    save(jobs) {
        localStorage.setItem(PANEL_JOB_MATCHES_KEY, JSON.stringify(jobs));
    },
};

export function panelJobMatches(seedJobs, config) {
    return {
        jobs: [],
        jobUrl: '',
        loading: false,
        error: '',
        config,

        init() {
            this.jobs = JobMatchesStore.load(seedJobs);
        },

        get sortedJobs() {
            return [...this.jobs].sort((a, b) => b.match_score - a.match_score);
        },

        scoreClass(score) {
            if (score >= 70) {
                return 'text-emerald-600 dark:text-emerald-400';
            }
            if (score >= 50) {
                return 'text-amber-600 dark:text-amber-400';
            }

            return 'text-red-600 dark:text-red-400';
        },

        recommendationLabel(job) {
            const key = job.recommendation || 'wait';
            return this.config.recommendations[key] || key;
        },

        recommendationBadgeClass(job) {
            const key = job.recommendation || 'wait';
            if (key === 'apply') {
                return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300';
            }
            if (key === 'prepare') {
                return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300';
            }

            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
        },

        formatDate(iso) {
            try {
                return new Intl.DateTimeFormat(this.config.locale || 'tr-TR', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(new Date(iso));
            } catch {
                return iso;
            }
        },

        persist() {
            JobMatchesStore.save(this.jobs);
        },

        async addJob() {
            const url = this.jobUrl.trim();
            if (!url || this.loading) {
                return;
            }

            this.error = '';
            this.loading = true;

            try {
                const response = await fetch(this.config.analyzeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ url }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    this.error = payload.message || this.config.errors.generic;
                    return;
                }

                const job = normalizeJob(payload.job);
                const duplicate = this.jobs.some((item) => item.url === job.url);
                if (duplicate) {
                    this.error = this.config.errors.duplicate;
                    return;
                }

                this.jobs.unshift(job);
                this.jobUrl = '';
                this.persist();
            } catch {
                this.error = this.config.errors.generic;
            } finally {
                this.loading = false;
            }
        },

        removeJob(job) {
            this.jobs = this.jobs.filter((item) => item.id !== job.id);
            this.persist();
        },
    };
}
