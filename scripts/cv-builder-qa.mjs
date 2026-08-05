import { mkdir, unlink, writeFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import path from 'node:path';

const baseUrl = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8012';
const debuggerUrl = process.env.QA_DEBUGGER_URL ?? 'http://127.0.0.1:9235';
const browserName = process.env.QA_BROWSER_NAME ?? 'Chromium';
const pdfTextExecutable = process.env.QA_PDFTOTEXT_PATH;
const outputDirectory = path.resolve('docs/screenshots/cv-builder');
const pdfOutputDirectory = path.resolve('artifacts/cv');
const invalidPhoto = path.resolve('scripts/fixtures/cv-invalid.svg');
const validPhoto = path.resolve('public/images/logo.webp');
const storageKey = 'jokiinlah_cv_academic_classic_v1';

class CdpClient {
    constructor(url) {
        this.socket = new WebSocket(url);
        this.nextId = 1;
        this.pending = new Map();
        this.listeners = new Map();
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.socket.addEventListener('open', resolve, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });
        this.socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.id && this.pending.has(message.id)) {
                const callback = this.pending.get(message.id);
                this.pending.delete(message.id);
                message.error ? callback.reject(new Error(message.error.message)) : callback.resolve(message.result);
                return;
            }
            for (const listener of this.listeners.get(message.method) ?? []) listener(message.params);
        });
    }

    send(method, params = {}) {
        const id = this.nextId++;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    on(method, listener) {
        this.listeners.set(method, [...(this.listeners.get(method) ?? []), listener]);
    }

    waitFor(method, timeout = 15000) {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error(`Timeout menunggu ${method}`)), timeout);
            const listener = (params) => {
                clearTimeout(timer);
                this.listeners.set(method, (this.listeners.get(method) ?? []).filter((item) => item !== listener));
                resolve(params);
            };
            this.on(method, listener);
        });
    }
}

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
const targetResponse = await fetch(`${debuggerUrl}/json/new?about:blank`, { method: 'PUT' });
if (!targetResponse.ok) throw new Error('Browser debugger tidak menyediakan target baru.');
const target = await targetResponse.json();
const cdp = new CdpClient(target.webSocketDebuggerUrl);
await cdp.connect();
await Promise.all([
    cdp.send('Page.enable'),
    cdp.send('Runtime.enable'),
    cdp.send('Network.enable'),
    cdp.send('Log.enable'),
    cdp.send('DOM.enable'),
]);

let documentStatus = null;
const consoleErrors = [];
const networkErrors = [];
cdp.on('Runtime.exceptionThrown', (event) => consoleErrors.push(event.exceptionDetails?.exception?.description ?? event.exceptionDetails?.text ?? 'Runtime exception'));
cdp.on('Log.entryAdded', (event) => {
    if (event.entry?.level === 'error') consoleErrors.push(event.entry.text);
});
cdp.on('Network.responseReceived', (event) => {
    if (event.type === 'Document' && event.response.url.startsWith(baseUrl)) documentStatus = event.response.status;
    if (event.response.status >= 400) networkErrors.push({ status: event.response.status, url: event.response.url });
});

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
    return result.result.value;
}

async function setViewport(width, height) {
    await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
}

async function navigate(pathname = '/fitur-gratis/pembuat-cv') {
    documentStatus = null;
    const loaded = cdp.waitFor('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: baseUrl + pathname });
    await loaded;
    await delay(800);
    if (documentStatus !== 200) throw new Error(`${pathname} mengembalikan ${documentStatus}.`);
    await evaluate(`new Promise((resolve, reject) => {
        const started = Date.now();
        const timer = setInterval(() => {
            if (window.Alpine && document.querySelector('[x-data="cvBuilder"]')?._x_dataStack) {
                clearInterval(timer); resolve(true);
            } else if (Date.now() - started > 5000) {
                clearInterval(timer); reject(new Error('Alpine cvBuilder tidak siap'));
            }
        }, 50);
    })`);
}

async function state(expression) {
    return evaluate(`(() => { const state = Alpine.$data(document.querySelector('[x-data="cvBuilder"]')); return eval(${JSON.stringify(expression)}); })()`);
}

async function setFile(filePath) {
    const document = await cdp.send('DOM.getDocument', { depth: -1, pierce: true });
    const input = await cdp.send('DOM.querySelector', { nodeId: document.root.nodeId, selector: 'input[type=file]' });
    if (!input.nodeId) throw new Error('Input foto tidak ditemukan.');
    await cdp.send('DOM.setFileInputFiles', { nodeId: input.nodeId, files: [filePath] });
    await delay(250);
}

async function printPdf(filename) {
    await cdp.send('Emulation.setEmulatedMedia', { media: 'print' });
    let result;
    try {
        result = await cdp.send('Page.printToPDF', {
            printBackground: true,
            preferCSSPageSize: true,
            scale: 1,
        });
    } finally {
        await cdp.send('Emulation.setEmulatedMedia', { media: 'screen' });
    }
    const pdf = Buffer.from(result.data, 'base64');
    const pdfText = pdf.toString('latin1');
    const pdfPath = path.join(pdfOutputDirectory, filename);
    await writeFile(pdfPath, pdf);
    if (!pdfTextExecutable) throw new Error('QA_PDFTOTEXT_PATH wajib diatur untuk validasi isi PDF per halaman.');

    const extraction = spawnSync(pdfTextExecutable, ['-layout', pdfPath, '-'], {
        encoding: 'utf8',
        maxBuffer: 10 * 1024 * 1024,
    });
    if (extraction.status !== 0) {
        throw new Error(`Ekstraksi PDF gagal: ${extraction.stderr || `exit code ${extraction.status}`}`);
    }
    const pages = extraction.stdout
        .replace(/\r\n/gu, '\n')
        .split('\f')
        .filter((page, index, collection) => page.trim() || index < collection.length - 1);
    const mediaBoxes = Array.from(pdfText.matchAll(/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/gu))
        .map((match) => ({ width: Number(match[1]), height: Number(match[2]) }));

    return {
        pageCount: pages.length,
        imageCount: (pdfText.match(/\/Subtype\s*\/Image\b/g) ?? []).length,
        pages,
        nonEmptyLineCounts: pages.map((page) => page.split('\n').filter((line) => line.trim()).length),
        isA4: mediaBoxes.length >= pages.length
            && mediaBoxes.every((box) => Math.abs(box.width - 595.28) <= 1 && Math.abs(box.height - 841.89) <= 1),
    };
}

const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl,
    browser: browserName,
    viewports: [],
    interactions: {},
    print: {},
    console_errors: consoleErrors,
    network_errors: networkErrors,
    status: 'failed',
};

await mkdir(outputDirectory, { recursive: true });
await mkdir(pdfOutputDirectory, { recursive: true });
for (const legacyPdf of ['cv-academic-classic-one-page.pdf', 'cv-academic-classic-multiple-pages.pdf']) {
    try {
        await unlink(path.join(outputDirectory, legacyPdf));
    } catch (error) {
        if (error.code !== 'ENOENT') throw error;
    }
}

try {
    await setViewport(1366, 768);
    await navigate();
    await evaluate(`localStorage.removeItem(${JSON.stringify(storageKey)})`);
    await state("state.loadSampleData(); true");
    await delay(700);

    const sample = await evaluate(`({
        name: document.querySelector('.cv-document-name').textContent,
        toast: document.querySelector('.cv-toast').textContent,
        h1Count: document.querySelectorAll('h1').length,
        formCount: document.querySelectorAll('form').length,
        draft: JSON.parse(localStorage.getItem(${JSON.stringify(storageKey)})),
    })`);
    if (sample.name !== 'Nadia Prameswari' || sample.h1Count !== 1 || sample.formCount !== 0) throw new Error(`Sample tidak valid: ${JSON.stringify(sample)}`);
    if ('photoPreview' in sample.draft) throw new Error('Foto masuk ke draft localStorage.');

    const repeater = await state(`(() => {
        const before = state.experiences.length;
        state.addExperience();
        const afterAdd = state.experiences.length;
        state.removeExperience(afterAdd - 1);
        return { before, afterAdd, afterRemove: state.experiences.length };
    })()`);
    if (repeater.afterAdd !== repeater.before + 1 || repeater.afterRemove !== repeater.before) throw new Error('Repeater pengalaman gagal.');

    const sectionToggle = await state(`(() => {
        state.toggleSection('projects');
        return state.sections.projects;
    })()`);
    await delay(100);
    const projectsVisible = await evaluate(`Array.from(document.querySelectorAll('.cv-document-section > h3')).find((node) => node.textContent === 'PROYEK')?.parentElement.getClientRects().length ?? 0`);
    if (sectionToggle !== false || projectsVisible !== 0) throw new Error('Toggle bagian proyek gagal.');

    const xssPayload = '<img src=x onerror=window.__cvXss=1>';
    await state(`state.personal = { ...state.personal, fullName: ${JSON.stringify(xssPayload)} }; true`);
    await delay(100);
    const xss = await evaluate(`({ text: document.querySelector('.cv-document-name').textContent, nestedImage: !!document.querySelector('.cv-document-name img'), executed: window.__cvXss === 1 })`);
    if (xss.text !== xssPayload || xss.nestedImage || xss.executed) throw new Error(`XSS tidak aman: ${JSON.stringify(xss)}`);

    await state("state.projects = [{ ...state.projects[0], url: 'javascript:alert(1)' }]; state.sections = { ...state.sections, projects: true }; true");
    await delay(100);
    const dangerousLinkVisible = await evaluate("document.querySelector('.cv-document-section a.cv-document-link')?.getClientRects().length ?? 0");
    if (dangerousLinkVisible !== 0) throw new Error('URL javascript tampil pada preview.');

    await state('state.usePhoto = true; true');
    await setFile(invalidPhoto);
    const invalidPhotoState = await state("({ error: state.photoError, preview: state.photoPreview })");
    if (!invalidPhotoState.error.includes('SVG') || invalidPhotoState.preview) throw new Error('Foto SVG tidak ditolak.');
    await setFile(validPhoto);
    const validPhotoState = await state("({ error: state.photoError, preview: state.photoPreview, usePhoto: state.usePhoto })");
    if (validPhotoState.error || !validPhotoState.preview.startsWith('blob:') || !validPhotoState.usePhoto) throw new Error('Foto WebP lokal gagal.');

    await state("state.loadSampleData(); true");
    await delay(700);
    await navigate();
    const restoredName = await state('state.personal.fullName');
    if (restoredName !== 'Nadia Prameswari') throw new Error('Draft tidak pulih setelah refresh.');

    const dialog = cdp.waitFor('Page.javascriptDialogOpening');
    const resetAction = state("state.clearAllData(); true");
    await dialog;
    await cdp.send('Page.handleJavaScriptDialog', { accept: true });
    await resetAction;
    await delay(750);
    const reset = await state(`({ name: state.personal.fullName, stored: localStorage.getItem(${JSON.stringify(storageKey)}), photo: state.photoPreview })`);
    if (reset.name || reset.stored !== null || reset.photo) throw new Error(`Reset gagal: ${JSON.stringify(reset)}`);
    report.interactions = { sample: true, repeater: true, section_toggle: true, local_storage_restore: true, reset: true, invalid_svg: true, valid_local_photo: true, xss_as_text: true, dangerous_url_rejected: true };

    for (const [width, height] of [[360, 800], [390, 844], [768, 1024], [1024, 768], [1366, 768], [1440, 900], [1920, 1080]]) {
        await setViewport(width, height);
        await navigate();
        await state("state.loadSampleData(); true");
        await delay(300);
        if (width < 1024) await state("state.setMobileTab('preview'); true");
        await delay(150);
        const diagnostics = await evaluate(`({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            previewVisible: document.querySelector('#cv-print-root').getClientRects().length > 0,
            mobilePreviewActionVisible: Array.from(document.querySelectorAll('.cv-action-button')).find((button) => button.textContent.trim() === 'Preview')?.getClientRects().length > 0,
            brokenImages: Array.from(document.images).filter((image) => image.complete && image.naturalWidth === 0).length,
        })`);
        if (diagnostics.scrollWidth > diagnostics.clientWidth + 1 || !diagnostics.previewVisible || diagnostics.brokenImages || (width >= 1024 && diagnostics.mobilePreviewActionVisible)) throw new Error(`Viewport ${width}x${height} gagal: ${JSON.stringify(diagnostics)}`);
        const screenshot = await cdp.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
        const filename = `cv-builder-${width}x${height}.png`;
        await writeFile(path.join(outputDirectory, filename), Buffer.from(screenshot.data, 'base64'));
        report.viewports.push({ viewport: `${width}x${height}`, horizontal_overflow: false, preview_visible: true, screenshot: `docs/screenshots/cv-builder/${filename}` });
    }

    await setViewport(1440, 900);
    await navigate();
    await state("state.loadSampleData(); state.clearPhoto(); state.usePhoto = false; true");
    await delay(150);
    const sampleWithoutPhoto = await printPdf('cv-academic-classic-sample.pdf');
    if (sampleWithoutPhoto.pageCount !== 1) throw new Error(`Data contoh tercetak ${sampleWithoutPhoto.pageCount} halaman, diharapkan 1.`);
    const samplePdfText = sampleWithoutPhoto.pages.join('\n').replace(/\s+/gu, ' ');
    const forbiddenPrintText = ['Beranda', 'Konsultasi Sekarang', 'Buat CV Profesional Secara Gratis', 'Muat Data Contoh', 'Data CV diproses di perangkat'];
    if (!sampleWithoutPhoto.isA4 || !samplePdfText.includes('Agustus 2023 - Agustus 2024') || !samplePdfText.includes('Soft Skills: Analitis, Komunikasi, Kerja Tim') || forbiddenPrintText.some((text) => samplePdfText.includes(text))) {
        throw new Error(`Isi PDF data contoh gagal: ${JSON.stringify({ isA4: sampleWithoutPhoto.isA4, samplePdfText })}`);
    }

    const previewProjectPeriods = await evaluate(`(() => Array.from(document.querySelectorAll('.cv-document-date--freeform'))
        .filter((period) => period.getClientRects().length > 0)
        .map((period) => {
            const range = document.createRange();
            range.selectNodeContents(period);
            const lineTops = new Set(Array.from(range.getClientRects()).map((rect) => Math.round(rect.top)));

            return {
                text: period.textContent.trim(),
                lineCount: lineTops.size,
                fitsHorizontally: period.scrollWidth <= period.clientWidth + 1,
            };
        }))()`);
    if (previewProjectPeriods.map(({ text }) => text).join(',') !== '2024,2025' || previewProjectPeriods.some(({ lineCount, fitsHorizontally }) => lineCount !== 1 || !fitsHorizontally)) throw new Error(`Tahun proyek terpecah di preview: ${JSON.stringify(previewProjectPeriods)}`);

    await cdp.send('Emulation.setEmulatedMedia', { media: 'print' });
    const printLayout = await evaluate(`(() => {
        const visible = (element) => element && element.getClientRects().length > 0;
        const section = (title) => Array.from(document.querySelectorAll('.cv-document-section'))
            .find((item) => item.querySelector('h3')?.textContent.trim() === title);
        const skills = section('KEAHLIAN');
        const date = Array.from(document.querySelectorAll('.cv-document-date'))
            .find((item) => item.textContent.trim() === 'Agustus 2023 – Agustus 2024');
        const dateRange = document.createRange();
        dateRange.selectNodeContents(date);
        const dateRect = date.getBoundingClientRect();
        const dateTextRect = dateRange.getBoundingClientRect();
        const dateRowRect = date.closest('.cv-document-row').getBoundingClientRect();
        const header = document.querySelector('.cv-document-header');
        const identity = document.querySelector('.cv-document-identity');
        const contactRows = Array.from(document.querySelectorAll('.cv-document-contacts-row')).filter(visible);
        const firstContacts = contactRows.map((row) => Array.from(row.querySelectorAll('.cv-document-contact')).find(visible));
        const contactRowsAreSingleLine = contactRows.every((row) => {
            const lineHeight = parseFloat(getComputedStyle(row).lineHeight);
            return row.getBoundingClientRect().height <= lineHeight * 1.5;
        });
        const protectedElements = Array.from(document.querySelectorAll('.cv-document-item, .cv-document-certification')).filter(visible);
        const projectPeriods = Array.from(document.querySelectorAll('.cv-document-date--freeform')).filter(visible)
            .map((period) => {
                const range = document.createRange();
                range.selectNodeContents(period);
                const lineTops = new Set(Array.from(range.getClientRects()).map((rect) => Math.round(rect.top)));

                return {
                    text: period.textContent.trim(),
                    lineCount: lineTops.size,
                    fitsHorizontally: period.scrollWidth <= period.clientWidth + 1,
                };
            });
        const paper = document.querySelector('#cv-print-root');
        const bodyFontPt = parseFloat(getComputedStyle(paper).fontSize) * 72 / 96;

        return {
            skillsBreakInside: getComputedStyle(skills).breakInside,
            skillsPageBreakInside: getComputedStyle(skills).pageBreakInside,
            protectedItems: protectedElements.every((item) => {
                const style = getComputedStyle(item);
                return style.breakInside === 'avoid' && style.pageBreakInside === 'avoid';
            }),
            projectPeriods,
            date: {
                text: date.textContent.trim(),
                whiteSpace: getComputedStyle(date).whiteSpace,
                overflowX: getComputedStyle(date).overflowX,
                minWidth: getComputedStyle(date).minWidth,
                scrollWidth: date.scrollWidth,
                clientWidth: date.clientWidth,
                textInsideElement: dateTextRect.left >= dateRect.left - 0.5 && dateTextRect.right <= dateRect.right + 0.5,
                elementInsideRow: dateRect.left >= dateRowRect.left - 0.5 && dateRect.right <= dateRowRect.right + 0.5,
            },
            contactRows: contactRows.length,
            contactRowsFit: contactRows.every((row) => row.scrollWidth <= row.clientWidth + 1),
            contactRowsAreSingleLine,
            contactRowsStartClean: firstContacts.every((contact) => {
                const dot = contact?.querySelector('.cv-document-dot');
                return !dot || getComputedStyle(dot).display === 'none';
            }),
            noPhoto: !document.querySelector('.cv-document-photo') && !header.classList.contains('has-photo'),
            noPhotoUsesFullWidth: Math.abs(identity.getBoundingClientRect().width - header.getBoundingClientRect().width) <= 1,
            bodyFontPt,
            paperFitsHorizontally: paper.scrollWidth <= paper.clientWidth + 1,
            headingsHaveRules: Array.from(document.querySelectorAll('.cv-document-section > h3')).filter(visible)
                .every((heading) => parseFloat(getComputedStyle(heading).borderBottomWidth) > 0),
            printedControls: Array.from(document.querySelectorAll('button, form, .cv-preview-toolbar, .cv-editor, .no-print')).filter(visible).length,
            orphans: getComputedStyle(paper).orphans,
            widows: getComputedStyle(paper).widows,
        };
    })()`);
    await cdp.send('Emulation.setEmulatedMedia', { media: 'screen' });

    if (printLayout.skillsBreakInside !== 'avoid' || printLayout.skillsPageBreakInside !== 'avoid' || !printLayout.protectedItems) throw new Error(`Proteksi page break gagal: ${JSON.stringify(printLayout)}`);
    if (printLayout.projectPeriods.map(({ text }) => text).join(',') !== '2024,2025' || printLayout.projectPeriods.some(({ lineCount, fitsHorizontally }) => lineCount !== 1 || !fitsHorizontally)) throw new Error(`Tahun proyek terpecah saat print: ${JSON.stringify(printLayout.projectPeriods)}`);
    if (printLayout.date.text !== 'Agustus 2023 – Agustus 2024' || printLayout.date.whiteSpace !== 'nowrap' || printLayout.date.overflowX !== 'visible' || ['auto', '0px'].includes(printLayout.date.minWidth) || printLayout.date.scrollWidth > printLayout.date.clientWidth + 1 || !printLayout.date.textInsideElement || !printLayout.date.elementInsideRow) throw new Error(`Tanggal panjang terpotong: ${JSON.stringify(printLayout.date)}`);
    if (printLayout.contactRows < 1 || printLayout.contactRows > 2 || !printLayout.contactRowsFit || !printLayout.contactRowsAreSingleLine || !printLayout.contactRowsStartClean) throw new Error(`Layout kontak gagal: ${JSON.stringify(printLayout)}`);
    if (!printLayout.noPhoto || !printLayout.noPhotoUsesFullWidth) throw new Error(`Layout tanpa foto menyisakan ruang: ${JSON.stringify(printLayout)}`);
    if (printLayout.bodyFontPt < 9.5 || !printLayout.paperFitsHorizontally || !printLayout.headingsHaveRules || printLayout.printedControls || printLayout.orphans !== '2' || printLayout.widows !== '2') throw new Error(`Layout print dasar gagal: ${JSON.stringify(printLayout)}`);

    await state(`(() => {
        state.projects = state.projects.map((project, index) => index === 0
            ? {
                ...project,
                period: 'Januari 2024 – Desember 2025 (fase pengembangan dan peluncuran nasional)',
                url: 'https://portofolio.example.com/proyek/dashboard-kinerja-penjualan-dan-analisis-data',
            }
            : project);
        return true;
    })()`);
    await delay(100);
    await cdp.send('Emulation.setEmulatedMedia', { media: 'print' });
    const portfolioUrlLayout = await evaluate(`(() => {
        const link = document.querySelector('.cv-document-link');
        const list = link.nextElementSibling;
        const linkRect = link.getBoundingClientRect();
        const period = document.querySelector('.cv-document-date--freeform');
        const periodRect = period.getBoundingClientRect();
        const periodRowRect = period.closest('.cv-document-row').getBoundingClientRect();
        const range = document.createRange();
        range.selectNodeContents(link);
        const lineRects = Array.from(range.getClientRects());
        return {
            lineCount: lineRects.length,
            fitsHorizontally: link.scrollWidth <= link.clientWidth + 1
                && lineRects.every((rect) => rect.left >= linkRect.left - 0.5 && rect.right <= linkRect.right + 0.5),
            fullTextVisible: link.textContent.trim() === 'portofolio.example.com/proyek/dashboard-kinerja-penjualan-dan-analisis-data',
            listStartsAfterUrl: list.getBoundingClientRect().top >= linkRect.bottom - 0.5,
            periodFits: period.scrollWidth <= period.clientWidth + 1
                && periodRect.left >= periodRowRect.left - 0.5
                && periodRect.right <= periodRowRect.right + 0.5
                && getComputedStyle(period).whiteSpace === 'normal',
        };
    })()`);
    await cdp.send('Emulation.setEmulatedMedia', { media: 'screen' });
    if (portfolioUrlLayout.lineCount < 1 || portfolioUrlLayout.lineCount > 2 || !portfolioUrlLayout.fitsHorizontally || !portfolioUrlLayout.fullTextVisible || !portfolioUrlLayout.listStartsAfterUrl || !portfolioUrlLayout.periodFits) throw new Error(`URL portofolio gagal: ${JSON.stringify(portfolioUrlLayout)}`);
    const portfolioPdf = await printPdf('cv-academic-classic-portfolio-url.pdf');
    const compactPortfolioPdfText = portfolioPdf.pages.join('').replace(/\s+/gu, '');
    if (!portfolioPdf.isA4 || !compactPortfolioPdfText.includes('portofolio.example.com/proyek/dashboard-kinerja-penjualan-dan-analisis-data') || portfolioPdf.nonEmptyLineCounts.some((lineCount) => lineCount < 10)) {
        throw new Error(`PDF URL portofolio gagal: ${JSON.stringify({ isA4: portfolioPdf.isA4, lineCounts: portfolioPdf.nonEmptyLineCounts })}`);
    }

    await state('state.loadSampleData(); true');
    await state('state.usePhoto = true; true');
    await setFile(validPhoto);
    await evaluate("document.querySelector('.cv-document-photo').decode()");
    await cdp.send('Emulation.setEmulatedMedia', { media: 'print' });
    const photoLayout = await evaluate(`(() => {
        const photo = document.querySelector('.cv-document-photo');
        const photoRect = photo.getBoundingClientRect();
        const identityRect = document.querySelector('.cv-document-identity').getBoundingClientRect();
        const headerRect = document.querySelector('.cv-document-header').getBoundingClientRect();
        return {
            visible: photo.getClientRects().length > 0,
            decoded: photo.complete && photo.naturalWidth > 0 && photo.naturalHeight > 0,
            objectFit: getComputedStyle(photo).objectFit,
            ratioIsThreeByFour: Math.abs(photoRect.width * 4 - photoRect.height * 3) <= 1.5,
            alignedTopRight: photoRect.top <= headerRect.top + 0.5,
            doesNotOverlapIdentity: photoRect.left >= identityRect.right - 0.5,
            containedByHeader: photoRect.right <= headerRect.right + 0.5,
        };
    })()`);
    await cdp.send('Emulation.setEmulatedMedia', { media: 'screen' });
    if (!photoLayout.visible || !photoLayout.decoded || photoLayout.objectFit !== 'cover' || !photoLayout.ratioIsThreeByFour || !photoLayout.alignedTopRight || !photoLayout.doesNotOverlapIdentity || !photoLayout.containedByHeader) throw new Error(`Foto print gagal: ${JSON.stringify(photoLayout)}`);
    const sampleWithPhoto = await printPdf('cv-academic-classic-sample-with-photo.pdf');
    if (sampleWithPhoto.pageCount !== 1 || !sampleWithPhoto.isA4 || sampleWithPhoto.imageCount <= sampleWithoutPhoto.imageCount) throw new Error(`PDF foto gagal: ${JSON.stringify({ sampleWithoutPhoto, sampleWithPhoto })}`);

    await state(`(() => {
        state.loadSampleData();
        while (state.experiences.length < 4) state.addExperience();
        state.experiences = state.experiences.map((item, index) => ({
            ...item,
            organization: item.organization || 'Organisasi Contoh ' + (index + 1),
            position: item.position || 'Koordinator Program',
            bullets: Array.from({ length: 4 }, (_, bulletIndex) => 'Pencapaian contoh ' + (bulletIndex + 1) + ' yang menjelaskan kontribusi, proses, dan hasil kerja secara terukur untuk pengujian dokumen panjang.'),
        }));
        return true;
    })()`);
    await delay(150);
    const multiplePages = await printPdf('cv-academic-classic-two-pages.pdf');
    const skillLabels = ['Teknis:', 'Perangkat Lunak:', 'Bahasa:', 'Soft Skills:'];
    const skillPageIndexes = new Set(skillLabels.map((label) => multiplePages.pages.findIndex((page) => page.includes(label))));
    if (multiplePages.pageCount !== 2 || !multiplePages.isA4 || multiplePages.nonEmptyLineCounts.some((lineCount) => lineCount < 10) || skillPageIndexes.has(-1) || skillPageIndexes.size !== 1) {
        throw new Error(`Dokumen panjang gagal: ${JSON.stringify({ pageCount: multiplePages.pageCount, isA4: multiplePages.isA4, lineCounts: multiplePages.nonEmptyLineCounts, skillPageIndexes: [...skillPageIndexes] })}`);
    }

    await cdp.send('Emulation.setEmulatedMedia', { media: 'print' });
    const printStyles = await evaluate(`({
        navbarHidden: getComputedStyle(document.querySelector('body > header')).display === 'none',
        footerHidden: getComputedStyle(document.querySelector('body > footer')).display === 'none',
        editorHidden: getComputedStyle(document.querySelector('.cv-editor')).display === 'none',
        font: getComputedStyle(document.querySelector('#cv-print-root')).fontFamily,
    })`);
    await cdp.send('Emulation.setEmulatedMedia', { media: 'screen' });
    if (!printStyles.navbarHidden || !printStyles.footerHidden || !printStyles.editorHidden || !printStyles.font.toLowerCase().includes('arial')) throw new Error(`Print CSS gagal: ${JSON.stringify(printStyles)}`);
    report.print = {
        sample_pdf_pages: sampleWithoutPhoto.pageCount,
        sample_with_photo_pdf_pages: sampleWithPhoto.pageCount,
        sample_with_photo_images: sampleWithPhoto.imageCount,
        multiple_page_pdf_pages: multiplePages.pageCount,
        multiple_page_non_empty_lines: multiplePages.nonEmptyLineCounts,
        skills_page: [...skillPageIndexes][0] + 1,
        skills_kept_together: skillPageIndexes.size === 1 && !skillPageIndexes.has(-1),
        long_date_visible: samplePdfText.includes('Agustus 2023 - Agustus 2024'),
        portfolio_url_wrapped_safely: compactPortfolioPdfText.includes('portofolio.example.com/proyek/dashboard-kinerja-penjualan-dan-analisis-data'),
        photo_printed: sampleWithPhoto.imageCount > sampleWithoutPhoto.imageCount,
        no_photo_full_width: printLayout.noPhotoUsesFullWidth,
        a4_media_box: sampleWithoutPhoto.isA4 && sampleWithPhoto.isA4 && multiplePages.isA4,
        navbar_hidden: true,
        footer_hidden: true,
        form_hidden: true,
        local_font: printStyles.font,
    };

    if (consoleErrors.length || networkErrors.length) throw new Error(`Console/network error: ${JSON.stringify({ consoleErrors, networkErrors })}`);
    report.status = 'passed';
} catch (error) {
    report.failure = error.message;
    process.exitCode = 1;
} finally {
    await writeFile(path.join(outputDirectory, 'visual-qa-report.json'), `${JSON.stringify(report, null, 2)}\n`);
    await cdp.send('Page.close');
    cdp.socket.close();
}

console.log(JSON.stringify(report, null, 2));
