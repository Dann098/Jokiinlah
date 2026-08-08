import Papa from 'papaparse';
import * as XLSX from 'xlsx';

export const CSV_MAX_BYTES = 10 * 1024 * 1024;
export const XLSX_MAX_BYTES = 5 * 1024 * 1024;
export const PREVIEW_ROW_LIMIT = 100;

export function detectFileType(filename) {
    const extension = String(filename ?? '').trim().toLowerCase().match(/\.[^.]+$/u)?.[0] ?? '';

    if (extension === '.csv') return { type: 'csv', direction: 'CSV → Excel' };
    if (extension === '.xlsx') return { type: 'xlsx', direction: 'Excel → CSV' };

    throw new Error('Format file tidak didukung. Gunakan file CSV atau XLSX.');
}

export function validateFile(file) {
    const detected = detectFileType(file?.name);

    if (!Number.isFinite(file?.size) || file.size <= 0) {
        throw new Error('File kosong dan tidak dapat diproses.');
    }

    if (detected.type === 'csv' && file.size > CSV_MAX_BYTES) {
        throw new Error('Ukuran file CSV melebihi batas maksimal 10 MB.');
    }

    if (detected.type === 'xlsx' && file.size > XLSX_MAX_BYTES) {
        throw new Error('Ukuran file XLSX melebihi batas maksimal 5 MB.');
    }

    return { ...detected, maxBytes: detected.type === 'csv' ? CSV_MAX_BYTES : XLSX_MAX_BYTES };
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function pendingConversion() {
    throw new Error('Konversi belum siap. Muat ulang halaman lalu coba kembali.');
}

export const parseCsv = pendingConversion;
export const createExcelWorkbook = pendingConversion;
export const extractExcelSheet = pendingConversion;
export const inspectXlsxArchive = pendingConversion;
export const createCsvExport = pendingConversion;
export const sanitizeExportValue = pendingConversion;
export const buildOutputFilename = pendingConversion;

export function csvExcelConverter() {
    return {
        selectedFile: null,
        fileType: '',
        fileInfo: null,
        workbook: null,
        sheetNames: [],
        selectedSheet: '',
        headers: [],
        rows: [],
        previewRows: [],
        delimiter: '',
        isProcessing: false,
        errorMessage: '',
        resultBlob: null,
        dragActive: false,
        conversionDirection: '',
        async selectFile(file) {
            this.errorMessage = '';
            this.resultBlob = null;

            try {
                const validated = validateFile(file);
                this.selectedFile = file;
                this.fileType = validated.type;
                this.conversionDirection = validated.direction;
                this.fileInfo = {
                    name: file.name,
                    size: formatFileSize(file.size),
                    type: validated.type === 'csv' ? 'CSV' : 'Excel XLSX',
                    rowCount: null,
                    columnCount: null,
                    sheetCount: null,
                };
            } catch (error) {
                this.selectedFile = null;
                this.fileType = '';
                this.fileInfo = null;
                this.conversionDirection = '';
                this.errorMessage = error instanceof Error ? error.message : 'File tidak dapat divalidasi.';
            }
        },
        handleFileInput(event) {
            const [file] = event?.target?.files ?? [];
            if (file) void this.selectFile(file);
        },
        handleDrop(event) {
            this.dragActive = false;
            const files = event?.dataTransfer?.files ?? [];
            if (files.length > 1) {
                this.errorMessage = 'Pilih hanya satu file untuk setiap proses.';
                return;
            }

            const [file] = files;
            if (file) void this.selectFile(file);
        },
    };
}
