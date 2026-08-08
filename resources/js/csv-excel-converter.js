import Papa from 'papaparse';
import * as XLSX from 'xlsx';

export const CSV_MAX_BYTES = 10 * 1024 * 1024;
export const XLSX_MAX_BYTES = 5 * 1024 * 1024;
export const PREVIEW_ROW_LIMIT = 100;

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
    };
}

export { Papa, XLSX };
