function responseError(response, payload) {
    if (payload && typeof payload === 'object') {
        return payload.message || payload.error || null;
    }

    return response.statusText || null;
}

/**
 * Renders the current builder data through the server's A4 PDF renderer.
 * The returned blob is the exact document that is archived and downloaded.
 *
 * @param {string} url
 * @param {{language: string, locales: Record<string, unknown>, csrfToken?: string}} payload
 * @returns {Promise<Blob>}
 */
export async function requestServerCvPdf(url, { language, locales, csrfToken = '' }) {
    if (!url) {
        throw new Error('PDF render endpoint missing');
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/pdf',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        body: JSON.stringify({ language, locales }),
    });

    if (!response.ok) {
        const contentType = response.headers.get('content-type') || '';
        const payload = contentType.includes('application/json')
            ? await response.json().catch(() => null)
            : null;
        throw new Error(responseError(response, payload) || 'PDF could not be rendered');
    }

    const blob = await response.blob();
    const contentType = blob.type || response.headers.get('content-type') || '';
    const signature = blob.size >= 5 ? await blob.slice(0, 5).text() : '';
    if (!blob.size || !contentType.includes('application/pdf') || signature !== '%PDF-') {
        throw new Error('PDF render returned an invalid document');
    }

    return blob;
}

export function downloadPdfBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
