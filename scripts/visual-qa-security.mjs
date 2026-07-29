import crypto from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8006';
const debuggerUrl = process.env.QA_DEBUGGER_URL ?? 'http://127.0.0.1:9230';
const outputDirectory = path.resolve('docs/screenshots/tahap-6');
const fixturePath = path.resolve('scripts/fixtures/visual-qa-document.pdf');
const qaPassword = process.env.QA_PASSWORD;
const qaTotpSecret = process.env.QA_TOTP_SECRET;

if (!qaPassword || !qaTotpSecret) {
    throw new Error('QA_PASSWORD dan QA_TOTP_SECRET wajib diisi melalui environment.');
}

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

    waitFor(method, timeout = 20000) {
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
const pageResponse = await fetch(`${debuggerUrl}/json/new?about:blank`, { method: 'PUT' });
if (!pageResponse.ok) throw new Error('Browser debugger tidak menyediakan target baru.');
const target = await pageResponse.json();
const cdp = new CdpClient(target.webSocketDebuggerUrl);
await cdp.connect();
await Promise.all([
    cdp.send('Page.enable'),
    cdp.send('Runtime.enable'),
    cdp.send('Network.enable'),
    cdp.send('Log.enable'),
    cdp.send('DOM.enable'),
]);
await cdp.send('Network.clearBrowserCookies');

let activeState = 'initial';
let documentStatus = null;
let allowedStatuses = [];
let consoleErrors = [];
let networkErrors = [];

cdp.on('Runtime.exceptionThrown', (event) => {
    consoleErrors.push({
        state: activeState,
        text: event.exceptionDetails?.exception?.description
            ?? event.exceptionDetails?.exception?.value
            ?? event.exceptionDetails?.text
            ?? 'Runtime exception',
    });
});
cdp.on('Log.entryAdded', (event) => {
    const expected = allowedStatuses.some((status) => event.entry?.text?.includes(`status of ${status}`));
    if (event.entry?.level === 'error' && !expected) {
        consoleErrors.push({ state: activeState, text: event.entry.text });
    }
});
cdp.on('Network.responseReceived', (event) => {
    if (event.type === 'Document' && event.response.url.startsWith(baseUrl)) {
        documentStatus = event.response.status;
    }
    if (event.response.status >= 400 && !allowedStatuses.includes(event.response.status)) {
        networkErrors.push({ state: activeState, status: event.response.status, url: event.response.url });
    }
});

const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl,
    scope: 'Tahap 6 Hardening dan Production Readiness',
    viewports: ['360x800', '390x844', '768x1024', '1024x768', '1366x768', '1440x900'],
    states: [],
    console_errors: [],
    network_errors: [],
};

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
    });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
    return result.result.value;
}

async function setViewport(width, height) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile: width < 768,
    });
}

async function navigate(url, expectedStatus = 200) {
    allowedStatuses = expectedStatus >= 400 ? [expectedStatus] : [];
    documentStatus = null;
    const loaded = cdp.waitFor('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: baseUrl + url });
    await loaded;
    await delay(900);
    if (documentStatus !== expectedStatus) {
        throw new Error(`${url} mengembalikan ${documentStatus}, diharapkan ${expectedStatus}.`);
    }
}

async function submit(formSelector, values = {}) {
    allowedStatuses = [];
    documentStatus = null;
    const loaded = cdp.waitFor('Page.loadEventFired');
    await evaluate(`(() => {
        const form = document.querySelector(${JSON.stringify(formSelector)});
        if (!form) throw new Error('Form tidak ditemukan');
        const values = ${JSON.stringify(values)};
        for (const [name, value] of Object.entries(values)) {
            const input = form.querySelector('[name="'+CSS.escape(name)+'"]');
            if (!input) throw new Error('Input tidak ditemukan: '+name);
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        form.submit();
        return true;
    })()`);
    await loaded;
    await delay(900);
}

async function setFile(selector, filePath) {
    const document = await cdp.send('DOM.getDocument', { depth: -1, pierce: true });
    const result = await cdp.send('DOM.querySelector', { nodeId: document.root.nodeId, selector });
    if (!result.nodeId) throw new Error(`Input file tidak ditemukan: ${selector}`);
    await cdp.send('DOM.setFileInputFiles', { nodeId: result.nodeId, files: [filePath] });
}

async function clearSession() {
    await cdp.send('Network.clearBrowserCookies');
}

async function record(name, url, width, height, expectedStatus = 200, includes = []) {
    activeState = name;
    const diagnostics = await evaluate(`(() => ({
        title: document.title,
        location: location.pathname,
        bodyText: document.body.innerText,
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        brokenImages: Array.from(document.images)
            .filter((image) => image.complete && image.naturalWidth === 0)
            .map((image) => image.currentSrc || image.src),
        activeTag: document.activeElement?.tagName ?? null,
        activeName: document.activeElement?.getAttribute('name') ?? null,
    }))()`);

    if (diagnostics.scrollWidth > diagnostics.clientWidth + 1) {
        throw new Error(`${name}: horizontal overflow ${diagnostics.scrollWidth}/${diagnostics.clientWidth}.`);
    }
    if (diagnostics.brokenImages.length) {
        throw new Error(`${name}: asset gambar gagal ${diagnostics.brokenImages.join(', ')}.`);
    }
    for (const text of includes) {
        if (!diagnostics.bodyText.includes(text)) {
            throw new Error(`${name}: teks "${text}" tidak ditemukan di ${diagnostics.location}; body=${diagnostics.bodyText.slice(0, 240)}.`);
        }
    }
    if (consoleErrors.length || networkErrors.length) {
        throw new Error(`${name}: console/network error ${JSON.stringify({ consoleErrors, networkErrors })}.`);
    }

    const screenshot = await cdp.send('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: false,
    });
    const filename = `${name}.png`;
    await writeFile(path.join(outputDirectory, filename), Buffer.from(screenshot.data, 'base64'));
    report.states.push({
        name,
        url,
        viewport: `${width}x${height}`,
        status: expectedStatus,
        title: diagnostics.title,
        screenshot: `docs/screenshots/tahap-6/${filename}`,
        horizontal_overflow: false,
        broken_images: [],
        console_errors: [],
        network_errors: [],
        keyboard_focus: `${diagnostics.activeTag ?? '-'}:${diagnostics.activeName ?? '-'}`,
    });
    report.console_errors.push(...consoleErrors);
    report.network_errors.push(...networkErrors);
    consoleErrors = [];
    networkErrors = [];
}

function totp(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const character of secret.replace(/=+$/, '')) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }
    const bytes = Buffer.alloc(Math.floor(bits.length / 8));
    for (let index = 0; index < bytes.length; index++) {
        bytes[index] = Number.parseInt(bits.slice(index * 8, index * 8 + 8), 2);
    }
    const counter = Math.floor(Date.now() / 1000 / 30);
    const message = Buffer.alloc(8);
    message.writeBigUInt64BE(BigInt(counter));
    const digest = crypto.createHmac('sha1', bytes).update(message).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const value = ((digest[offset] & 0x7f) << 24)
        | ((digest[offset + 1] & 0xff) << 16)
        | ((digest[offset + 2] & 0xff) << 8)
        | (digest[offset + 3] & 0xff);
    return String(value % 1_000_000).padStart(6, '0');
}

await mkdir(outputDirectory, { recursive: true });

await setViewport(360, 800);
await navigate('/login');
await record('01-admin-login', '/login', 360, 800, 200, ['Masuk ke akun']);
await submit('form', { email: process.env.QA_ADMIN_EMAIL ?? 'admin@example.com', password: qaPassword });
await setViewport(390, 844);
await record('02-admin-two-factor-setup', '/keamanan/two-factor', 390, 844, 200, ['Keamanan dua faktor']);

await clearSession();
await setViewport(768, 1024);
await navigate('/login');
await record('03-staff-login', '/login', 768, 1024, 200, ['Masuk ke akun']);
await submit('form', { email: process.env.QA_STAFF_EMAIL ?? 'staff@example.com', password: qaPassword });
await setViewport(1024, 768);
await record('04-two-factor-challenge', '/two-factor-challenge', 1024, 768, 200, ['Verifikasi dua faktor']);
await evaluate("document.querySelector('[name=recovery_code]').focus()");
await setViewport(1366, 768);
await record('05-recovery-code-input', '/two-factor-challenge', 1366, 768, 200, ['kode pemulihan']);
await submit('form', { code: '000000' });
await setViewport(1440, 900);
await record('06-otp-validation-error', '/two-factor-challenge', 1440, 900, 200, ['Kode tidak valid']);
await submit('form', { code: totp(qaTotpSecret) });
await setViewport(1366, 768);
await record('07-staff-panel-after-otp', '/admin', 1366, 768, 200, ['Dasbor']);

await clearSession();
await setViewport(360, 800);
await navigate('/admin/login');
await record('08-filament-login-guarded', '/admin/login', 360, 800, 200, ['Masuk']);

await clearSession();
await setViewport(390, 844);
await navigate('/login');
await record('09-customer-portal-login', '/login', 390, 844, 200, ['Masuk ke akun']);
await submit('form', { email: process.env.QA_CUSTOMER_EMAIL ?? 'customer@example.com', password: qaPassword });
await setViewport(768, 1024);
await record('10-customer-dashboard', '/dashboard', 768, 1024, 200, ['Dashboard']);

await navigate('/dashboard/proyek');
const filePage = await evaluate(`(() => {
    const link = Array.from(document.querySelectorAll('a[href]'))
        .find((item) => new URL(item.href).pathname.match(/^\\/dashboard\\/proyek\\/\\d+$/));
    return link ? new URL(link.href).pathname + '/file' : null;
})()`);
if (!filePage) throw new Error('Project demo untuk QA upload tidak ditemukan.');
await navigate(filePage);
await setViewport(1024, 768);
await record('11-private-upload-form', filePage, 1024, 768, 200, ['Unggah dokumen']);
await setFile('input[type=file]', fixturePath);
await submit('form[enctype="multipart/form-data"]', { category: 'dokumen_awal' });
await setViewport(1440, 900);
await record('12-infected-file-error', filePage, 1440, 900, 200, ['terdeteksi berbahaya']);

await setViewport(1024, 768);
await navigate('/admin/projects', 403);
await record('13-error-403', '/admin/projects', 1024, 768, 403, ['403']);

await clearSession();
await setViewport(390, 844);
await navigate('/halaman-tidak-ada', 404);
await record('14-error-404', '/halaman-tidak-ada', 390, 844, 404, ['404']);

await clearSession();
await navigate('/login');
await submit('form', { email: process.env.QA_RATE_EMAIL ?? 'qa-rate@example.com', password: qaPassword });
for (let attempt = 0; attempt < 5; attempt++) {
    await submit('form', { code: '000000' });
}
allowedStatuses = [429];
documentStatus = null;
const rateLoaded = cdp.waitFor('Page.loadEventFired');
await evaluate("document.querySelector('form').submit()");
await rateLoaded;
await delay(900);
if (documentStatus !== 429) throw new Error(`Rate limit mengembalikan ${documentStatus}, diharapkan 429.`);
await setViewport(360, 800);
await record('15-error-429', '/two-factor-challenge', 360, 800, 429, ['429']);

report.summary = {
    states_checked: report.states.length,
    horizontal_overflow: 0,
    console_errors: report.console_errors.length,
    network_errors: report.network_errors.length,
    asset_errors: 0,
    result: 'passed',
    note: 'Error infected menggunakan fake scanner non-production; automated test membuktikan infected dan scanner failure.',
};
await writeFile(
    path.join(outputDirectory, 'visual-qa-report.json'),
    JSON.stringify(report, null, 2) + '\n',
);
await cdp.send('Page.close');
console.log(JSON.stringify(report.summary));
