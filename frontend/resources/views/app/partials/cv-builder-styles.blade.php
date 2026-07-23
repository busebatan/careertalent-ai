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
@media print {
    body * { visibility: hidden; }
    #harvard-preview, #harvard-preview * { visibility: visible; }
    #harvard-preview { position: absolute; left: 0; top: 0; width: 100%; padding: 0.5in; }
}
