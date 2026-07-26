import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8003';
const debuggerUrl = process.env.QA_DEBUGGER_URL ?? 'http://127.0.0.1:9225';
const outputDirectory = path.resolve('docs/screenshots');

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
            const timer = setTimeout(() => reject(new Error('Timeout menunggu '+method)), timeout);
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
const pageResponse = await fetch(debuggerUrl+'/json/new?about:blank', { method: 'PUT' });
if (!pageResponse.ok) throw new Error('Chrome debugger tidak menyediakan target baru.');
const target = await pageResponse.json();
const cdp = new CdpClient(target.webSocketDebuggerUrl);
await cdp.connect();
await Promise.all([cdp.send('Page.enable'), cdp.send('Runtime.enable'), cdp.send('Network.enable'), cdp.send('Log.enable')]);

let activePage = 'initial';
let consoleErrors = [];
let networkErrors = [];
let allowedNetworkStatuses = [];
let documentStatus = null;
cdp.on('Runtime.exceptionThrown', (event) => consoleErrors.push({ page: activePage, text: event.exceptionDetails?.text ?? 'Runtime exception' }));
cdp.on('Log.entryAdded', (event) => {
    const expectedHttpError = allowedNetworkStatuses.some(status => event.entry?.text?.includes('status of '+status));
    if (event.entry?.level === 'error' && !expectedHttpError) consoleErrors.push({ page: activePage, text: event.entry.text });
});
cdp.on('Network.responseReceived', (event) => {
    if (event.type === 'Document' && event.response.url.startsWith(baseUrl)) documentStatus = event.response.status;
    if (event.response.status >= 400 && !allowedNetworkStatuses.includes(event.response.status)) {
        networkErrors.push({ page: activePage, status: event.response.status, url: event.response.url });
    }
});

const report = { generated_at: new Date().toISOString(), base_url: baseUrl, pages: [], console_errors: [], network_errors: [] };

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
    return result.result.value;
}

async function navigate(url) {
    const loaded = cdp.waitFor('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: baseUrl+url });
    await loaded;
    await delay(900);
    const scrollHeight = await evaluate('document.documentElement.scrollHeight');
    for (let position = 700; position < scrollHeight; position += 700) {
        await evaluate(`window.scrollTo(0, ${position})`);
        await delay(60);
    }
    await evaluate('window.scrollTo(0, 0)');
    await delay(180);
}

async function recordCurrentPage(name, url, width, height) {
    const dimensions = await evaluate(`({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        title: document.title,
        h1Count: document.querySelectorAll('h1').length,
        brokenImages: Array.from(document.images).filter(image => image.complete && image.naturalWidth === 0).map(image => image.currentSrc || image.src)
    })`);
    if (dimensions.scrollWidth > dimensions.clientWidth) {
        const offenders = await evaluate(`Array.from(document.querySelectorAll('*')).map(element => { const rect = element.getBoundingClientRect(); return { tag: element.tagName, className: String(element.className).slice(0, 120), left: Math.round(rect.left), right: Math.round(rect.right), width: Math.round(rect.width) }; }).filter(item => item.right > document.documentElement.clientWidth + 1 || item.left < -1).slice(0, 12)`);
        throw new Error(name+' memiliki horizontal overflow: '+JSON.stringify(offenders));
    }
    if (dimensions.h1Count !== 1) throw new Error(name+' memiliki '+dimensions.h1Count+' elemen h1.');
    if (dimensions.brokenImages.length) throw new Error(name+' memiliki broken image: '+JSON.stringify(dimensions.brokenImages));

    const screenshot = await cdp.send('Page.captureScreenshot', { format: 'png', fromSurface: true, captureBeyondViewport: false });
    const filename = 'tahap-3-'+name+'.png';
    await writeFile(path.join(outputDirectory, filename), Buffer.from(screenshot.data, 'base64'));
    report.pages.push({ name, url, viewport: width+'x'+height, title: dimensions.title, status: documentStatus, screenshot: 'docs/screenshots/'+filename, horizontal_overflow: false, broken_images: [] });
    report.console_errors.push(...consoleErrors);
    report.network_errors.push(...networkErrors);
}

async function capture(name, url, width = 1366, height = 768, expectedStatus = 200) {
    activePage = name;
    consoleErrors = [];
    networkErrors = [];
    allowedNetworkStatuses = expectedStatus >= 400 ? [expectedStatus] : [];
    documentStatus = null;
    await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
    await navigate(url);
    if (documentStatus !== expectedStatus) throw new Error(name+' mengembalikan status '+documentStatus+', diharapkan '+expectedStatus+'.');
    await recordCurrentPage(name, url, width, height);
    allowedNetworkStatuses = [];
}

await mkdir(outputDirectory, { recursive: true });

try {
    await capture('home-desktop', '/', 1440, 900);
    await capture('home-mobile', '/', 390, 844);
    activePage = 'mobile-menu-open';
    consoleErrors = [];
    networkErrors = [];
    const menuState = await evaluate(`document.querySelector('[aria-controls=mobile-navigation]').click(); new Promise(resolve => setTimeout(() => resolve({
        visible: getComputedStyle(document.querySelector('#mobile-navigation')).display !== 'none',
        focusInside: document.querySelector('#mobile-navigation').contains(document.activeElement),
        bodyLocked: document.body.style.overflow === 'hidden',
        expanded: document.querySelector('[aria-controls=mobile-navigation]').getAttribute('aria-expanded')
    }), 300))`);
    if (!menuState.visible || !menuState.focusInside || !menuState.bodyLocked || menuState.expanded !== 'true') {
        throw new Error('State menu mobile tidak accessible: '+JSON.stringify(menuState));
    }
    await recordCurrentPage('mobile-menu-open', '/', 390, 844);
    const menuClosed = await evaluate(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' })); new Promise(resolve => setTimeout(() => resolve({
        hidden: getComputedStyle(document.querySelector('#mobile-navigation')).display === 'none',
        focusReturned: document.activeElement === document.querySelector('[aria-controls=mobile-navigation]'),
        bodyUnlocked: document.body.style.overflow === ''
    }), 300))`);
    if (!menuClosed.hidden || !menuClosed.focusReturned || !menuClosed.bodyUnlocked) {
        throw new Error('Menu mobile tidak menutup dengan benar: '+JSON.stringify(menuClosed));
    }

    await capture('home-360', '/', 360, 800);
    await capture('home-tablet-768', '/', 768, 1024);
    await capture('home-tablet-1024', '/', 1024, 768);
    await capture('home-1366', '/', 1366, 768);

    await capture('services', '/layanan');
    await capture('service-detail', '/layanan/konsultasi-skripsi-penelitian');
    await capture('portfolio', '/portofolio');
    await capture('portfolio-detail', '/portofolio/dashboard-monitoring-penjualan');
    await capture('articles', '/artikel');
    await capture('article-detail', '/artikel/menyusun-rumusan-masalah');
    await capture('faq', '/faq');
    const faqState = await evaluate(`document.querySelector('[aria-controls^=faq-panel-]').click(); new Promise(resolve => setTimeout(() => {
        const button = document.querySelector('[aria-controls^=faq-panel-]');
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        resolve({ expanded: button.getAttribute('aria-expanded'), visible: getComputedStyle(panel).display !== 'none' });
    }, 300))`);
    if (faqState.expanded !== 'true' || !faqState.visible) throw new Error('Accordion FAQ tidak dapat dibuka secara accessible.');
    if (consoleErrors.length || networkErrors.length) throw new Error('Error muncul saat interaksi accordion FAQ.');
    await capture('contact', '/kontak');
    await capture('privacy', '/kebijakan-privasi');
    await capture('terms', '/syarat-dan-ketentuan');
    await capture('empty-search', '/layanan?q=qa-tidak-ada-hasil');
    await capture('content-404', '/layanan/qa-konten-tidak-ada', 1366, 768, 404);

    activePage = 'validation-error';
    consoleErrors = [];
    networkErrors = [];
    documentStatus = null;
    await navigate('/kontak');
    const validationLoaded = cdp.waitFor('Page.loadEventFired');
    await evaluate(`(() => {
        const form = document.querySelector('form[action$=konsultasi]');
        form.querySelector('[name=name]').value = '';
        form.querySelector('[name=email]').value = 'qa-invalid@example.test';
        form.querySelector('[name=phone]').value = '081234567890';
        form.querySelector('[name=service_id]').value = form.querySelector('[name=service_id] option:nth-child(2)').value;
        form.querySelector('[name=project_title]').value = 'QA validasi';
        form.querySelector('[name=description]').value = 'pendek';
        form.submit();
        return true;
    })()`);
    await validationLoaded;
    await delay(900);
    if (!await evaluate(`document.body.innerText.includes('Periksa kembali formulir')`)) throw new Error('State validation error tidak tampil.');
    if (!await evaluate(`document.activeElement?.matches('[data-error-summary]')`)) throw new Error('Fokus tidak berpindah ke ringkasan error.');
    await recordCurrentPage('validation-error', '/kontak', 1366, 768);

    await navigate('/kontak');
    activePage = 'success-state';
    consoleErrors = [];
    networkErrors = [];
    const successLoaded = cdp.waitFor('Page.loadEventFired');
    const qaEmail = 'qa-visual-'+Date.now()+'@example.test';
    await evaluate(`(() => {
        const form = document.querySelector('form[action$=konsultasi]');
        form.querySelector('[name=name]').value = 'QA Visual';
        form.querySelector('[name=email]').value = '${qaEmail}';
        form.querySelector('[name=phone]').value = '081234567890';
        form.querySelector('[name=service_id]').value = form.querySelector('[name=service_id] option:nth-child(2)').value;
        form.querySelector('[name=project_title]').value = 'QA visual Tahap 3';
        form.querySelector('[name=description]').value = 'Pengujian visual aman untuk memverifikasi state sukses formulir konsultasi Tahap 3.';
        form.querySelector('[name=privacy]').checked = true;
        form.querySelector('[name=academic_integrity]').checked = true;
        form.submit();
        return true;
    })()`);
    await successLoaded;
    await delay(900);
    const successState = await evaluate(`({ visible: document.body.innerText.includes('Permintaan konsultasi Anda telah diterima'), url: location.href, summary: document.body.innerText.slice(0, 600) })`);
    if (!successState.visible) throw new Error('State sukses konsultasi tidak tampil: '+JSON.stringify(successState));
    await recordCurrentPage('success-state', '/kontak', 1366, 768);

    if (report.console_errors.length || report.network_errors.length) throw new Error('Console atau network error ditemukan selama visual QA.');
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = error.message;
    process.exitCode = 1;
} finally {
    await writeFile(path.join(outputDirectory, 'visual-qa-report.json'), JSON.stringify(report, null, 2));
    cdp.socket.close();
}

console.log(JSON.stringify(report, null, 2));
