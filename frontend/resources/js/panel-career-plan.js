export function careerPlanWatcher(config, runtime = {}) {
    return createCareerStatusWatcher(config, runtime, ['active', 'ready']);
}

export function careerAnalysisWatcher(config, runtime = {}) {
    return createCareerStatusWatcher(config, runtime, ['ready']);
}

function createCareerStatusWatcher(config, runtime = {}, readyStatuses = ['active', 'ready']) {
    const fetcher = runtime.fetch || globalThis.fetch;
    const sleep = runtime.sleep || ((ms) => new Promise((resolve) => setTimeout(resolve, ms)));
    const reload = runtime.reload || (() => globalThis.location?.reload());

    return {
        status: config.status || 'queued',
        error: '',
        async start() {
            if (!['queued', 'running'].includes(this.status) || !config.statusUrl) return;
            for (let attempt = 0; attempt < (config.attempts || 90); attempt += 1) {
                await sleep(config.interval || 2000);
                try {
                    const response = await fetcher(config.statusUrl, { headers: { Accept: 'application/json' } });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || config.errorMessage);
                    this.status = payload.status || this.status;
                    if (readyStatuses.includes(this.status)) {
                        reload();
                        return;
                    }
                    if (this.status === 'failed') {
                        this.error = payload.message || payload.error_message || config.failedMessage;
                        return;
                    }
                } catch (error) {
                    this.error = error?.message || config.errorMessage;
                    return;
                }
            }
            this.error = config.timeoutMessage;
        },
    };
}
