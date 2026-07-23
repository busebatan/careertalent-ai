import assert from 'node:assert/strict';
import test from 'node:test';
import { requestServerCvPdf } from '../../resources/js/cv-pdf-export.js';

test('posts the requested CV locale snapshot and returns the server PDF blob', async () => {
    const originalFetch = global.fetch;
    let request;
    global.fetch = async (url, options) => {
        request = { url, options };
        return new Response(new Blob(['%PDF-1.4'], { type: 'application/pdf' }), {
            status: 200,
            headers: { 'content-type': 'application/pdf' },
        });
    };

    try {
        const locales = { tr: { personal: { full_name: 'Buse' } }, en: {} };
        const pdf = await requestServerCvPdf('/panel/cv-merkezi/pdf', {
            language: 'tr',
            locales,
            csrfToken: 'csrf-token',
        });

        assert.equal(request.url, '/panel/cv-merkezi/pdf');
        assert.equal(request.options.method, 'POST');
        assert.equal(request.options.headers.Accept, 'application/pdf');
        assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.deepEqual(JSON.parse(request.options.body), { language: 'tr', locales });
        assert.equal(pdf.type, 'application/pdf');
    } finally {
        global.fetch = originalFetch;
    }
});

test('rejects non-PDF server responses', async () => {
    const originalFetch = global.fetch;
    global.fetch = async () => new Response(new Blob(['not a PDF'], { type: 'text/html' }), {
        status: 200,
        headers: { 'content-type': 'text/html' },
    });

    try {
        await assert.rejects(
            () => requestServerCvPdf('/panel/cv-merkezi/pdf', { language: 'tr', locales: { tr: {} } }),
            /invalid document/,
        );
    } finally {
        global.fetch = originalFetch;
    }
});

test('rejects an invalid PDF signature even when the server labels it as PDF', async () => {
    const originalFetch = global.fetch;
    global.fetch = async () => new Response(new Blob(['not-a-pdf'], { type: 'application/pdf' }), {
        status: 200,
        headers: { 'content-type': 'application/pdf' },
    });

    try {
        await assert.rejects(
            () => requestServerCvPdf('/panel/cv-merkezi/pdf', { language: 'tr', locales: { tr: {} } }),
            /invalid document/,
        );
    } finally {
        global.fetch = originalFetch;
    }
});
