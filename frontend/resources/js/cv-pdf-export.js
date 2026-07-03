/** @type {Promise<typeof import('html2pdf.js').default> | null} */
let html2pdfLoader = null;

function loadHtml2Pdf() {
    if (!html2pdfLoader) {
        html2pdfLoader = import('html2pdf.js').then((mod) => mod.default);
    }

    return html2pdfLoader;
}

function cleanupHtml2pdfArtifacts() {
    document.querySelectorAll('.html2pdf__overlay, .html2pdf__container').forEach((el) => el.remove());
}

function mountClone(source) {
    const clone = source.cloneNode(true);
    clone.removeAttribute('id');
    clone.classList.remove('rounded-lg', 'border', 'border-slate-300', 'p-8', 'shadow-lg');
    clone.classList.add('harvard-cv', 'harvard-cv-pdf-export');
    clone.querySelectorAll('.cv-optional-preview-block').forEach((node) => {
        if (node.textContent?.trim()) {
            node.style.display = 'block';
        }
    });

    const wrapper = document.createElement('div');
    wrapper.setAttribute('aria-hidden', 'true');
    wrapper.style.cssText = 'position:fixed;left:-10000px;top:0;z-index:-1;background:#fff;';
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    return { clone, wrapper };
}

/**
 * @param {HTMLElement} sourceEl
 * @param {string} filename
 */
export async function exportHarvardCvPdf(sourceEl, filename) {
    if (!sourceEl) {
        throw new Error('CV preview element not found');
    }

    const html2pdf = await loadHtml2Pdf();
    const { clone, wrapper } = mountClone(sourceEl);

    try {
        await html2pdf()
            .set({
                margin: 0.5,
                filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false,
                },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
            })
            .from(clone)
            .save();
    } finally {
        wrapper.remove();
        cleanupHtml2pdfArtifacts();
    }
}
