export function bootCompanyPositionAnalysis() {
    document.querySelectorAll('[data-position-analysis]').forEach((element) => {
        if (element.dataset.analysisReady === 'true') return;
        if (!['queued', 'processing'].includes(element.dataset.status)) return;
        const url = element.dataset.statusUrl;
        if (!url) return;
        element.dataset.analysisReady = 'true';
        let attempts = 0;
        const poll = async () => {
            attempts += 1;
            let finished = false;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) return;
                const result = await response.json();
                element.textContent = `AI: ${result.status}`;
                if (['completed', 'failed'].includes(result.status)) {
                    finished = true;
                    window.location.reload();
                    return;
                }
            } catch (_) {
                // A transient network failure should not cancel the bounded poll.
            } finally {
                if (!finished && attempts < 90) window.setTimeout(poll, 2000);
            }
        };
        window.setTimeout(poll, 1200);
    });
}
