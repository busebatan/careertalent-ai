export function bootJobShareQr() {
    document.querySelectorAll('[data-job-qr-download]').forEach((button) => {
        if (button.dataset.qrReady === 'true') return;
        button.dataset.qrReady = 'true';
        button.addEventListener('click', async () => {
            const url = button.dataset.jobUrl;
            if (!url) return;
            button.disabled = true;
            try {
                const { default: QRCode } = await import('qrcode');
                const dataUrl = await QRCode.toDataURL(url, {
                    width: 1024,
                    margin: 3,
                    errorCorrectionLevel: 'M',
                    color: { dark: '#07111f', light: '#ffffff' },
                });
                const anchor = document.createElement('a');
                anchor.href = dataUrl;
                anchor.download = 'careertalent-basvuru-qr.png';
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
            } finally {
                button.disabled = false;
            }
        });
    });
}
