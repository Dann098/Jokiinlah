import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    analyzeData,
    buildOutputFilename,
    cleanData,
    createCsvExport,
    createExcelWorkbook,
    dataCleaner,
    extractExcelSheet,
    inspectXlsxArchive,
    isXlsxArchive,
    normalizeHeader,
    normalizeHeaders,
    parseCsvText,
    removeDuplicates,
    removeEmptyColumns,
    removeEmptyRows,
    sanitizeExportValue,
    trimTextValues,
    validateFile,
} from '../../resources/js/data-cleaner.js';
import * as XLSX from 'xlsx';

test('nama kolom dinormalisasi menjadi snake_case yang aman dan konsisten', () => {
    assert.equal(normalizeHeader(' Nama Lengkap! ', 0), 'nama_lengkap');
    assert.equal(normalizeHeader('Status-Pelanggan', 1), 'status_pelanggan');
    assert.equal(normalizeHeader('', 2), 'kolom_3');
    assert.deepEqual(normalizeHeaders(['Nama', 'Nama', '', 'Tanggal Lahir!']).headers, [
        'nama',
        'nama_2',
        'kolom_3',
        'tanggal_lahir',
    ]);
});

test('perapian spasi hanya mengubah teks dan menjaga angka, nol awal, serta line break', () => {
    const source = [['  Nadia   Prameswari  ', -1200, '00123', 'Baris  satu\nBaris   dua']];
    const result = trimTextValues(source);

    assert.deepEqual(result.rows, [['Nadia Prameswari', -1200, '00123', 'Baris satu\nBaris dua']]);
    assert.equal(result.changedCount, 2);
    assert.deepEqual(source, [['  Nadia   Prameswari  ', -1200, '00123', 'Baris  satu\nBaris   dua']]);
});

test('baris kosong dihapus tanpa menganggap nol dan false sebagai kosong', () => {
    const result = removeEmptyRows([
        ['', '   ', null],
        [0, '', null],
        [false, undefined, ''],
        ['isi', '', ''],
    ]);

    assert.deepEqual(result.rows, [[0, '', null], [false, undefined, ''], ['isi', '', '']]);
    assert.equal(result.removedCount, 1);
});

test('kolom yang seluruh nilainya kosong dihapus dan urutan kolom lain dipertahankan', () => {
    const result = removeEmptyColumns(
        ['nama', 'kosong', 'nilai'],
        [['Nadia', '', 0], ['Raka', '  ', 10]],
    );

    assert.deepEqual(result.headers, ['nama', 'nilai']);
    assert.deepEqual(result.rows, [['Nadia', 0], ['Raka', 10]]);
    assert.deepEqual(result.removedHeaders, ['kosong']);
});

test('duplikat seluruh baris dihapus dengan mempertahankan baris pertama dan urutan', () => {
    const result = removeDuplicates([
        ['Nadia', 'Jakarta'],
        ['Raka', 'Bandung'],
        ['Nadia', 'Jakarta'],
        ['Nadia', 'Bandung'],
    ]);

    assert.deepEqual(result.rows, [
        ['Nadia', 'Jakarta'],
        ['Raka', 'Bandung'],
        ['Nadia', 'Bandung'],
    ]);
    assert.equal(result.removedCount, 1);
});

test('pipeline pembersihan mengikuti urutan dan tidak memutasi data awal', () => {
    const headers = [' Nama ', 'Kolom Kosong', 'Nama'];
    const rows = [
        ['  Nadia  ', '', 'A'],
        ['Nadia', ' ', 'A'],
        ['', '', ''],
    ];
    const originalRows = structuredClone(rows);
    const result = cleanData(headers, rows, {
        normalizeHeaders: true,
        trimText: true,
        removeEmptyRows: true,
        removeEmptyColumns: true,
        removeDuplicates: true,
    });

    assert.deepEqual(result.headers, ['nama', 'nama_2']);
    assert.deepEqual(result.rows, [['Nadia', 'A']]);
    assert.equal(result.summary.emptyRowsRemoved, 1);
    assert.equal(result.summary.emptyColumnsRemoved, 1);
    assert.equal(result.summary.duplicatesRemoved, 1);
    assert.equal(result.summary.textValuesTrimmed, 2);
    assert.equal(result.summary.headersNormalized, 3);
    assert.deepEqual(rows, originalRows);
});

test('analisis awal menghitung masalah tanpa menyatakan data sudah dibersihkan', () => {
    const result = analyzeData(
        [' Nama ', 'kosong'],
        [[' Nadia ', ''], [' Nadia ', ''], ['', '']],
        7,
        2,
    );

    assert.equal(result.rowCount, 3);
    assert.equal(result.columnCount, 2);
    assert.equal(result.emptyRowCount, 1);
    assert.equal(result.duplicateRowCount, 1);
    assert.equal(result.emptyColumnCount, 1);
    assert.equal(result.headersNeedingNormalization, 1);
    assert.equal(result.parsingErrorCount, 7);
    assert.equal(result.sheetCount, 2);
});

test('formula spreadsheet diamankan saat ekspor tetapi angka negatif tetap utuh', () => {
    for (const value of ['=SUM(A1:A2)', '+CMD', '@perintah', '\tformula', '\rformula', '\n=CMD', '  =CMD', '-SUM(A1:A2)']) {
        assert.equal(sanitizeExportValue(value), `'${value}`);
    }

    assert.equal(sanitizeExportValue(-1200), -1200);
    assert.equal(sanitizeExportValue('-1200'), '-1200');
    assert.equal(sanitizeExportValue('-3.5'), '-3.5');
    assert.equal(sanitizeExportValue('teks biasa'), 'teks biasa');
});

test('nama file hasil mempertahankan nama dasar dan memilih ekstensi keluaran', () => {
    assert.equal(buildOutputFilename('data.pelanggan.CSV', 'csv'), 'data.pelanggan-bersih.csv');
    assert.equal(buildOutputFilename('laporan.xlsx', 'xlsx'), 'laporan-bersih.xlsx');
});

test('validasi file hanya menerima CSV/XLSX dan batas ukuran yang benar', () => {
    assert.equal(validateFile({ name: 'data.csv', size: 10 * 1024 * 1024 }).type, 'csv');
    assert.equal(validateFile({ name: 'data.xlsx', size: 5 * 1024 * 1024 }).type, 'xlsx');
    assert.throws(() => validateFile({ name: 'data.csv', size: 10 * 1024 * 1024 + 1 }), /10 MB/u);
    assert.throws(() => validateFile({ name: 'data.xlsx', size: 5 * 1024 * 1024 + 1 }), /5 MB/u);
    assert.throws(() => validateFile({ name: 'data.xls', size: 100 }), /tidak didukung/u);
    assert.throws(() => validateFile({ name: 'data.xlsm', size: 100 }), /tidak didukung/u);
    assert.throws(() => validateFile({ name: 'data.csv', size: 0 }), /kosong/u);
});

test('CSV koma, titik koma, BOM, header duplikat, dan karakter Indonesia dibaca utuh', () => {
    const comma = parseCsvText('\uFEFFNama,Kota\r\nNadia,Jakarta');
    assert.equal(comma.delimiter, ',');
    assert.deepEqual(comma.headers, ['Nama', 'Kota']);
    assert.deepEqual(comma.rows, [['Nadia', 'Jakarta']]);

    const semicolon = parseCsvText('Nama;Nama;Catatan\r\nNadia;Prameswari;Kota Yogyakarta');
    assert.equal(semicolon.delimiter, ';');
    assert.deepEqual(semicolon.headers, ['Nama', 'Nama', 'Catatan']);
    assert.deepEqual(semicolon.rows, [['Nadia', 'Prameswari', 'Kota Yogyakarta']]);
    assert.throws(() => parseCsvText('Nama,Kota'), /minimal satu baris data/u);
    assert.throws(() => parseCsvText(''), /tidak memiliki data/u);
});

test('sheet Excel memakai cached value dan mempertahankan formula tanpa cached value sebagai teks', () => {
    const worksheet = XLSX.utils.aoa_to_sheet([['Nama', 'Nilai', 'Formula'], ['Nadia', 3, 'placeholder']]);
    worksheet.C2 = { t: 'n', v: 3, f: '1+2' };
    worksheet.C3 = { t: 'n', f: '2+2' };
    worksheet.A3 = { t: 's', v: 'Raka' };
    worksheet.B3 = { t: 'n', v: -1200 };
    worksheet['!ref'] = 'A1:C3';

    const result = extractExcelSheet(worksheet);

    assert.deepEqual(result.headers, ['Nama', 'Nilai', 'Formula']);
    assert.deepEqual(result.rows, [['Nadia', 3, 3], ['Raka', -1200, '=2+2']]);
});

test('XLSX harus berupa arsip ZIP dan rentang sheet ekstrem ditolak sebelum dialokasikan', () => {
    assert.equal(isXlsxArchive(new Uint8Array([0x50, 0x4B, 0x03, 0x04, 0x00])), true);
    assert.equal(isXlsxArchive(new TextEncoder().encode('Nama\nNadia')), false);
    assert.throws(() => extractExcelSheet({ '!ref': 'A1:XFD1048576', A1: { t: 's', v: 'Nama' } }), /terlalu banyak sel/u);
});

test('XLSX dengan ukuran dekompresi ekstrem ditolak sebelum workbook dibaca', () => {
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Nadia']]), 'Data');
    const bytes = new Uint8Array(XLSX.write(workbook, { bookType: 'xlsx', type: 'array' }));
    const signature = [0x50, 0x4B, 0x01, 0x02];
    const centralOffset = bytes.findIndex((value, index) => signature.every((part, partIndex) => bytes[index + partIndex] === part));
    assert.notEqual(centralOffset, -1);
    new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength).setUint32(centralOffset + 24, 60 * 1024 * 1024, true);

    assert.throws(() => inspectXlsxArchive(bytes), /terlalu besar setelah diekstrak/u);
});

test('ekspor CSV memakai BOM, seluruh data, delimiter koma, dan sanitasi formula', () => {
    const rows = Array.from({ length: 105 }, (_, index) => [`Baris ${index + 1}`, index === 104 ? '=SUM(A1:A2)' : index]);
    const csv = createCsvExport(['Nama', 'Nilai'], rows);

    assert.equal(csv.startsWith('\uFEFF'), true);
    assert.match(csv, /Baris 105,"?'=SUM\(A1:A2\)"?/u);
    assert.equal(csv.split('\r\n').length, 106);
});

test('ekspor XLSX hanya membuat sheet Data Bersih tanpa formula sumber', () => {
    const workbook = createExcelWorkbook(['Nama', 'Nilai'], [['Nadia', '=1+1'], ['Raka', -3.5]]);

    assert.deepEqual(workbook.SheetNames, ['Data Bersih']);
    assert.equal(workbook.Sheets['Data Bersih'].B2.v, "'=1+1");
    assert.equal(workbook.Sheets['Data Bersih'].B2.f, undefined);
    assert.equal(workbook.Sheets['Data Bersih'].B3.v, -3.5);
});

test('pergantian sheet memperbarui data awal dan menghapus hasil lama', () => {
    const state = dataCleaner();
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Nadia']]), 'Pertama');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Raka']]), 'Kedua');
    state.workbook = workbook;
    state.sheetNames = workbook.SheetNames;
    state.cleanedHeaders = ['lama'];
    state.cleanedRows = [['lama']];

    state.loadExcelSheet('Kedua');

    assert.equal(state.selectedSheet, 'Kedua');
    assert.deepEqual(state.originalRows, [['Raka']]);
    assert.deepEqual(state.cleanedHeaders, []);
    assert.deepEqual(state.cleanedRows, []);
});

test('reset state menghapus seluruh referensi data dari memory dan mengembalikan opsi default', () => {
    const state = dataCleaner();
    state.selectedFile = { name: 'rahasia.csv' };
    state.workbook = { SheetNames: ['Rahasia'] };
    state.sheetNames = ['Rahasia'];
    state.originalRows = [['data pribadi']];
    state.cleanedRows = [['data pribadi']];
    state.errorMessage = 'error';
    state.options = { ...state.options, removeDuplicates: false };

    state.resetData(false);

    assert.equal(state.selectedFile, null);
    assert.equal(state.workbook, null);
    assert.deepEqual(state.sheetNames, []);
    assert.deepEqual(state.originalRows, []);
    assert.deepEqual(state.cleanedRows, []);
    assert.equal(state.errorMessage, '');
    assert.equal(state.options.removeDuplicates, true);
});

test('state Alpine menjalankan alur CSV lengkap, preview, dan validasi pemilihan file', async () => {
    const state = dataCleaner();
    const file = {
        name: 'pelanggan.csv',
        size: 48,
        async text() {
            return ' Nama ,Kosong\r\n  Nadia  ,\r\n  Nadia  ,';
        },
    };

    await state.handleFileInput({ currentTarget: { files: [file] } });
    assert.equal(state.fileInfo.name, 'pelanggan.csv');
    assert.equal(state.fileInfo.type, 'CSV');
    assert.equal(state.fileInfo.size, '48 B');
    assert.deepEqual(state.previewRows(), [['  Nadia  ', ''], ['  Nadia  ', '']]);
    assert.equal(state.previewTotal(), 2);

    await state.runCleaning();
    assert.deepEqual(state.cleanedHeaders, ['nama']);
    assert.deepEqual(state.cleanedRows, [['Nadia']]);
    assert.equal(state.canDownload(), true);
    assert.deepEqual(state.previewHeaders(), ['nama']);

    state.setPreview('original');
    assert.deepEqual(state.previewHeaders(), [' Nama ', 'Kosong']);
    state.setPreview('cleaned');
    await state.handleDrop({ dataTransfer: { files: [] } });
    assert.equal(state.dragActive, false);
    assert.match(state.errorMessage, /tepat satu file/u);
});

test('state Alpine membaca XLSX, mengganti sheet, dan menangani kondisi gagal secara ramah', async () => {
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Nadia']]), 'Data');
    const bytes = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
    const state = dataCleaner();
    const file = {
        name: 'laporan.xlsx',
        size: 2 * 1024 * 1024,
        async arrayBuffer() {
            return bytes;
        },
    };

    await state.parseFile(file);
    assert.deepEqual(state.sheetNames, ['Data']);
    assert.equal(state.selectedSheet, 'Data');
    assert.equal(state.fileInfo.size, '2.00 MB');

    state.changeSheet({ currentTarget: { value: 'Tidak Ada' } });
    assert.match(state.errorMessage, /tidak dapat dibaca/u);
    assert.equal(state.selectedSheet, 'Data');
    assert.deepEqual(state.cleanedRows, []);
    await state.parseFile({ name: 'rusak.csv', size: 10, async text() { return 'Header'; } });
    assert.match(state.errorMessage, /minimal satu baris/u);
    assert.equal(state.selectedFile, null);
});

test('CSV dengan struktur rusak ditolak tanpa meninggalkan data yang dapat diproses', async () => {
    const state = dataCleaner();
    await state.parseFile({
        name: 'rusak.csv',
        size: 40,
        async text() {
            return 'Nama,Kota\r\nNadia,Jakarta,Tambahan';
        },
    });

    assert.match(state.errorMessage, /Struktur CSV tidak konsisten/u);
    assert.equal(state.selectedFile, null);
    assert.deepEqual(state.originalHeaders, []);
    assert.deepEqual(state.originalRows, []);
    assert.equal(state.canDownload(), false);
    assert.equal(state.parsingErrors.length, 1);
    assert.match(state.parsingErrors[0].message, /Jumlah kolom/u);
});

test('parser CSV membatasi detail error dan berhenti pada struktur rusak pertama', () => {
    const malformed = ['Nama,Kota', ...Array.from({ length: 100_000 }, () => 'Nadia')].join('\n');
    const parsed = parseCsvText(malformed);

    assert.equal(parsed.hasStructuralErrors, true);
    assert.ok(parsed.errorCount <= 2);
    assert.ok(parsed.errors.length <= 2);
    assert.equal(parsed.rows.length, 1);
    assert.ok(parsed.errors.some((error) => /Jumlah kolom/u.test(error.message)));
});

test('file teks yang hanya diganti ekstensi menjadi XLSX ditolak', async () => {
    const state = dataCleaner();
    const disguised = new TextEncoder().encode('Nama\nNadia');
    await state.parseFile({
        name: 'palsu.xlsx',
        size: disguised.byteLength,
        async arrayBuffer() { return disguised.buffer; },
    });

    assert.match(state.errorMessage, /bukan workbook XLSX yang valid/u);
    assert.equal(state.workbook, null);
    assert.equal(state.selectedFile, null);
});

test('hasil pembacaan file lama tidak boleh menimpa file terbaru', async () => {
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Lama']]), 'Data Lama');
    const oldBytes = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
    let releaseOldFile;
    const oldBuffer = new Promise((resolve) => { releaseOldFile = () => resolve(oldBytes); });
    const state = dataCleaner();

    const oldParsing = state.parseFile({
        name: 'lama.xlsx',
        size: 2000,
        async arrayBuffer() { return oldBuffer; },
    });
    await new Promise((resolve) => setTimeout(resolve, 0));
    await state.parseFile({
        name: 'baru.csv',
        size: 30,
        async text() { return 'Nama,Kota\r\nTerbaru,Bali'; },
    });
    releaseOldFile();
    await oldParsing;

    assert.equal(state.selectedFile.name, 'baru.csv');
    assert.equal(state.fileType, 'csv');
    assert.deepEqual(state.originalRows, [['Terbaru', 'Bali']]);
    assert.deepEqual(state.sheetNames, []);
});

test('workbook dengan sheet pertama kosong otomatis memilih sheet valid berikutnya', async () => {
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Sampul']]), 'Sampul');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Nadia']]), 'Data');
    const bytes = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
    const state = dataCleaner();

    await state.parseFile({ name: 'dengan-sampul.xlsx', size: 2000, async arrayBuffer() { return bytes; } });

    assert.deepEqual(state.sheetNames, ['Sampul', 'Data']);
    assert.equal(state.selectedSheet, 'Data');
    assert.deepEqual(state.originalRows, [['Nadia']]);
});

test('kontrol tab keyboard, konfirmasi reset, destroy, dan guard download bekerja', () => {
    const state = dataCleaner();
    state.originalHeaders = ['nama'];
    state.originalRows = [['Nadia']];
    state.cleanedHeaders = ['nama'];
    state.cleanedRows = [['Nadia']];
    state.cleaningSummary = { rowsAfter: 1 };
    state.activePreview = 'original';
    let prevented = false;
    state.handleTabKey({ key: 'ArrowRight', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.activePreview, 'cleaned');
    state.handleTabKey({ key: 'Home', preventDefault() {} });
    assert.equal(state.activePreview, 'original');
    state.handleTabKey({ key: 'End', preventDefault() {} });
    assert.equal(state.activePreview, 'cleaned');
    state.handleTabKey({ key: 'ArrowLeft', preventDefault() {} });
    assert.equal(state.activePreview, 'original');
    state.handleTabKey({ key: 'Escape', preventDefault() { throw new Error('tidak boleh dipanggil'); } });

    const previousWindow = globalThis.window;
    globalThis.window = { confirm: () => false };
    assert.equal(state.resetData(), false);
    assert.equal(state.originalRows.length, 1);
    globalThis.window.confirm = () => true;
    assert.equal(state.resetData(), true);
    state.destroy();
    assert.equal(state.canDownload(), false);
    state.downloadResult();
    globalThis.window = previousWindow;
});

test('state Alpine menangani memory pressure, data yang belum siap, dan kegagalan ekspor', async () => {
    const state = dataCleaner();
    await state.runCleaning();
    assert.match(state.errorMessage, /Pilih dan baca file/u);
    state.setPreview('cleaned');
    assert.equal(state.activePreview, 'original');

    state.parseCsv = async () => { throw new RangeError('out of memory'); };
    await state.parseFile({ name: 'besar.csv', size: 100 });
    assert.match(state.errorMessage, /tidak memiliki cukup memori/u);
    assert.equal(state.selectedFile, null);

    state.cleanedHeaders = ['nama'];
    state.cleanedRows = [['Nadia']];
    state.cleaningSummary = { rowsAfter: 1 };
    state.outputFormat = 'xlsx';
    state.exportExcel = () => { throw new Error('gagal'); };
    state.downloadResult();
    assert.match(state.errorMessage, /gagal dibuat/u);
    assert.throws(() => validateFile(null), /tidak didukung/u);
    assert.throws(() => validateFile({ name: 'tanpa-ekstensi', size: 10 }), /tidak didukung/u);
});
