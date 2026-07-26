import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8003';
const debuggerUrl = process.env.QA_DEBUGGER_URL ?? 'http://127.0.0.1:9225';
const outputDirectory = path.resolve('docs/screenshots/tahap-4');
const fixturePath = path.resolve('scripts/fixtures/visual-qa-document.pdf');
const foreignProjectId = process.env.QA_FOREIGN_PROJECT_ID ?? '2';

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

let activePage = 'initial';
let consoleErrors = [];
let networkErrors = [];
let allowedNetworkStatuses = [];
let documentStatus = null;

cdp.on('Runtime.exceptionThrown', (event) => {
    consoleErrors.push({ page: activePage, text: event.exceptionDetails?.text ?? 'Runtime exception' });
});
cdp.on('Log.entryAdded', (event) => {
    const expected = allowedNetworkStatuses.some((status) => event.entry?.text?.includes('status of '+status));
    if (event.entry?.level === 'error' && !expected) {
        consoleErrors.push({ page: activePage, text: event.entry.text });
    }
});
cdp.on('Network.responseReceived', (event) => {
    if (event.type === 'Document' && event.response.url.startsWith(baseUrl)) {
        documentStatus = event.response.status;
    }
    if (event.response.status >= 400 && !allowedNetworkStatuses.includes(event.response.status)) {
        networkErrors.push({ page: activePage, status: event.response.status, url: event.response.url });
    }
});

const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl,
    scope: 'Tahap 4 Customer Portal',
    pages: [],
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
    allowedNetworkStatuses = expectedStatus >= 400 ? [expectedStatus] : [];
    documentStatus = null;
    const loaded = cdp.waitFor('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: baseUrl+url });
    await loaded;
    await delay(700);
    if (documentStatus !== expectedStatus) {
        throw new Error(url+' mengembalikan status '+documentStatus+', diharapkan '+expectedStatus+'.');
    }
}

async function submit(selector, setup = '') {
    documentStatus = null;
    const loaded = cdp.waitFor('Page.loadEventFired');
    await evaluate(`(() => {
        const form = document.querySelector(${JSON.stringify(selector)});
        if (!form) throw new Error('Form tidak ditemukan: '+${JSON.stringify(selector)});
        ${setup}
        form.submit();
        return true;
    })()`);
    await loaded;
    await delay(700);
}

async function setFile(selector, filePath) {
    const document = await cdp.send('DOM.getDocument', { depth: -1, pierce: true });
    const result = await cdp.send('DOM.querySelector', {
        nodeId: document.root.nodeId,
        selector,
    });
    if (!result.nodeId) throw new Error('Input file tidak ditemukan: '+selector);
    await cdp.send('DOM.setFileInputFiles', {
        nodeId: result.nodeId,
        files: [filePath],
    });
}

async function record(name, url, width, height, expectedStatus = 200) {
    activePage = name;
    const diagnostics = await evaluate(`(() => {
        const ids = Array.from(document.querySelectorAll('[id]')).map((element) => element.id);
        const duplicateIds = ids.filter((id, index) => ids.indexOf(id) !== index);
        const controls = Array.from(document.querySelectorAll('input:not([type=hidden]), select, textarea'));
        const unlabeledControls = controls.filter((control) => {
            if (control.getAttribute('aria-label') || control.getAttribute('aria-labelledby')) return false;
            return !document.querySelector('label[for="'+CSS.escape(control.id)+'"]') && !control.closest('label');
        }).map((control) => control.id || control.name || control.type);
        return {
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            title: document.title,
            h1Count: document.querySelectorAll('h1').length,
            duplicateIds: [...new Set(duplicateIds)],
            unlabeledControls,
            brokenImages: Array.from(document.images)
                .filter((image) => image.complete && image.naturalWidth === 0)
                .map((image) => image.currentSrc || image.src),
        };
    })()`);

    if (diagnostics.scrollWidth > diagnostics.clientWidth) {
        const offenders = await evaluate(`Array.from(document.querySelectorAll('*')).map((element) => {
            const rect = element.getBoundingClientRect();
            return { tag: element.tagName, className: String(element.className).slice(0, 100), left: Math.round(rect.left), right: Math.round(rect.right) };
        }).filter((item) => item.right > document.documentElement.clientWidth + 1 || item.left < -1).slice(0, 12)`);
        throw new Error(name+' memiliki horizontal overflow: '+JSON.stringify(offenders));
    }
    if (diagnostics.h1Count !== 1) throw new Error(name+' memiliki '+diagnostics.h1Count+' elemen h1.');
    if (diagnostics.brokenImages.length) throw new Error(name+' memiliki broken image.');
    if (diagnostics.duplicateIds.length) throw new Error(name+' memiliki ID ganda: '+diagnostics.duplicateIds.join(', '));
    if (diagnostics.unlabeledControls.length) throw new Error(name+' memiliki kontrol tanpa label: '+diagnostics.unlabeledControls.join(', '));
    if (consoleErrors.length || networkErrors.length) {
        throw new Error(name+' memiliki console/network error: '+JSON.stringify({ consoleErrors, networkErrors }));
    }

    const screenshot = await cdp.send('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: false,
    });
    const filename = name+'.png';
    await writeFile(path.join(outputDirectory, filename), Buffer.from(screenshot.data, 'base64'));
    report.pages.push({
        name,
        url,
        viewport: width+'x'+height,
        title: diagnostics.title,
        status: expectedStatus,
        screenshot: 'docs/screenshots/tahap-4/'+filename,
        horizontal_overflow: false,
        broken_images: [],
        duplicate_ids: [],
        unlabeled_controls: [],
    });
    report.console_errors.push(...consoleErrors);
    report.network_errors.push(...networkErrors);
    consoleErrors = [];
    networkErrors = [];
    allowedNetworkStatuses = [];
}

async function capture(name, url, width = 1366, height = 768, expectedStatus = 200) {
    activePage = name;
    consoleErrors = [];
    networkErrors = [];
    await setViewport(width, height);
    await navigate(url, expectedStatus);
    await record(name, url, width, height, expectedStatus);
}

async function login(email) {
    await navigate('/login');
    await submit('form[action$="/login"]', `
        form.querySelector('[name=email]').value = ${JSON.stringify(email)};
        form.querySelector('[name=password]').value = 'Password123!';
    `);
    if (!await evaluate(`location.pathname.startsWith('/dashboard')`)) {
        throw new Error('Login customer gagal untuk akun demo.');
    }
}

async function logout() {
    await submit('form[action$="/logout"]');
}

await mkdir(outputDirectory, { recursive: true });

try {
    await capture('01-login-customer', '/login', 390, 844);
    await login('customer@example.com');

    await capture('02-dashboard-data', '/dashboard', 1440, 900);
    await capture('03-project-list-desktop', '/dashboard/proyek', 1366, 768);
    await capture('04-project-list-mobile', '/dashboard/proyek', 360, 800);
    await capture('05-project-filter', '/dashboard/proyek?status=waiting_data', 1024, 768);
    await capture('06-project-search', '/dashboard/proyek?q=Portal', 768, 1024);

    const primaryProjectPath = await evaluate(`new URL(Array.from(document.querySelectorAll('a[href*="/dashboard/proyek/"]')).find((link) => link.href.match(/\\/dashboard\\/proyek\\/\\d+$/))?.href).pathname`);
    await capture('07-project-detail', primaryProjectPath, 1440, 900);

    activePage = '08-milestone-timeline';
    consoleErrors = [];
    networkErrors = [];
    await evaluate(`document.querySelector('#milestone').scrollIntoView({ block: 'start' }); new Promise((resolve) => setTimeout(resolve, 400))`);
    await record('08-milestone-timeline', primaryProjectPath+'#milestone', 1440, 900);

    const filePath = primaryProjectPath+'/file';
    activePage = '09-file-list-long-filename';
    consoleErrors = [];
    networkErrors = [];
    await setViewport(1366, 768);
    await navigate(filePath);
    await evaluate(`document.querySelector('#document-list-title').scrollIntoView({ block: 'start' }); new Promise((resolve) => setTimeout(resolve, 300))`);
    await record('09-file-list-long-filename', filePath+'#document-list-title', 1366, 768);

    activePage = '10-upload-validation-error';
    await submit('form[action$="/file"]');
    if (!await evaluate(`document.body.innerText.includes('Periksa kembali formulir')`)) throw new Error('Error upload tidak tampil.');
    await record('10-upload-validation-error', filePath, 1366, 768);

    activePage = '11-upload-file-success';
    consoleErrors = [];
    networkErrors = [];
    await navigate(filePath);
    await setFile('#new-file', fixturePath);
    await submit('form[action$="/file"]', `
        form.querySelector('[name=category]').value = 'data_pendukung';
        form.querySelector('[name=description]').value = 'Dokumen aman dari visual QA Tahap 4.';
    `);
    if (!await evaluate(`document.body.innerText.includes('Berkas baru berhasil diunggah')`)) throw new Error('State sukses upload tidak tampil.');
    await record('11-upload-file-success', filePath, 1366, 768);

    activePage = '12-file-version-list';
    consoleErrors = [];
    networkErrors = [];
    await navigate(filePath);
    await evaluate(`(() => {
        const versions = Array.from(document.querySelectorAll('details')).find((item) => item.innerText.includes('Riwayat versi'));
        versions.open = true;
        versions.scrollIntoView({ block: 'center' });
        return new Promise((resolve) => setTimeout(resolve, 300));
    })()`);
    await record('12-file-version-list', filePath+'#version-history', 1024, 768);

    activePage = '13-upload-version-success';
    consoleErrors = [];
    networkErrors = [];
    const versionFormSelector = 'form[action$="/versi"]';
    const versionFileId = await evaluate(`document.querySelector(${JSON.stringify(versionFormSelector)})?.querySelector('input[type=file]')?.id`);
    await setFile('#'+versionFileId, fixturePath);
    await submit(versionFormSelector, `
        form.querySelector('[name=category]').value = 'revisi';
        form.querySelector('[name=description]').value = 'Versi baru visual QA.';
    `);
    if (!await evaluate(`document.body.innerText.includes('Versi berkas baru berhasil diunggah')`)) throw new Error('State sukses versi tidak tampil.');
    await record('13-upload-version-success', filePath, 1024, 768);

    const revisionPath = primaryProjectPath+'/revisi';
    await capture('14-revision-list', revisionPath, 1366, 768);

    activePage = '15-revision-form';
    consoleErrors = [];
    networkErrors = [];
    await evaluate(`document.querySelector('#form-revisi').scrollIntoView({ block: 'start' }); new Promise((resolve) => setTimeout(resolve, 300))`);
    await record('15-revision-form', revisionPath+'#form-revisi', 1366, 768);

    activePage = '16-revision-validation-error';
    await submit('form[action$="/revisi"]');
    if (!await evaluate(`document.body.innerText.includes('Periksa kembali formulir')`)) throw new Error('Error revisi tidak tampil.');
    await record('16-revision-validation-error', revisionPath, 1366, 768);

    await capture('17-reminder-list', '/dashboard/pengingat', 768, 1024);
    await capture('18-appointment-list', '/dashboard/jadwal', 1024, 768);
    await capture('19-profile', '/dashboard/profil', 1366, 768);

    activePage = '20-profile-validation-error';
    await submit('form[action$="/profil"]', `
        form.querySelector('[name=name]').value = '';
        form.querySelector('[name=phone]').value = '123';
    `);
    if (!await evaluate(`document.body.innerText.includes('Periksa kembali formulir')`)) throw new Error('Error profil tidak tampil.');
    await record('20-profile-validation-error', '/dashboard/profil', 1366, 768);

    activePage = '21-change-password';
    consoleErrors = [];
    networkErrors = [];
    await navigate('/dashboard/profil');
    await evaluate(`document.querySelector('form[action$="/password"]').scrollIntoView({ block: 'start' }); new Promise((resolve) => setTimeout(resolve, 300))`);
    await record('21-change-password', '/dashboard/profil#password', 1024, 768);

    activePage = '22-mobile-menu-open';
    consoleErrors = [];
    networkErrors = [];
    await setViewport(390, 844);
    await navigate('/dashboard');
    const menuState = await evaluate(`document.querySelector('[aria-controls=customer-mobile-menu]').click(); new Promise((resolve) => setTimeout(() => resolve({
        visible: getComputedStyle(document.querySelector('#customer-mobile-menu')).display !== 'none',
        focusInside: document.querySelector('#customer-mobile-menu').contains(document.activeElement),
        bodyLocked: document.body.style.overflow === 'hidden',
        expanded: document.querySelector('[aria-controls=customer-mobile-menu]').getAttribute('aria-expanded')
    }), 300))`);
    if (!menuState.visible || !menuState.focusInside || !menuState.bodyLocked || menuState.expanded !== 'true') {
        throw new Error('Menu mobile portal tidak accessible: '+JSON.stringify(menuState));
    }
    await record('22-mobile-menu-open', '/dashboard', 390, 844);
    await evaluate(`document.querySelector('#customer-mobile-menu button').click()`);

    await capture('23-ownership-403', '/dashboard/proyek/'+foreignProjectId, 1366, 768, 403);
    await capture('24-long-project-title', '/dashboard/proyek?q=PRJ-20260722-0006', 390, 844);
    await capture('25-tablet-dashboard', '/dashboard', 768, 1024);

    await logout();
    await login('dimas@example.com');
    await capture('26-dashboard-empty', '/dashboard', 390, 844);
    await capture('27-empty-project-list', '/dashboard/proyek', 1440, 900);

    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = error.message;
    report.console_errors.push(...consoleErrors);
    report.network_errors.push(...networkErrors);
    process.exitCode = 1;
} finally {
    await writeFile(
        path.join(outputDirectory, 'visual-qa-report.json'),
        JSON.stringify(report, null, 2),
    );
    cdp.socket.close();
}

console.log(JSON.stringify(report, null, 2));
