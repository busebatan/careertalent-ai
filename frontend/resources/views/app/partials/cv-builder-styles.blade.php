[x-cloak] { display: none !important; }
.harvard-cv {
    font-family: Georgia, 'Times New Roman', Times, serif;
    color: #000;
    background: #fff;
    font-size: 11pt;
    line-height: 1.35;
}
.harvard-cv h1 {
    font-size: 18pt;
    font-weight: bold;
    text-align: center;
    margin: 0 0 4px;
    letter-spacing: 0.02em;
}
.harvard-cv .contact {
    text-align: center;
    font-size: 10pt;
    margin-bottom: 12px;
}
.harvard-cv h2 {
    font-size: 11pt;
    font-weight: bold;
    text-transform: uppercase;
    border-bottom: 1px solid #000;
    margin: 14px 0 6px;
    padding-bottom: 2px;
    letter-spacing: 0.05em;
}
.harvard-cv .entry-header {
    display: flex;
    justify-content: space-between;
    font-weight: bold;
    font-size: 10.5pt;
}
.harvard-cv .entry-sub {
    display: flex;
    justify-content: space-between;
    font-style: italic;
    font-size: 10pt;
}
.harvard-cv ul { margin: 4px 0 0 18px; padding: 0; }
.harvard-cv li { margin-bottom: 2px; }
.harvard-cv p { margin: 4px 0; }
.harvard-cv-pdf-export {
    font-family: Georgia, 'Times New Roman', Times, serif !important;
    font-size: 11pt !important;
    line-height: 1.35 !important;
    width: 6.5in !important;
    max-width: 6.5in !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    border: none !important;
    border-radius: 0 !important;
    background: #fff !important;
    color: #000 !important;
}
.harvard-cv-pdf-export h1 {
    font-size: 18pt !important;
    font-weight: bold !important;
    text-align: center !important;
    margin: 0 0 4px !important;
    letter-spacing: 0.02em !important;
}
.harvard-cv-pdf-export .contact {
    text-align: center !important;
    font-size: 10pt !important;
    margin-bottom: 12px !important;
}
.harvard-cv-pdf-export h2 {
    font-size: 11pt !important;
    font-weight: bold !important;
    text-transform: uppercase !important;
    border-bottom: 1px solid #000 !important;
    margin: 14px 0 6px !important;
    padding-bottom: 2px !important;
    letter-spacing: 0.05em !important;
}
.harvard-cv-pdf-export .entry-header {
    display: flex !important;
    justify-content: space-between !important;
    font-weight: bold !important;
    font-size: 10.5pt !important;
}
.harvard-cv-pdf-export .entry-sub {
    display: flex !important;
    justify-content: space-between !important;
    font-style: italic !important;
    font-size: 10pt !important;
}
.harvard-cv-pdf-export ul { margin: 4px 0 0 18px !important; padding: 0 !important; }
.harvard-cv-pdf-export li { margin-bottom: 2px !important; }
.harvard-cv-pdf-export p { margin: 4px 0 !important; }
.harvard-cv-pdf-export .cv-optional-preview-block { display: block !important; }
@media print {
    body * { visibility: hidden; }
    #harvard-preview, #harvard-preview * { visibility: visible; }
    #harvard-preview { position: absolute; left: 0; top: 0; width: 100%; padding: 0.5in; }
}
