const DEFAULT_DURATION_MS = 4000;
const FADE_MS = 500;

export function bootFlashToasts(root = document) {
    root.querySelectorAll('[data-flash-toast]').forEach((element) => {
        if (element.dataset.flashToastBound === '1') {
            return;
        }

        element.dataset.flashToastBound = '1';

        const parsedDuration = Number.parseInt(element.dataset.flashToastDuration || '', 10);
        const duration = Number.isFinite(parsedDuration) && parsedDuration > 0
            ? parsedDuration
            : DEFAULT_DURATION_MS;

        window.setTimeout(() => {
            element.classList.add('flash-toast-leaving');

            window.setTimeout(() => {
                element.remove();
            }, FADE_MS);
        }, duration);
    });
}
