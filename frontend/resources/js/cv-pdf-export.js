/** @type {Promise<typeof import('html2pdf.js').default> | null} */
let html2pdfLoader = null;

const PDF_SAFE_STYLES = `
html, body { margin: 0; background: #fff; color: #000; }
*, *::before, *::after { box-sizing: border-box; border-color: #000; color: #000; }
.harvard-cv-pdf-export { width: 6.5in; max-width: 6.5in; margin: 0; padding: 0; border: 0; background: #fff; color: #000; box-shadow: none; font: 11pt/1.35 Georgia, "Times New Roman", serif; }
.harvard-cv-pdf-export h1 { margin: 0 0 4px; font-size: 18pt; font-weight: 700; letter-spacing: .02em; text-align: center; }
.harvard-cv-pdf-export .contact { margin: 0 0 12px; font-size: 10pt; text-align: center; }
.harvard-cv-pdf-export h2 { margin: 14px 0 6px; padding-bottom: 2px; border-bottom: 1px solid #000; font-size: 11pt; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
.harvard-cv-pdf-export .entry-header, .harvard-cv-pdf-export .entry-sub { display: flex; justify-content: space-between; }
.harvard-cv-pdf-export .entry-header { font-size: 10.5pt; font-weight: 700; }
.harvard-cv-pdf-export .entry-sub { font-size: 10pt; font-style: italic; }
.harvard-cv-pdf-export ul { margin: 4px 0 0 18px; padding: 0; }
.harvard-cv-pdf-export li { margin-bottom: 2px; }
.harvard-cv-pdf-export p { margin: 4px 0; }
.harvard-cv-pdf-export .cv-optional-preview-block { display: block; }
`;

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
    [clone, ...clone.querySelectorAll('*')].forEach((node) => {
        [...node.attributes].forEach((attribute) => {
            if (attribute.name.startsWith('x-') || attribute.name.startsWith(':') || attribute.name.startsWith('@')) {
                node.removeAttribute(attribute.name);
            }
        });
        node.style.color = '#000000';
        node.style.backgroundColor = 'transparent';
        node.style.borderColor = '#000000';
        node.style.outlineColor = '#000000';
        node.style.textDecorationColor = '#000000';
        node.style.boxShadow = 'none';
        if (node instanceof SVGElement) {
            node.style.fill = '#000000';
            node.style.stroke = '#000000';
        }
    });
    clone.style.backgroundColor = '#ffffff';
    clone.querySelectorAll('.cv-optional-preview-block').forEach((node) => {
        if (node.textContent?.trim()) {
            node.style.display = 'block';
        }
    });

    const wrapper = document.createElement('div');
    wrapper.setAttribute('aria-hidden', 'true');
    wrapper.setAttribute('x-ignore', '');
    wrapper.style.cssText = 'position:fixed;left:-10000px;top:0;z-index:-1;background:#fff;';
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    return { clone, wrapper };
}

/**
 * @param {HTMLElement} sourceEl
 * @param {string} filename
 */
export async function renderHarvardCvPdf(sourceEl, filename) {
    if (!sourceEl) {
        throw new Error('CV preview element not found');
    }

    const html2pdf = await loadHtml2Pdf();
    const { clone, wrapper } = mountClone(sourceEl);

    try {
        return await html2pdf()
            .set({
                margin: 0.5,
                filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false,
                    onclone: (clonedDocument) => {
                        clonedDocument.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => node.remove());
                        const safeStyle = clonedDocument.createElement('style');
                        safeStyle.textContent = PDF_SAFE_STYLES;
                        clonedDocument.head.appendChild(safeStyle);
                    },
                },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
            })
            .from(clone)
            .outputPdf('blob');
    } finally {
        wrapper.remove();
        cleanupHtml2pdfArtifacts();
    }
}

export function downloadPdfBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url; anchor.download = filename; anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export async function exportHarvardCvPdf(sourceEl, filename) {
    const blob = await renderHarvardCvPdf(sourceEl, filename);
    downloadPdfBlob(blob, filename);
    return blob;
}
