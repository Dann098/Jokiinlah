import { mkdir, unlink, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const baseUrl = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8012';
const debuggerUrl = process.env.QA_DEBUGGER_URL ?? 'http://127.0.0.1:9235';
const outputDirectory = path.resolve('docs/screenshots/cv-builder');
const temporaryPdfDirectory = path.join(os.tmpdir(), 'jokiinlah-cv-builder-qa');
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

function pdfPageCount(data) {
    return (Buffer.from(data, 'base64').toString('latin1').match(/\/Type\s*\/Page\b/g) ?? []).length;
}

async function printPdf(filename) {
    const result = await cdp.send('Page.printToPDF', { printBackground: true, preferCSSPageSize: true });
    const temporaryPath = path.join(temporaryPdfDirectory, filename);
    await writeFile(temporaryPath, Buffer.from(result.data, 'base64'));
    const pageCount = pdfPageCount(result.data);
    await unlink(temporaryPath);
    return pageCount;
}

const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl,
    viewports: [],
    interactions: {},
    print: {},
    console_errors: consoleErrors,
    network_errors: networkErrors,
    status: 'failed',
};

await mkdir(outputDirectory, { recursive: true });
await mkdir(temporaryPdfDirectory, { recursive: true });
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
    await state(`(() => {
        state.clearPhoto();
        state.personal = { ...state.personal, fullName: 'Nadia Prameswari', email: 'nadia@example.com' };
        state.summary = 'Fresh graduate Sistem Informasi yang teliti dan siap berkontribusi.';
        state.experiences = [];
        state.educations = [];
        state.projects = [];
        state.certifications = [];
        state.skillCategories = [];
        return true;
    })()`);
    await delay(100);
    const onePage = await printPdf('cv-academic-classic-one-page.pdf');
    if (onePage !== 1) throw new Error(`Dokumen singkat tercetak ${onePage} halaman, diharapkan 1.`);

    await state(`(() => {
        state.loadSampleData();
        while (state.experiences.length < 8) state.addExperience();
        state.experiences = state.experiences.map((item, index) => ({
            ...item,
            organization: item.organization || 'Organisasi Contoh ' + (index + 1),
            position: item.position || 'Koordinator Program',
            bullets: Array.from({ length: 5 }, (_, bulletIndex) => 'Pencapaian contoh ' + (bulletIndex + 1) + ' yang menjelaskan kontribusi, proses, dan hasil kerja secara terukur untuk pengujian dokumen panjang.'),
        }));
        return true;
    })()`);
    await delay(150);
    const multiplePages = await printPdf('cv-academic-classic-multiple-pages.pdf');
    if (multiplePages < 2) throw new Error(`Dokumen panjang hanya tercetak ${multiplePages} halaman.`);

    await cdp.send('Emulation.setEmulatedMedia', { media: 'print' });
    const printStyles = await evaluate(`({
        navbarHidden: getComputedStyle(document.querySelector('body > header')).display === 'none',
        footerHidden: getComputedStyle(document.querySelector('body > footer')).display === 'none',
        editorHidden: getComputedStyle(document.querySelector('.cv-editor')).display === 'none',
        font: getComputedStyle(document.querySelector('#cv-print-root')).fontFamily,
    })`);
    await cdp.send('Emulation.setEmulatedMedia', { media: 'screen' });
    if (!printStyles.navbarHidden || !printStyles.footerHidden || !printStyles.editorHidden || !printStyles.font.toLowerCase().includes('arial')) throw new Error(`Print CSS gagal: ${JSON.stringify(printStyles)}`);
    report.print = { one_page_pdf_pages: onePage, multiple_page_pdf_pages: multiplePages, navbar_hidden: true, footer_hidden: true, form_hidden: true, local_font: printStyles.font };

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
