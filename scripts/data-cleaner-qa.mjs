import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

import Papa from 'papaparse';
import * as XLSX from 'xlsx';

const baseUrl = process.env.QA_BASE_URL ?? 'http://127.0.0.1:8012';
const debuggerUrl = process.env.QA_DEBUGGER_URL ?? 'http://127.0.0.1:9235';
const browserName = process.env.QA_BROWSER_NAME ?? 'Chromium';
const outputDirectory = path.resolve('docs/screenshots/data-cleaner');
const fixtureDirectory = await mkdtemp(path.join(os.tmpdir(), 'jokiinlah-data-cleaner-fixtures-'));
const downloadDirectory = await mkdtemp(path.join(os.tmpdir(), 'jokiinlah-data-cleaner-downloads-'));

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

async function createFixtures() {
    const semicolonCsv = [
        ' Nama Lengkap ;Nama Lengkap;Kolom Kosong;Nilai;Formula',
        '  Nadia   Prameswari  ;Nadia;;-1200;=SUM(A1:A2)',
        'Nadia Prameswari;Nadia; ;-1200;=SUM(A1:A2)',
        ' ; ; ; ; ',
        'José Álvarez;Yogyakarta;;00123;@command',
    ].join('\r\n');
    const commaCsv = '\uFEFFNama,Kota\r\nSiti,Surabaya\r\nBudi,Bandung';
    const files = {
        semicolonCsv: path.join(fixtureDirectory, 'data-pelanggan.csv'),
        commaCsv: path.join(fixtureDirectory, 'data-koma.csv'),
        emptyCsv: path.join(fixtureDirectory, 'kosong.csv'),
        largeCsv: path.join(fixtureDirectory, 'terlalu-besar.csv'),
        largeXlsx: path.join(fixtureDirectory, 'terlalu-besar.xlsx'),
        corruptXlsx: path.join(fixtureDirectory, 'rusak.xlsx'),
        oldXls: path.join(fixtureDirectory, 'lama.xls'),
        macroXlsm: path.join(fixtureDirectory, 'macro.xlsm'),
        workbook: path.join(fixtureDirectory, 'data-multi-sheet.xlsx'),
    };

    await Promise.all([
        writeFile(files.semicolonCsv, semicolonCsv, 'utf8'),
        writeFile(files.commaCsv, commaCsv, 'utf8'),
        writeFile(files.emptyCsv, ''),
        writeFile(files.largeCsv, Buffer.alloc(10 * 1024 * 1024 + 1, 65)),
        writeFile(files.largeXlsx, Buffer.alloc(5 * 1024 * 1024 + 1, 65)),
        writeFile(files.corruptXlsx, 'bukan workbook'),
        writeFile(files.oldXls, 'format lama'),
        writeFile(files.macroXlsm, 'macro'),
    ]);

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Sampul']]), 'Sampul');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([
        ['Nama', 'Nilai', 'Kosong'],
        ['Nadia', -3.5, ''],
        ['Nadia', -3.5, ''],
        ['', '', ''],
    ]), 'Pertama');
    const second = XLSX.utils.aoa_to_sheet([
        ['Nama', 'Formula'],
        ['Raka', 'placeholder'],
        ['Siti', '@command'],
    ]);
    second.B2 = { t: 'n', v: 3, f: '1+2' };
    XLSX.utils.book_append_sheet(workbook, second, 'Kedua');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Header Saja']]), 'Kosong');
    await writeFile(files.workbook, XLSX.write(workbook, { bookType: 'xlsx', type: 'buffer' }));

    return files;
}

const fixtures = await createFixtures();
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
await cdp.send('Browser.setDownloadBehavior', { behavior: 'allow', downloadPath: downloadDirectory, eventsEnabled: true });

let documentStatus = null;
const consoleErrors = [];
const networkErrors = [];
const privateRequests = [];
cdp.on('Runtime.exceptionThrown', (event) => consoleErrors.push(event.exceptionDetails?.exception?.description ?? event.exceptionDetails?.text ?? 'Runtime exception'));
cdp.on('Log.entryAdded', (event) => {
    if (event.entry?.level === 'error') consoleErrors.push(event.entry.text);
});
cdp.on('Network.responseReceived', (event) => {
    if (event.type === 'Document' && event.response.url.startsWith(baseUrl)) documentStatus = event.response.status;
    if (event.response.status >= 400) networkErrors.push({ status: event.response.status, url: event.response.url });
});
cdp.on('Network.requestWillBeSent', (event) => {
    if (event.request.method !== 'GET' || ['XHR', 'Fetch', 'Ping'].includes(event.type)) {
        privateRequests.push({ method: event.request.method, type: event.type, url: event.request.url });
    }
});

async function evaluate(expression) {
    const result = await cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
    return result.result.value;
}

async function state(expression) {
    return evaluate(`(async () => {
        const state = Alpine.$data(document.querySelector('[x-data="dataCleaner"]'));
        const result = await eval(${JSON.stringify(expression)});
        return result === undefined ? null : JSON.parse(JSON.stringify(result));
    })()`);
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
    documentStatus = null;
    const loaded = cdp.waitFor('Page.loadEventFired');
    await cdp.send('Page.navigate', { url: `${baseUrl}/fitur-gratis/pembersih-data` });
    await loaded;
    await waitUntil(`Boolean(window.Alpine && document.querySelector('[x-data="dataCleaner"]')?._x_dataStack)`);
    if (documentStatus !== 200) throw new Error(`Halaman cleaner mengembalikan ${documentStatus}.`);
}

async function setFile(filePath) {
    const documentNode = await cdp.send('DOM.getDocument', { depth: -1, pierce: true });
    const input = await cdp.send('DOM.querySelector', { nodeId: documentNode.root.nodeId, selector: '#data-cleaner-file' });
    if (!input.nodeId) throw new Error('Input data cleaner tidak ditemukan.');
    await cdp.send('DOM.setFileInputFiles', { nodeId: input.nodeId, files: [filePath] });
    await delay(50);
    await waitUntil(`!Alpine.$data(document.querySelector('[x-data="dataCleaner"]')).isProcessing`);
}

async function waitForDownload(filename) {
    const filePath = path.join(downloadDirectory, filename);
    const started = Date.now();
    while (Date.now() - started < 10000) {
        try {
            return await readFile(filePath);
        } catch {
            await delay(100);
        }
    }
    throw new Error(`Download ${filename} tidak tersedia.`);
}

async function screenshot(filename, selector = null) {
    const parameters = { format: 'png', fromSurface: true };
    if (selector) {
        const clip = await evaluate(`(() => {
            const rect = document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect();
            return {
                x: 0,
                y: Math.max(0, rect.top + window.scrollY - 48),
                width: window.innerWidth,
                height: window.innerHeight,
                scale: 1,
            };
        })()`);
        parameters.captureBeyondViewport = true;
        parameters.clip = clip;
    }
    const capture = await cdp.send('Page.captureScreenshot', parameters);
    await writeFile(path.join(outputDirectory, filename), Buffer.from(capture.data, 'base64'));
}

const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl,
    browser: browserName,
    viewports: [],
    csv: {},
    xlsx: {},
    validation: {},
    downloads: {},
    privacy: {},
    console_errors: consoleErrors,
    network_errors: networkErrors,
    status: 'failed',
};

await mkdir(outputDirectory, { recursive: true });

try {
    await setViewport(1366, 768);
    await navigate();
    privateRequests.length = 0;

    await setFile(fixtures.commaCsv);
    const commaState = await state(`({ delimiter: state.delimiter, rows: state.originalRows, type: state.fileType, output: state.outputFormat })`);
    if (commaState.delimiter !== ',' || commaState.rows.length !== 2 || commaState.type !== 'csv' || commaState.output !== 'csv') throw new Error(`CSV koma gagal: ${JSON.stringify(commaState)}`);
    report.csv.comma_delimiter = true;

    await state('state.resetData(false)');
    await setFile(fixtures.semicolonCsv);
    const initialCsv = await state(`({
        delimiter: state.delimiter,
        summary: state.initialSummary,
        headers: state.originalHeaders,
        rows: state.originalRows,
        errors: state.parsingErrors,
    })`);
    if (initialCsv.delimiter !== ';' || initialCsv.summary.emptyRowCount !== 1 || initialCsv.summary.duplicateRowCount !== 1 || initialCsv.summary.emptyColumnCount !== 1 || initialCsv.headers[0] !== ' Nama Lengkap ') {
        throw new Error(`Analisis CSV gagal: ${JSON.stringify(initialCsv)}`);
    }

    await state('state.runCleaning()');
    const cleanedCsv = await state(`({
        headers: state.cleanedHeaders,
        rows: state.cleanedRows,
        summary: state.cleaningSummary,
        previewCount: state.previewRows().length,
        canDownload: state.canDownload(),
    })`);
    if (cleanedCsv.rows.length !== 2 || cleanedCsv.headers.join(',') !== 'nama_lengkap,nama_lengkap_2,nilai,formula' || cleanedCsv.summary.emptyRowsRemoved !== 1 || cleanedCsv.summary.duplicatesRemoved !== 1 || cleanedCsv.summary.emptyColumnsRemoved !== 1 || cleanedCsv.previewCount > 100 || !cleanedCsv.canDownload) {
        throw new Error(`Pembersihan CSV gagal: ${JSON.stringify(cleanedCsv)}`);
    }
    report.csv = {
        ...report.csv,
        semicolon_delimiter: true,
        indonesian_characters: cleanedCsv.rows.some((row) => row.includes('José Álvarez')),
        empty_rows_removed: cleanedCsv.summary.emptyRowsRemoved,
        duplicates_removed: cleanedCsv.summary.duplicatesRemoved,
        empty_columns_removed: cleanedCsv.summary.emptyColumnsRemoved,
        spaces_trimmed: cleanedCsv.summary.textValuesTrimmed,
        headers_normalized: cleanedCsv.summary.headersNormalized,
        negative_number_preserved: cleanedCsv.rows.some((row) => row.includes('-1200')),
    };

    await state("state.outputFormat = 'csv'; state.downloadResult(); true");
    const csvDownload = await waitForDownload('data-pelanggan-bersih.csv');
    const csvText = csvDownload.toString('utf8');
    const downloadedCsv = Papa.parse(csvText.replace(/^\uFEFF/u, ''), { header: false }).data;
    if (!csvText.startsWith('\uFEFF') || downloadedCsv.length !== 3 || !downloadedCsv.flat().includes("'=SUM(A1:A2)") || !downloadedCsv.flat().includes('-1200') || !downloadedCsv.flat().includes('José Álvarez')) {
        throw new Error('Isi download CSV tidak aman atau tidak lengkap.');
    }

    await state("state.outputFormat = 'xlsx'; state.downloadResult(); true");
    const convertedXlsx = XLSX.read(await waitForDownload('data-pelanggan-bersih.xlsx'), { type: 'buffer', cellFormula: true });
    const convertedRows = XLSX.utils.sheet_to_json(convertedXlsx.Sheets['Data Bersih'], { header: 1, raw: true });
    if (convertedXlsx.SheetNames.join(',') !== 'Data Bersih' || convertedRows.length !== 3 || convertedRows.flat().some((value) => typeof value === 'string' && value.startsWith('=')) || Object.values(convertedXlsx.Sheets['Data Bersih']).some((cell) => cell?.f)) {
        throw new Error('Konversi CSV ke XLSX gagal atau membawa formula.');
    }
    report.downloads.csv = { filename: 'data-pelanggan-bersih.csv', all_rows: true, utf8_bom: true, formula_safe: true, negative_number_safe: true };
    report.downloads.xlsx_from_csv = { filename: 'data-pelanggan-bersih.xlsx', one_sheet: true, formula_safe: true };

    await state('state.resetData(false)');
    await setFile(fixtures.workbook);
    const workbookState = await state(`({ sheets: state.sheetNames, selected: state.selectedSheet, rows: state.originalRows.length, output: state.outputFormat })`);
    if (workbookState.sheets.join(',') !== 'Sampul,Pertama,Kedua,Kosong' || workbookState.selected !== 'Pertama' || workbookState.output !== 'xlsx') throw new Error(`Workbook gagal: ${JSON.stringify(workbookState)}`);
    await state("(async () => { state.loadExcelSheet('Kedua'); await state.runCleaning(); return true; })()");
    const secondSheet = await state(`({ selected: state.selectedSheet, rows: state.cleanedRows, summary: state.cleaningSummary })`);
    if (secondSheet.selected !== 'Kedua' || secondSheet.rows.length !== 2 || secondSheet.rows[0][1] !== 3) throw new Error(`Pergantian sheet gagal: ${JSON.stringify(secondSheet)}`);
    await state('state.downloadResult(); true');
    const xlsxDownload = XLSX.read(await waitForDownload('data-multi-sheet-bersih.xlsx'), { type: 'buffer', cellFormula: true });
    if (xlsxDownload.SheetNames.join(',') !== 'Data Bersih') throw new Error('Download XLSX membawa sheet lain.');
    report.xlsx = { one_sheet_auto_selected: true, empty_first_sheet_skipped: true, multiple_sheets_listed: true, sheet_switch: true, cached_formula_value: true, negative_number: true };
    report.downloads.xlsx = { filename: 'data-multi-sheet-bersih.xlsx', one_sheet: true, all_rows: true };

    await state(`(() => { try { state.loadExcelSheet('Kosong'); } catch (error) { state.errorMessage = error.message; } return true; })()`);
    const emptySheetError = await state('state.errorMessage');
    if (!/minimal satu baris data|tidak memiliki data/iu.test(emptySheetError)) throw new Error(`Sheet kosong tidak ditolak: ${emptySheetError}`);
    report.xlsx.empty_sheet_rejected = true;

    const invalidCases = [
        [fixtures.emptyCsv, /kosong|tidak memiliki data/iu, 'empty_csv'],
        [fixtures.largeCsv, /10 MB/u, 'oversize_csv'],
        [fixtures.largeXlsx, /5 MB/u, 'oversize_xlsx'],
        [fixtures.corruptXlsx, /gagal|tidak memiliki|minimal satu baris|sheet|bukan workbook|tidak valid/iu, 'corrupt_xlsx'],
        [fixtures.oldXls, /tidak didukung/u, 'xls_rejected'],
        [fixtures.macroXlsm, /tidak didukung/u, 'xlsm_rejected'],
    ];
    for (const [file, pattern, key] of invalidCases) {
        await state('state.resetData(false)');
        await setFile(file);
        const message = await state('state.errorMessage');
        if (!pattern.test(message)) throw new Error(`${key} tidak menghasilkan error yang benar: ${message}`);
        report.validation[key] = true;
    }

    await state('state.resetData(false)');
    const cleared = await state(`({
        file: state.selectedFile,
        workbook: state.workbook,
        sheets: state.sheetNames.length,
        originalRows: state.originalRows.length,
        cleanedRows: state.cleanedRows.length,
        summary: state.cleaningSummary,
    })`);
    if (cleared.file || cleared.workbook || cleared.sheets || cleared.originalRows || cleared.cleanedRows || cleared.summary) throw new Error(`Reset tidak membersihkan memory: ${JSON.stringify(cleared)}`);

    const storage = await evaluate(`(async () => ({
        local: localStorage.length,
        session: sessionStorage.length,
        indexedDb: typeof indexedDB.databases === 'function' ? (await indexedDB.databases()).length : 0,
    }))()`);
    if (storage.local || storage.session || storage.indexedDb || privateRequests.length) throw new Error(`Privasi gagal: ${JSON.stringify({ storage, privateRequests })}`);
    await navigate();
    const restored = await state(`({ file: state.selectedFile, originalRows: state.originalRows.length, cleanedRows: state.cleanedRows.length })`);
    if (restored.file || restored.originalRows || restored.cleanedRows) throw new Error(`Data pulih setelah reload: ${JSON.stringify(restored)}`);
    report.privacy = { no_upload_request: true, no_local_storage: true, no_session_storage: true, no_indexed_db: true, reset_clears_memory: true, reload_restores_nothing: true };

    for (const [width, height] of [[360, 800], [390, 844], [768, 1024], [1024, 768], [1366, 768], [1440, 900]]) {
        await setViewport(width, height);
        await navigate();
        await setFile(fixtures.semicolonCsv);
        await state('state.runCleaning()');
        await waitUntil(`Array.from(document.querySelectorAll('button')).some((button) => button.textContent.includes('Download Hasil') && button.getBoundingClientRect().height > 0)`);
        const layout = await evaluate(`({
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            h1Count: document.querySelectorAll('h1').length,
            inputHeight: document.querySelector('.data-cleaner-dropzone').getBoundingClientRect().height,
            visibleButtons: Array.from(document.querySelectorAll('.data-cleaner-shell button')).filter((button) => button.getBoundingClientRect().height > 0).length,
            buttonMinHeight: Math.min(...Array.from(document.querySelectorAll('.data-cleaner-shell button')).map((button) => button.getBoundingClientRect().height).filter((height) => height > 0)),
            resultReady: Boolean(Alpine.$data(document.querySelector('[x-data="dataCleaner"]')).cleaningSummary),
            downloadRendered: Array.from(document.querySelectorAll('button')).some((button) => button.textContent.includes('Download Hasil') && button.getBoundingClientRect().height > 0),
            resetRendered: Array.from(document.querySelectorAll('button')).some((button) => button.textContent.includes('Reset Data') && button.getBoundingClientRect().height > 0),
            tableOverflowManaged: ['auto', 'scroll'].includes(getComputedStyle(document.querySelector('.data-cleaner-table-wrap')).overflowX),
        })`);
        if (layout.overflow || layout.h1Count !== 1 || layout.inputHeight < 44 || layout.visibleButtons < 4 || layout.buttonMinHeight < 43 || !layout.resultReady || !layout.downloadRendered || !layout.resetRendered || !layout.tableOverflowManaged) throw new Error(`Layout ${width}x${height} gagal: ${JSON.stringify(layout)}`);
        await evaluate(`window.scrollTo({ top: document.querySelector('#result-summary-title').getBoundingClientRect().top + window.scrollY - 110, behavior: 'instant' })`);
        await delay(100);
        const resultHeadingVisible = await evaluate(`(() => {
            const rect = document.querySelector('#result-summary-title').getBoundingClientRect();
            return rect.top >= 80 && rect.top < window.innerHeight;
        })()`);
        if (!resultHeadingVisible) throw new Error(`Ringkasan hasil tidak terlihat pada screenshot ${width}x${height}.`);
        await evaluate(`window.scrollTo({ top: 0, behavior: 'instant' })`);
        await delay(50);
        const filename = `data-cleaner-${width}x${height}.png`;
        await screenshot(filename, '#result-summary-title');
        report.viewports.push({ viewport: `${width}x${height}`, stateful_result: true, result_visible_in_screenshot: true, horizontal_overflow: false, controls_min_44px: true, table_overflow_managed: true, screenshot: `docs/screenshots/data-cleaner/${filename}` });
    }

    if (consoleErrors.length || networkErrors.length) throw new Error(`Console/network error: ${JSON.stringify({ consoleErrors, networkErrors })}`);
    report.status = 'passed';
} catch (error) {
    report.failure = error.stack ?? String(error);
    process.exitCode = 1;
} finally {
    await writeFile(path.join(outputDirectory, 'browser-qa-report.json'), `${JSON.stringify(report, null, 2)}\n`);
    await Promise.all([
        rm(fixtureDirectory, { recursive: true, force: true }),
        rm(downloadDirectory, { recursive: true, force: true }),
    ]);
    console.log(JSON.stringify(report, null, 2));
    cdp.socket.close();
}
