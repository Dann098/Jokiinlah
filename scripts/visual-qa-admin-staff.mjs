import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8004';
const debuggerUrl = process.env.QA_DEBUGGER_URL ?? 'http://127.0.0.1:9226';
const outputDirectory = path.resolve('docs/screenshots/tahap-5');

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
]);
await cdp.send('Network.clearBrowserCookies');

let activeState = 'initial';
let documentStatus = null;
let allowedStatuses = [];
let consoleErrors = [];
let networkErrors = [];

cdp.on('Runtime.exceptionThrown', (event) => {
    consoleErrors.push({ state: activeState, text: event.exceptionDetails?.text ?? 'Runtime exception' });
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
    scope: 'Tahap 5 Panel Admin dan Staff',
    required_states: 54,
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
    await delay(1000);
    if (documentStatus !== expectedStatus) {
        throw new Error(`${url} mengembalikan ${documentStatus}, diharapkan ${expectedStatus}.`);
    }
}

async function submit(selector, setup = '') {
    const loaded = cdp.waitFor('Page.loadEventFired');
    await evaluate(`(() => {
        const form = document.querySelector(${JSON.stringify(selector)});
        if (!form) throw new Error('Form tidak ditemukan: ${selector}');
        ${setup}
        form.submit();
        return true;
    })()`);
    await loaded;
    await delay(1000);
}

async function clickByText(text, selector = 'button, a', wait = 700) {
    const clicked = await evaluate(`(() => {
        const target = Array.from(document.querySelectorAll(${JSON.stringify(selector)}))
            .find((item) => item.offsetParent !== null && item.innerText.trim().includes(${JSON.stringify(text)}));
        if (!target) return false;
        target.click();
        return true;
    })()`);
    if (!clicked) throw new Error(`Kontrol "${text}" tidak ditemukan pada ${activeState}.`);
    await delay(wait);
}

async function clickModalPrimary(wait = 1000) {
    const result = await evaluate(`(() => {
        const modal = document.querySelector('[role=dialog], .fi-modal-window');
        const buttons = modal ? Array.from(modal.querySelectorAll('.fi-modal-footer-actions button')).filter((item) => item.offsetParent !== null) : [];
        const target = buttons.find((item) => /buat lainnya/i.test(item.innerText.trim()))
            ?? buttons.find((item) => !/batal|cancel|tutup|close/i.test(item.innerText.trim()) && item.innerText.trim());
        if (!target) return { clicked: false, buttons: buttons.map((item) => item.innerText.trim()) };
        target.click();
        return { clicked: true, buttons: buttons.map((item) => item.innerText.trim()) };
    })()`);
    if (!result.clicked) throw new Error(`Tombol utama modal tidak ditemukan: ${JSON.stringify(result.buttons)}`);
    await delay(wait);
}

async function findRecordPath(prefix, needle = '') {
    return evaluate(`(() => {
        const links = Array.from(document.querySelectorAll('a[href]'));
        const match = links.find((link) =>
            new URL(link.href).pathname.match(new RegExp('^${prefix.replaceAll('/', '\\/')}\\\\/[^/]+$'))
            && link.closest('tr, article, section, li, div')?.innerText.includes(${JSON.stringify(needle)})
        ) ?? links.find((link) => new URL(link.href).pathname.match(new RegExp('^${prefix.replaceAll('/', '\\/')}\\\\/[^/]+$')));
        return match ? new URL(match.href).pathname : null;
    })()`);
}

async function record(name, url, width, height, expectedStatus = 200, assertions = {}) {
    const diagnostics = await evaluate(`(() => {
        const visible = (element) => element.offsetParent !== null;
        const ids = Array.from(document.querySelectorAll('[id]')).map((element) => element.id);
        const duplicateIds = ids.filter((id, index) => ids.indexOf(id) !== index);
        const controls = Array.from(document.querySelectorAll('input:not([type=hidden]), select, textarea')).filter(visible);
        const unlabeledControls = controls.filter((control) => {
            if (control.getAttribute('aria-label') || control.getAttribute('aria-labelledby') || control.getAttribute('placeholder')) return false;
            if (control.closest('label')) return false;
            return !control.id || !document.querySelector('label[for="'+CSS.escape(control.id)+'"]');
        }).map((control) => ({
            control: control.id || control.name || control.type,
            html: control.outerHTML.slice(0, 300),
            context: control.parentElement?.parentElement?.innerText?.trim().slice(0, 160) ?? '',
        }));
        return {
            title: document.title,
            h1Count: document.querySelectorAll('h1').length,
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
            duplicateIds: [...new Set(duplicateIds)],
            unlabeledControls,
            brokenImages: Array.from(document.images)
                .filter((image) => image.complete && image.naturalWidth === 0)
                .map((image) => image.currentSrc || image.src),
            linkPaths: Array.from(document.querySelectorAll('a[href]')).map((link) => new URL(link.href).pathname),
            validationErrorCount: document.querySelectorAll('.fi-fo-field-wrp-error-message').length,
            bodyText: document.body.innerText,
        };
    })()`);

    if (diagnostics.scrollWidth > diagnostics.clientWidth + 1) throw new Error(`${name}: horizontal overflow.`);
    if (diagnostics.h1Count !== 1) throw new Error(`${name}: jumlah h1 ${diagnostics.h1Count}.`);
    if (diagnostics.brokenImages.length) throw new Error(`${name}: broken image ${diagnostics.brokenImages.join(', ')}`);
    if (diagnostics.duplicateIds.length) throw new Error(`${name}: ID ganda ${diagnostics.duplicateIds.join(', ')}`);
    if (diagnostics.unlabeledControls.length) throw new Error(`${name}: kontrol tanpa label ${JSON.stringify(diagnostics.unlabeledControls)}`);
    for (const expected of assertions.includes ?? []) {
        if (!diagnostics.bodyText.includes(expected)) throw new Error(`${name}: teks "${expected}" tidak ditemukan.`);
    }
    for (const forbidden of assertions.excludes ?? []) {
        if (diagnostics.bodyText.includes(forbidden)) throw new Error(`${name}: teks terlarang "${forbidden}" ditemukan.`);
    }
    for (const forbiddenPath of assertions.excludedPaths ?? []) {
        if (diagnostics.linkPaths.includes(forbiddenPath)) throw new Error(`${name}: link terlarang "${forbiddenPath}" ditemukan.`);
    }
    if ((assertions.minimumValidationErrors ?? 0) > diagnostics.validationErrorCount) {
        throw new Error(`${name}: hanya ${diagnostics.validationErrorCount} pesan validasi, minimal ${assertions.minimumValidationErrors}.`);
    }
    if (consoleErrors.length || networkErrors.length) {
        throw new Error(`${name}: console/network error ${JSON.stringify({ consoleErrors, networkErrors })}`);
    }

    const screenshot = await cdp.send('Page.captureScreenshot', {
        format: 'png',
        fromSurface: true,
        captureBeyondViewport: false,
    });
    const filename = `${name}.png`;
    await writeFile(path.join(outputDirectory, filename), Buffer.from(screenshot.data, 'base64'));
    report.states.push({
        number: report.states.length + 1,
        name,
        url,
        viewport: `${width}x${height}`,
        status: expectedStatus,
        title: diagnostics.title,
        screenshot: `docs/screenshots/tahap-5/${filename}`,
        horizontal_overflow: false,
        broken_images: [],
        duplicate_ids: [],
        unlabeled_controls: [],
        assertions,
    });
    report.console_errors.push(...consoleErrors);
    report.network_errors.push(...networkErrors);
    consoleErrors = [];
    networkErrors = [];
    allowedStatuses = [];
}

async function capture(name, url, width = 1366, height = 768, expectedStatus = 200, assertions = {}) {
    activeState = name;
    consoleErrors = [];
    networkErrors = [];
    await setViewport(width, height);
    await navigate(url, expectedStatus);
    await record(name, url, width, height, expectedStatus, assertions);
}

async function captureCurrent(name, width = 1366, height = 768, assertions = {}) {
    activeState = name;
    consoleErrors = [];
    networkErrors = [];
    await setViewport(width, height);
    await delay(400);
    await record(name, await evaluate('location.pathname + location.search'), width, height, 200, assertions);
}

async function captureRelation(name, url, assertions = {}, tabLabel = null) {
    activeState = name;
    consoleErrors = [];
    networkErrors = [];
    await setViewport(1366, 768);
    await navigate(url.split('?')[0]);
    await delay(500);
    await evaluate(`window.scrollTo(0, Math.max(document.body.scrollHeight, document.documentElement.scrollHeight))`);
    await delay(900);
    if (tabLabel) {
        await clickByText(tabLabel, 'button', 900);
    }
    await evaluate(`window.scrollTo(0, Math.max(document.body.scrollHeight, document.documentElement.scrollHeight))`);
    await delay(1500);
    await record(name, url, 1366, 768, 200, assertions);
}

async function login(email) {
    await navigate('/login');
    await submit('form[action$="/login"]', `
        form.querySelector('[name=email]').value = ${JSON.stringify(email)};
        form.querySelector('[name=password]').value = 'Password123!';
    `);
    if (!await evaluate(`location.pathname === '/admin'`)) throw new Error(`Login gagal untuk ${email}.`);
}

async function logout() {
    await submit('form[action$="/admin/logout"]');
}

await mkdir(outputDirectory, { recursive: true });

try {
    await capture('01-admin-login', '/admin/login', 390, 844, 200, { includes: ['Masuk'] });
    await login('admin@example.com');
    await capture('02-admin-dashboard', '/admin', 1440, 900, 200, { includes: ['Konsultasi baru', 'Pembayaran terbuka'] });
    await capture('03-admin-navigation', '/admin', 1366, 768, 200, { includes: ['Operasional', 'Pengguna', 'Konten Publik', 'Sistem'] });

    await navigate('/admin');
    await evaluate(`(() => {
        const input = document.querySelector('.fi-global-search input[type=search]');
        input.value = 'Portal';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    })()`);
    await delay(1000);
    await captureCurrent('04-admin-global-search', 1366, 768, { includes: ['Portal'] });

    await capture('05-customer-list', '/admin/customer', 1366, 768, 200, { includes: ['Customer Demo'] });
    const customerPath = await findRecordPath('/admin/customer', 'Customer Demo');
    if (!customerPath) throw new Error('Detail customer tidak ditemukan.');
    await capture('06-customer-detail', customerPath, 1024, 768, 200, { excludes: ['password'] });

    await capture('07-staff-list', '/admin/staff', 1366, 768, 200, { includes: ['Staff Akademik'] });
    await capture('08-staff-create', '/admin/staff/create', 1024, 768, 200, { includes: ['Role staff ditentukan server', 'Password awal acak'] });

    await capture('09-consultation-list', '/admin/consultations', 1366, 768, 200, { includes: ['CNS-20260722-0003'] });
    const consultationPath = await findRecordPath('/admin/consultations', 'CNS-20260722-0003');
    if (!consultationPath) throw new Error('Detail konsultasi siap konversi tidak ditemukan.');
    await capture('10-consultation-detail', consultationPath, 1366, 768, 200, { includes: ['Review Proposal Lanjutan'] });
    await clickByText('Konversi ke proyek');
    await captureCurrent('11-consultation-conversion', 1024, 768, { includes: ['Judul proyek', 'Staff', 'Deadline'] });

    await capture('12-project-list', '/admin/projects', 1366, 768, 200, { includes: ['PRJ-20260722-0001'] });
    const primaryProjectPath = await findRecordPath('/admin/projects', 'PRJ-20260722-0001');
    const unassignedProjectPath = await findRecordPath('/admin/projects', 'PRJ-20260722-0008');
    if (!primaryProjectPath || !unassignedProjectPath) throw new Error('Fixture proyek admin tidak lengkap.');
    await capture('13-project-detail', primaryProjectPath, 1440, 900, 200, { includes: ['Ringkasan proyek', 'Informasi admin'] });

    await navigate(primaryProjectPath);
    await clickByText('Ubah status');
    await captureCurrent('14-project-status-action', 1024, 768, { includes: ['Status tujuan'] });
    await navigate(primaryProjectPath);
    await clickByText('Ubah progress');
    await captureCurrent('15-project-progress-action', 1024, 768, { includes: ['Progress (%)'] });
    await navigate(primaryProjectPath);
    await clickByText('Atur staff');
    await captureCurrent('16-project-assignment-action', 1024, 768, { includes: ['Staff aktif'] });

    await captureRelation('17-milestone-relation', `${primaryProjectPath}?relation=0`, { includes: ['Milestone'] });
    await captureRelation('18-file-relation', `${primaryProjectPath}?relation=1`, { includes: ['Berkas Privat', 'Unggah berkas'] }, 'Berkas Privat');
    await clickByText('Unggah berkas', 'button', 1200);
    await clickModalPrimary(1200);
    await captureCurrent('19-upload-validation-error', 1024, 768, { includes: ['Unggah berkas privat'], minimumValidationErrors: 2 });
    await captureRelation('20-file-version-history', `${primaryProjectPath}?relation=1`, { includes: ['Versi'] }, 'Berkas Privat');
    await captureRelation('21-revision-relation', `${primaryProjectPath}?relation=2`, { includes: ['Revisi Customer'] }, 'Revisi Customer');
    await captureRelation('22-reminder-relation', `${primaryProjectPath}?relation=3`, { includes: ['Pengingat'] }, 'Pengingat');
    await captureRelation('23-appointment-relation', `${primaryProjectPath}?relation=4`, { includes: ['Jadwal'] }, 'Jadwal');
    await navigate(primaryProjectPath);
    await clickByText('Pembayaran');
    await captureCurrent('24-payment-status', 1024, 768, { includes: ['Status pembayaran manual'] });

    await capture('25-service-resource', '/admin/services', 1366, 768, 200, { includes: ['Layanan'] });
    await capture('26-portfolio-resource', '/admin/portfolios', 1366, 768, 200, { includes: ['Portofolio'] });
    await capture('27-article-resource', '/admin/articles', 1366, 768, 200, { includes: ['Artikel'] });
    await capture('28-testimonial-resource', '/admin/testimonials', 1366, 768, 200, { includes: ['Testimoni'] });
    await capture('29-faq-resource', '/admin/faqs', 1366, 768, 200, { includes: ['FAQ'] });
    await capture('30-site-setting', '/admin/site-settings', 1366, 768, 200, { includes: ['Pengaturan Situs'] });
    await capture('31-activity-log-read-only', '/admin/activity-logs', 1366, 768, 200, { excludes: ['Buat', 'Hapus massal'] });

    await navigate('/admin');
    const notificationOpened = await evaluate(`(() => {
        const button = Array.from(document.querySelectorAll('button')).find((item) =>
            /notifikasi|notification/i.test(item.getAttribute('aria-label') ?? item.title ?? '')
        );
        if (!button) return false;
        button.click();
        return true;
    })()`);
    if (!notificationOpened) throw new Error('Panel notifikasi tidak ditemukan.');
    await captureCurrent('32-notification-panel', 1024, 768);

    await navigate('/admin/projects');
    await evaluate(`(() => {
        const input = document.querySelector('main .fi-ta-search-field input[type=search]');
        input.value = 'DATA-YANG-TIDAK-ADA-999';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    })()`);
    await delay(1000);
    await captureCurrent('33-empty-state', 1024, 768, { includes: ['Belum ada proyek'] });
    await navigate('/admin/staff/create');
    await evaluate(`document.querySelector('main form').noValidate = true`);
    await clickByText('Buat', 'button', 1000);
    await captureCurrent('34-validation-error', 1024, 768, { minimumValidationErrors: 2 });
    await setViewport(390, 844);
    await navigate('/admin');
    await evaluate(`document.querySelector('[aria-controls="fi-main-sidebar"]')?.click()`);
    await delay(400);
    await captureCurrent('35-mobile-navigation', 390, 844, { includes: ['Operasional'] });
    await capture('36-tablet-layout', '/admin/projects', 768, 1024, 200, { includes: ['Proyek'] });

    await logout();
    await capture('37-staff-login', '/admin/login', 390, 844, 200, { includes: ['Masuk'] });
    await login('staff@example.com');
    await capture('38-staff-dashboard', '/admin', 1440, 900, 200, {
        includes: ['Proyek aktif'],
        excludes: ['Konsultasi baru', 'Pembayaran terbuka'],
    });
    await capture('39-staff-assigned-project-list', '/admin/projects', 1366, 768, 200, {
        includes: ['PRJ-20260722-0002'],
        excludes: ['PRJ-20260722-0008', 'Pembayaran'],
    });
    const staffProjectPath = await findRecordPath('/admin/projects', 'PRJ-20260722-0002');
    if (!staffProjectPath) throw new Error('Proyek assigned staff tidak ditemukan.');
    await capture('40-staff-assigned-project-detail', staffProjectPath, 1366, 768, 200, {
        includes: ['Ringkasan proyek'],
        excludes: ['Informasi admin', 'Pembayaran'],
    });
    await capture('41-staff-unassigned-project-denied', unassignedProjectPath, 1366, 768, 404);
    await capture('42-staff-consultation-menu-hidden', '/admin', 1366, 768, 200, { excludedPaths: ['/admin/consultations'] });
    await capture('43-staff-consultation-url-denied', '/admin/consultations', 1366, 768, 403);
    await capture('44-staff-payment-hidden', staffProjectPath, 1024, 768, 200, { excludes: ['Pembayaran', 'Catatan pembayaran'] });
    await capture('45-staff-customer-resource-denied', '/admin/customer', 1366, 768, 403);
    await capture('46-staff-resource-denied', '/admin/staff', 1366, 768, 403);
    await captureRelation('47-staff-assigned-milestone', `${staffProjectPath}?relation=0`, { includes: ['Milestone'] });
    await captureRelation('48-staff-assigned-file', `${staffProjectPath}?relation=1`, { includes: ['Berkas Privat'] }, 'Berkas Privat');
    await captureRelation('49-staff-assigned-revision', `${staffProjectPath}?relation=2`, { includes: ['Revisi Customer'] }, 'Revisi Customer');
    await captureRelation('50-staff-assigned-reminder', `${staffProjectPath}?relation=3`, { includes: ['Pengingat'] }, 'Pengingat');
    await captureRelation('51-staff-assigned-appointment', `${staffProjectPath}?relation=4`, { includes: ['Jadwal'] }, 'Jadwal');
    await navigate('/admin');
    await evaluate(`(() => {
        const input = document.querySelector('.fi-global-search input[type=search]');
        input.value = 'CNS-20260722-0003';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    })()`);
    await delay(1000);
    await captureCurrent('52-staff-global-search-scoped', 1024, 768, { excludes: ['CNS-20260722-0003'] });
    await capture('53-staff-activity-log-denied', '/admin/activity-logs', 1366, 768, 403);
    await setViewport(360, 800);
    await navigate('/admin');
    await evaluate(`document.querySelector('[aria-controls="fi-main-sidebar"]')?.click()`);
    await delay(400);
    await captureCurrent('54-mobile-staff-panel', 360, 800, { excludes: ['Pembayaran'], excludedPaths: ['/admin/consultations'] });

    if (report.states.length !== report.required_states) {
        throw new Error(`Jumlah state ${report.states.length}, wajib ${report.required_states}.`);
    }
    report.status = 'passed';
} catch (error) {
    report.status = 'failed';
    report.failure = error.message;
    report.console_errors.push(...consoleErrors);
    report.network_errors.push(...networkErrors);
    process.exitCode = 1;
} finally {
    await writeFile(path.join(outputDirectory, 'visual-qa-report.json'), JSON.stringify(report, null, 2));
    cdp.socket.close();
}

console.log(JSON.stringify(report, null, 2));
