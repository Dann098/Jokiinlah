import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.QA_BASE_URL;
const debuggerUrl = process.env.QA_DEBUGGER_URL;
const browserName = process.env.QA_BROWSER_NAME ?? 'Chromium';
const outputDirectory = path.resolve('docs/screenshots/word-to-pdf');

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
const longName = `${'laporan-penelitian-dengan-nama-sangat-panjang-'.repeat(5)}Indonesia.docx`;
await mkdir(outputDirectory, { recursive: true });

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

const consoleErrors = [];
const networkErrors = [];
cdp.on('Runtime.exceptionThrown', (event) => consoleErrors.push(event.exceptionDetails?.text ?? 'Runtime exception'));
cdp.on('Log.entryAdded', (event) => {
    if (event.entry?.level === 'error') consoleErrors.push(event.entry.text);
});
cdp.on('Network.responseReceived', (event) => {
    if (event.response.status >= 400) networkErrors.push({ status: event.response.status, url: event.response.url });
});

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
    return result.result.value;
}

async function waitUntil(expression, timeout = 10000) {
    const started = Date.now();
    while (Date.now() - started < timeout) {
        if (await evaluate(expression)) return;
        await delay(50);
    }
    throw new Error(`Timeout menunggu kondisi: ${expression}`);
}

async function setViewport(width, height) {
    await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
}

async function navigate() {
    const loaded = cdp.waitFor('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: `${baseUrl}/fitur-gratis/word-ke-pdf` });
    await loaded;
    await waitUntil(`Boolean(window.Alpine && document.querySelector('[x-data^="wordToPdfUpload"]')?._x_dataStack)`);
}

async function setVirtualFile(name, mimeType, bytes) {
    await evaluate(`(() => {
        const input = document.querySelector('#word-document');
        if (!input) throw new Error('Input Word tidak ditemukan.');
        const transfer = new DataTransfer();
        transfer.items.add(new File([new Uint8Array(${JSON.stringify(bytes)})], ${JSON.stringify(name)}, { type: ${JSON.stringify(mimeType)} }));
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    })()`);
    await delay(100);
}

async function screenshot(filename) {
    const capture = await cdp.send('Page.captureScreenshot', { format: 'png', fromSurface: true });
    await writeFile(path.join(outputDirectory, filename), Buffer.from(capture.data, 'base64'));
}

const report = {
    generated_at: new Date().toISOString(),
    browser: browserName,
    status: 'running',
    responsive_status: 'running',
    viewports: [],
    validation: {},
    limitations: {
        libreoffice_available: false,
        real_doc_docx_conversion: 'not run',
        pdf_layout_content_checks: 'not run',
        server_error_states: 'covered by PHPUnit, not browser automation',
    },
};

try {
    const viewports = [[360, 800], [390, 844], [768, 1024], [1024, 768], [1366, 768], [1440, 900]];
    for (const [width, height] of viewports) {
        await setViewport(width, height);
        await navigate();
        await setVirtualFile(
            longName,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            [80, 75, 3, 4, 102, 105, 120, 116, 117, 114, 101],
        );

        const metrics = await evaluate(`(() => {
            const root = document.querySelector('[x-data^="wordToPdfUpload"]');
            const state = Alpine.$data(root);
            const dropzone = document.querySelector('.word-converter-dropzone').getBoundingClientRect();
            const button = document.querySelector('button[type="submit"]').getBoundingClientRect();
            const filenameElement = document.querySelector('[x-text="file?.name"]');
            const filename = filenameElement.getBoundingClientRect();
            return {
                h1Count: document.querySelectorAll('h1').length,
                overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
                dropzoneClipped: dropzone.left < 0 || dropzone.right > window.innerWidth,
                submitHeight: button.height,
                filenameOverflow: filename.right > window.innerWidth || filename.left < 0,
                filenameVisible: filenameElement.getClientRects().length > 0,
                filenameExact: state.file?.name === ${JSON.stringify(longName)} && filenameElement.textContent === ${JSON.stringify(longName)},
                clientErrorEmpty: state.clientError === '',
                privacyPresent: document.body.innerText.includes('File digunakan sementara untuk proses konversi dan tidak disimpan secara permanen.'),
                loadingLiveRegion: Boolean(document.querySelector('[aria-live="polite"]')),
                errorAlert: Boolean(document.querySelector('[role="alert"]')),
            };
        })()`);

        if (metrics.h1Count !== 1 || metrics.overflow || metrics.dropzoneClipped || metrics.submitHeight < 44 || metrics.filenameOverflow || !metrics.filenameVisible || !metrics.filenameExact || !metrics.clientErrorEmpty || !metrics.privacyPresent || !metrics.loadingLiveRegion || !metrics.errorAlert) {
            throw new Error(`Viewport ${width}x${height} gagal: ${JSON.stringify(metrics)}`);
        }

        const filename = `word-to-pdf-${width}x${height}.png`;
        await screenshot(filename);
        report.viewports.push({ viewport: `${width}x${height}`, ...metrics, screenshot: `docs/screenshots/word-to-pdf/${filename}` });
    }

    await setViewport(390, 844);
    await navigate();
    await setVirtualFile('dokumen-salah.pdf', 'application/pdf', [37, 80, 68, 70, 45, 49, 46, 52]);
    const validation = await evaluate(`(() => {
        const state = Alpine.$data(document.querySelector('[x-data^="wordToPdfUpload"]'));
        return { error: state.clientError, fileCleared: state.file === null };
    })()`);
    if (!validation.error.includes('DOC atau DOCX') || !validation.fileCleared) {
        throw new Error(`Validasi PDF gagal: ${JSON.stringify(validation)}`);
    }
    report.validation.pdf_rejected = true;
    report.validation.long_filename_safe = true;

    if (consoleErrors.length || networkErrors.length) {
        throw new Error(`Browser error: ${JSON.stringify({ consoleErrors, networkErrors })}`);
    }

    report.console_errors = consoleErrors;
    report.network_errors = networkErrors;
    report.responsive_status = 'passed';
    report.status = 'partial';
} catch (error) {
    report.status = 'failed';
    report.failure = error.stack ?? String(error);
    throw error;
} finally {
    await writeFile(path.join(outputDirectory, 'browser-qa-report.json'), `${JSON.stringify(report, null, 2)}\n`);
    cdp.socket.close();
    console.log(JSON.stringify(report, null, 2));
}
