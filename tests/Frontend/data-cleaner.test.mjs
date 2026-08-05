import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    analyzeData,
    buildOutputFilename,
    cleanData,
    dataCleaner,
    normalizeHeader,
    normalizeHeaders,
    removeDuplicates,
    removeEmptyColumns,
    removeEmptyRows,
    sanitizeExportValue,
    trimTextValues,
    validateFile,
} from '../../resources/js/data-cleaner.js';

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
    assert.equal(result.summary.textValuesTrimmed, 1);
    assert.equal(result.summary.headersNormalized, 3);
    assert.deepEqual(rows, originalRows);
});

test('analisis awal menghitung masalah tanpa menyatakan data sudah dibersihkan', () => {
    const result = analyzeData(
        [' Nama ', 'kosong'],
        [[' Nadia ', ''], [' Nadia ', ''], ['', '']],
        [{ message: 'contoh' }],
        2,
    );

    assert.equal(result.rowCount, 3);
    assert.equal(result.columnCount, 2);
    assert.equal(result.emptyRowCount, 1);
    assert.equal(result.duplicateRowCount, 1);
    assert.equal(result.emptyColumnCount, 1);
    assert.equal(result.headersNeedingNormalization, 1);
    assert.equal(result.parsingErrorCount, 1);
    assert.equal(result.sheetCount, 2);
});

test('formula spreadsheet diamankan saat ekspor tetapi angka negatif tetap utuh', () => {
    for (const value of ['=SUM(A1:A2)', '+CMD', '@perintah', '\tformula', '\rformula', '-SUM(A1:A2)']) {
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
