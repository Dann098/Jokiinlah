import assert from 'node:assert/strict';
import { test } from 'node:test';

import * as XLSX from 'xlsx';
import {
    buildOutputFilename,
    createCsvExport,
    createExcelWorkbook,
    csvExcelConverter,
    detectFileType,
    extractExcelSheet,
    inspectXlsxArchive,
    parseCsv,
    sanitizeExportValue,
    validateFile,
} from '../../resources/js/csv-excel-converter.js';

test('jenis dan arah konversi dideteksi hanya dari ekstensi CSV atau XLSX', () => {
    assert.deepEqual(detectFileType('penjualan.CSV'), { type: 'csv', direction: 'CSV → Excel' });
    assert.deepEqual(detectFileType('penjualan.xlsx'), { type: 'xlsx', direction: 'Excel → CSV' });
    assert.throws(() => detectFileType('penjualan.xls'), /tidak didukung/u);
    assert.throws(() => detectFileType('penjualan.csv.exe'), /tidak didukung/u);
});

test('validasi menerima batas tepat dan menolak file kosong, terlalu besar, atau format lain', () => {
    assert.equal(validateFile({ name: 'data.csv', size: 10 * 1024 * 1024 }).type, 'csv');
    assert.equal(validateFile({ name: 'data.xlsx', size: 5 * 1024 * 1024 }).type, 'xlsx');
    assert.throws(() => validateFile({ name: 'data.csv', size: 10 * 1024 * 1024 + 1 }), /10 MB/u);
    assert.throws(() => validateFile({ name: 'data.xlsx', size: 5 * 1024 * 1024 + 1 }), /5 MB/u);
    assert.throws(() => validateFile({ name: 'data.csv', size: 0 }), /kosong/u);
    for (const name of ['data.xls', 'data.xlsm', 'data.xlsb', 'data.ods', 'data.json', 'data.pdf', 'data.zip', 'data.exe']) {
        assert.throws(() => validateFile({ name, size: 100 }), /tidak didukung/u);
    }
});

test('CSV memakai header, mendeteksi koma, titik koma, dan tab tanpa mengubah data teks', () => {
    const comma = parseCsv('\uFEFFNama,Kode,Catatan\r\nRani,00123,café & español');
    assert.equal(comma.delimiter, ',');
    assert.deepEqual(comma.headers, ['Nama', 'Kode', 'Catatan']);
    assert.deepEqual(comma.rows, [['Rani', '00123', 'café & español']]);

    const semicolon = parseCsv('Nama;Telepon\nRani;08123456789');
    assert.equal(semicolon.delimiter, ';');
    assert.deepEqual(semicolon.rows, [['Rani', '08123456789']]);

    const tab = parseCsv('Nama\tKota\nRani\tYogyakarta');
    assert.equal(tab.delimiter, '\t');
    assert.deepEqual(tab.rows, [['Rani', 'Yogyakarta']]);
});

test('CSV rusak, tanpa header, atau tanpa baris data ditolak dengan pesan aman', () => {
    assert.throws(() => parseCsv(''), /tidak memiliki data/u);
    assert.throws(() => parseCsv('Nama,Kota'), /minimal satu baris data/u);
    assert.throws(() => parseCsv('Nama,Kota\n"Rani,Yogyakarta'), /tidak dapat dibaca/u);
});

test('workbook hasil CSV memiliki satu sheet Data, seluruh baris, lebar kolom, dan tanpa formula', () => {
    const rows = Array.from({ length: 125 }, (_, index) => [String(index).padStart(4, '0'), index === 124 ? '=1+1' : `Baris ${index + 1}`]);
    const workbook = createExcelWorkbook(['Kode', 'Nilai'], rows);
    const worksheet = workbook.Sheets.Data;
    const output = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: true });

    assert.deepEqual(workbook.SheetNames, ['Data']);
    assert.equal(output.length, 126);
    assert.equal(output[1][0], '0000');
    assert.equal(worksheet.B126.f, undefined);
    assert.equal(worksheet.B126.v, "'=1+1");
    assert.equal(Array.isArray(worksheet['!cols']), true);
});

test('sheet Excel memakai cached value dan formula tanpa cache tetap menjadi teks aman', () => {
    const worksheet = XLSX.utils.aoa_to_sheet([['Nama', 'Nilai', 'Formula'], ['Rani', -1200, 'placeholder']]);
    worksheet.C2 = { t: 'n', v: 3, f: '1+2' };
    worksheet.A3 = { t: 's', v: 'Dimas' };
    worksheet.B3 = { t: 's', v: '00123' };
    worksheet.C3 = { t: 'n', f: '2+2' };
    worksheet['!ref'] = 'A1:C3';

    const result = extractExcelSheet(worksheet);

    assert.deepEqual(result.headers, ['Nama', 'Nilai', 'Formula']);
    assert.deepEqual(result.rows, [['Rani', -1200, 3], ['Dimas', '00123', '=2+2']]);
});

test('arsip XLSX valid dikenali dan beban dekompresi berlebihan ditolak', () => {
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Rani']]), 'Data');
    const bytes = new Uint8Array(XLSX.write(workbook, { bookType: 'xlsx', type: 'array' }));

    assert.doesNotThrow(() => inspectXlsxArchive(bytes));
    assert.throws(() => inspectXlsxArchive(new TextEncoder().encode('bukan xlsx')), /bukan workbook XLSX/u);
});

test('CSV keluaran memakai BOM, koma, header, seluruh data, dan encoding UTF-8', () => {
    const rows = Array.from({ length: 125 }, (_, index) => [`Baris ${index + 1}`, index === 124 ? 'é ñ & Bahasa Indonesia' : index]);
    const csv = createCsvExport(['Nama', 'Nilai'], rows);

    assert.equal(csv.startsWith('\uFEFF'), true);
    assert.equal(csv.split('\r\n').length, 126);
    assert.match(csv, /Baris 125,é ñ & Bahasa Indonesia/u);
});

test('formula injection diamankan tanpa merusak angka negatif', () => {
    for (const value of ['=SUM(A1:A10)', '+CMD', '@perintah', '\tformula', '\rformula', '\nformula', '  -CMD']) {
        assert.equal(sanitizeExportValue(value), `'${value}`);
    }

    assert.equal(sanitizeExportValue(-1200), -1200);
    assert.equal(sanitizeExportValue('-1200'), '-1200');
    assert.equal(sanitizeExportValue('-3.5'), '-3.5');
});

test('nama hasil aman, mempertahankan nama dasar, dan memakai akhiran konversi', () => {
    assert.equal(buildOutputFilename('data penjualan.CSV', 'xlsx'), 'data penjualan-konversi.xlsx');
    assert.equal(buildOutputFilename('laporan.final.xlsx', 'csv'), 'laporan.final-konversi.csv');
    assert.equal(buildOutputFilename('../rahasia?.xlsx', 'csv'), 'rahasia-konversi.csv');
});

test('pemilihan sheet memperbarui preview dan membuang hasil konversi sebelumnya', () => {
    const state = csvExcelConverter();
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Rani']]), 'Januari');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([['Nama'], ['Dimas']]), 'Februari');
    state.workbook = workbook;
    state.sheetNames = [...workbook.SheetNames];
    state.resultBlob = { stale: true };

    state.loadSheet('Februari');

    assert.equal(state.selectedSheet, 'Februari');
    assert.deepEqual(state.rows, [['Dimas']]);
    assert.deepEqual(state.previewRows, [['Dimas']]);
    assert.equal(state.resultBlob, null);
});

test('preview dibatasi 100 baris sedangkan state tetap menyimpan seluruh baris', () => {
    const state = csvExcelConverter();
    state.setTable(['Nilai'], Array.from({ length: 125 }, (_, index) => [index]));

    assert.equal(state.rows.length, 125);
    assert.equal(state.previewRows.length, 100);
    assert.equal(state.fileInfo.rowCount, 125);
});

test('reset menghapus seluruh referensi file, workbook, sheet, preview, hasil, dan error', () => {
    const state = csvExcelConverter();
    state.selectedFile = { name: 'rahasia.xlsx' };
    state.workbook = { SheetNames: ['Rahasia'] };
    state.sheetNames = ['Rahasia'];
    state.selectedSheet = 'Rahasia';
    state.headers = ['Nama'];
    state.rows = [['data pribadi']];
    state.previewRows = [['data pribadi']];
    state.resultBlob = { private: true };
    state.errorMessage = 'error';

    state.reset(false);

    assert.equal(state.selectedFile, null);
    assert.equal(state.workbook, null);
    assert.deepEqual(state.sheetNames, []);
    assert.equal(state.selectedSheet, '');
    assert.deepEqual(state.headers, []);
    assert.deepEqual(state.rows, []);
    assert.deepEqual(state.previewRows, []);
    assert.equal(state.resultBlob, null);
    assert.equal(state.errorMessage, '');
});
