export function careerPlanWatcher(config, runtime = {}) {
    return createCareerStatusWatcher(config, runtime, ['active', 'ready']);
}

export function careerAnalysisWatcher(config, runtime = {}) {
    return createCareerStatusWatcher(config, runtime, ['ready']);
}

export function careerDataReset(config, runtime = {}) {
    const fetcher = runtime.fetch || globalThis.fetch;
    const reload = runtime.reload || (() => globalThis.location?.reload());
    const clearLocalCv = runtime.clearLocalCv || (() => globalThis.PanelCvStore?.clear());
    const csrfToken = runtime.csrfToken || (() => globalThis.document?.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));

    return {
        resetOpen: false,
        resetScope: 'all',
        resetWorking: false,
        resetError: '',
        async clearCareerData() {
            if (!config.clearUrl || this.resetWorking) return;
            this.resetWorking = true;
            this.resetError = '';
            const token = csrfToken();
            try {
                const response = await fetcher(config.clearUrl, {
                    method: 'POST',
                    headers: {
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ scope: this.resetScope }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || config.errorMessage);
                if (['analysis', 'all'].includes(this.resetScope)) clearLocalCv();
                reload();
            } catch (error) {
                this.resetError = error?.message || config.errorMessage;
                this.resetWorking = false;
            }
        },
    };
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
