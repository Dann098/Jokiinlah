import Papa from 'papaparse';
import * as XLSX from 'xlsx';

const CSV_MAX_BYTES = 10 * 1024 * 1024;
const XLSX_MAX_BYTES = 5 * 1024 * 1024;
const PREVIEW_LIMIT = 100;
const MAX_TABLE_CELLS = 1_000_000;
const MAX_XLSX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;

const DEFAULT_OPTIONS = Object.freeze({
    removeEmptyRows: true,
    removeDuplicates: true,
    trimText: true,
    normalizeHeaders: true,
    removeEmptyColumns: true,
});

function freshOptions() {
    return { ...DEFAULT_OPTIONS };
}

function cloneRows(rows) {
    return rows.map((row) => [...row]);
}

function isEmptyValue(value) {
    return value === null || value === undefined || (typeof value === 'string' && value.trim() === '');
}

function hasMeaningfulRow(rows) {
    return rows.some((row) => row.some((value) => !isEmptyValue(value)));
}

function assertSafeTableSize(rowCount, columnCount) {
    if (rowCount * columnCount > MAX_TABLE_CELLS) {
        throw new Error('Data memiliki terlalu banyak sel untuk diproses dengan aman di browser (maksimal 1.000.000 sel).');
    }
}

export function normalizeHeader(value, index) {
    const normalized = String(value ?? '')
        .trim()
        .normalize('NFKD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .replace(/[\s-]+/gu, '_')
        .replace(/[^a-z0-9_]/gu, '')
        .replace(/_+/gu, '_')
        .replace(/^_+|_+$/gu, '');

    return normalized || `kolom_${index + 1}`;
}

export function normalizeHeaders(headers) {
    const used = new Set();
    const nextSuffix = new Map();
    const changes = [];
    const normalizedHeaders = headers.map((header, index) => {
        const base = normalizeHeader(header, index);
        let candidate = base;
        let suffix = nextSuffix.get(base) ?? 2;

        while (used.has(candidate)) {
            candidate = `${base}_${suffix}`;
            suffix += 1;
        }

        nextSuffix.set(base, suffix);
        used.add(candidate);
        if (String(header ?? '') !== candidate) {
            changes.push({ index, original: String(header ?? ''), normalized: candidate });
        }

        return candidate;
    });

    return { headers: normalizedHeaders, changes };
}

function tidyText(value) {
    if (typeof value !== 'string') return value;

    return value
        .trim()
        .replace(/[^\S\r\n]+/gu, ' ');
}

export function trimTextValues(rows) {
    let changedCount = 0;
    const cleanedRows = rows.map((row) => row.map((value) => {
        const cleaned = tidyText(value);
        if (cleaned !== value) changedCount += 1;

        return cleaned;
    }));

    return { rows: cleanedRows, changedCount };
}

export function removeEmptyRows(rows) {
    const cleanedRows = rows.filter((row) => row.some((value) => !isEmptyValue(value)));

    return { rows: cleanedRows.map((row) => [...row]), removedCount: rows.length - cleanedRows.length };
}

export function removeEmptyColumns(headers, rows) {
    const retainedIndexes = headers
        .map((_, index) => index)
        .filter((index) => rows.some((row) => !isEmptyValue(row[index])));
    const retainedIndexSet = new Set(retainedIndexes);
    const removedIndexes = headers
        .map((_, index) => index)
        .filter((index) => !retainedIndexSet.has(index));

    return {
        headers: retainedIndexes.map((index) => headers[index]),
        rows: rows.map((row) => retainedIndexes.map((index) => row[index] ?? '')),
        removedHeaders: removedIndexes.map((index) => headers[index]),
        removedCount: removedIndexes.length,
    };
}

function stableRowKey(row) {
    return JSON.stringify(row.map((value) => {
        if (value === null) return ['null', null];
        if (value === undefined) return ['undefined', null];

        return [typeof value, value];
    }));
}

export function removeDuplicates(rows) {
    const seen = new Set();
    const cleanedRows = [];

    rows.forEach((row) => {
        const key = stableRowKey(row);
        if (seen.has(key)) return;
        seen.add(key);
        cleanedRows.push([...row]);
    });

    return { rows: cleanedRows, removedCount: rows.length - cleanedRows.length };
}

export function analyzeData(headers, rows, parsingErrors = [], sheetCount = 0) {
    const tidiedRows = trimTextValues(rows).rows;
    const nonEmptyRows = removeEmptyRows(tidiedRows).rows;
    const duplicateResult = removeDuplicates(nonEmptyRows);
    const emptyColumns = removeEmptyColumns(headers, rows);
    const normalized = normalizeHeaders(headers);

    return {
        rowCount: rows.length,
        columnCount: headers.length,
        emptyRowCount: rows.length - nonEmptyRows.length,
        duplicateRowCount: nonEmptyRows.length - duplicateResult.rows.length,
        emptyColumnCount: emptyColumns.removedCount,
        headersNeedingNormalization: normalized.changes.length,
        parsingErrorCount: Array.isArray(parsingErrors) ? parsingErrors.length : Number(parsingErrors) || 0,
        sheetCount,
    };
}

export function cleanData(originalHeaders, originalRows, options = DEFAULT_OPTIONS) {
    let headers = [...originalHeaders];
    let rows = cloneRows(originalRows);
    let headerChanges = [];
    let removedHeaders = [];
    const summary = {
        initialRows: rows.length,
        finalRows: rows.length,
        initialColumns: headers.length,
        finalColumns: headers.length,
        emptyRowsRemoved: 0,
        duplicatesRemoved: 0,
        emptyColumnsRemoved: 0,
        headersNormalized: 0,
        textValuesTrimmed: 0,
    };

    if (options.normalizeHeaders) {
        const normalized = normalizeHeaders(headers);
        headers = normalized.headers;
        headerChanges = normalized.changes;
        summary.headersNormalized = headerChanges.length;
    }

    if (options.trimText) {
        const tidied = trimTextValues(rows);
        rows = tidied.rows;
        summary.textValuesTrimmed = tidied.changedCount;
    }

    if (options.removeEmptyRows) {
        const nonEmpty = removeEmptyRows(rows);
        rows = nonEmpty.rows;
        summary.emptyRowsRemoved = nonEmpty.removedCount;
    }

    if (options.removeEmptyColumns) {
        const nonEmptyColumns = removeEmptyColumns(headers, rows);
        headers = nonEmptyColumns.headers;
        rows = nonEmptyColumns.rows;
        removedHeaders = nonEmptyColumns.removedHeaders;
        summary.emptyColumnsRemoved = nonEmptyColumns.removedCount;
    }

    if (options.removeDuplicates) {
        const unique = removeDuplicates(rows);
        rows = unique.rows;
        summary.duplicatesRemoved = unique.removedCount;
    }

    summary.finalRows = rows.length;
    summary.finalColumns = headers.length;

    return { headers, rows, summary, headerChanges, removedHeaders };
}

export function validateFile(file) {
    const extension = String(file?.name ?? '').toLowerCase().match(/\.[^.]+$/u)?.[0] ?? '';
    const type = extension === '.csv' ? 'csv' : extension === '.xlsx' ? 'xlsx' : '';

    if (!type) throw new Error('Format file tidak didukung. Gunakan file CSV atau XLSX.');
    if (!Number.isFinite(file?.size) || file.size <= 0) throw new Error('File kosong dan tidak dapat diproses.');
    if (type === 'csv' && file.size > CSV_MAX_BYTES) throw new Error('Ukuran file CSV melebihi batas maksimal 10 MB.');
    if (type === 'xlsx' && file.size > XLSX_MAX_BYTES) throw new Error('Ukuran file Excel melebihi batas maksimal 5 MB.');

    return { type, extension, maxBytes: type === 'csv' ? CSV_MAX_BYTES : XLSX_MAX_BYTES };
}

export function isXlsxArchive(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer ?? 0);

    return bytes.length >= 4
        && bytes[0] === 0x50
        && bytes[1] === 0x4B
        && bytes[2] === 0x03
        && bytes[3] === 0x04;
}

export function inspectXlsxArchive(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer ?? 0);
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const minimumEocdOffset = Math.max(0, bytes.length - 65_557);
    let eocdOffset = -1;

    for (let index = bytes.length - 22; index >= minimumEocdOffset; index -= 1) {
        if (view.getUint32(index, true) === 0x06054B50) {
            eocdOffset = index;
            break;
        }
    }

    if (!isXlsxArchive(bytes) || eocdOffset < 0) {
        throw new Error('File bukan workbook XLSX yang valid. Pastikan file tidak rusak atau hanya diganti ekstensinya.');
    }

    const entryCount = view.getUint16(eocdOffset + 10, true);
    const centralDirectorySize = view.getUint32(eocdOffset + 12, true);
    let offset = view.getUint32(eocdOffset + 16, true);
    const centralDirectoryEnd = offset + centralDirectorySize;
    let totalUncompressedBytes = 0;
    const entryNames = new Set();
    const decoder = new TextDecoder('utf-8');

    if (!entryCount || centralDirectoryEnd > eocdOffset || centralDirectoryEnd > bytes.length) {
        throw new Error('Struktur arsip XLSX tidak valid atau tidak didukung.');
    }

    for (let entry = 0; entry < entryCount; entry += 1) {
        if (offset + 46 > centralDirectoryEnd || view.getUint32(offset, true) !== 0x02014B50) {
            throw new Error('Struktur arsip XLSX tidak valid atau tidak didukung.');
        }

        const uncompressedBytes = view.getUint32(offset + 24, true);
        const nameLength = view.getUint16(offset + 28, true);
        const extraLength = view.getUint16(offset + 30, true);
        const commentLength = view.getUint16(offset + 32, true);
        const nextOffset = offset + 46 + nameLength + extraLength + commentLength;
        if (nextOffset > centralDirectoryEnd) throw new Error('Struktur arsip XLSX tidak valid atau tidak didukung.');

        totalUncompressedBytes += uncompressedBytes;
        if (totalUncompressedBytes > MAX_XLSX_UNCOMPRESSED_BYTES) {
            throw new Error('Workbook XLSX terlalu besar setelah diekstrak (maksimal 50 MB).');
        }

        entryNames.add(decoder.decode(bytes.subarray(offset + 46, offset + 46 + nameLength)).replaceAll('\\', '/'));
        offset = nextOffset;
    }

    if (!entryNames.has('[Content_Types].xml') || !entryNames.has('xl/workbook.xml')) {
        throw new Error('File bukan workbook XLSX yang valid. Struktur workbook tidak ditemukan.');
    }

    return { entryCount, totalUncompressedBytes };
}

function assertTabularData(headers, rows) {
    if (!headers.length || headers.every(isEmptyValue)) throw new Error('File tidak memiliki header yang dapat diproses.');
    if (!rows.length || !hasMeaningfulRow(rows)) throw new Error('File harus memiliki minimal satu baris data.');
}

export function parseCsvText(source) {
    const text = String(source ?? '').replace(/^\uFEFF/u, '');
    if (!text.trim()) throw new Error('File tidak memiliki data yang dapat diproses.');

    const table = [];
    const errors = [];
    let errorCount = 0;
    let hasStructuralErrors = false;
    let delimiter = ',';
    let cellCount = 0;
    let tooManyCells = false;
    let expectedColumns = null;

    Papa.parse(text, {
        delimiter: '',
        dynamicTyping: false,
        skipEmptyLines: false,
        step(result, parser) {
            const row = result.data ?? [];
            delimiter = result.meta.delimiter || delimiter;
            const stepErrors = result.errors ?? [];
            errorCount += stepErrors.length;
            errors.push(...stepErrors.slice(0, Math.max(0, 5 - errors.length)));
            const stepHasStructuralError = structuralCsvErrors(stepErrors).length > 0;
            hasStructuralErrors ||= stepHasStructuralError;
            cellCount += row.length;
            if (cellCount > MAX_TABLE_CELLS) {
                tooManyCells = true;
                parser.abort();
                return;
            }

            if (expectedColumns === null) {
                expectedColumns = row.length;
            } else if (row.length !== expectedColumns && !stepHasStructuralError) {
                const tooMany = row.length > expectedColumns;
                const mismatch = {
                    type: 'FieldMismatch',
                    code: tooMany ? 'TooManyFields' : 'TooFewFields',
                    message: tooMany
                        ? `Terlalu banyak kolom: diharapkan ${expectedColumns}, ditemukan ${row.length}.`
                        : `Terlalu sedikit kolom: diharapkan ${expectedColumns}, ditemukan ${row.length}.`,
                    row: table.length - 1,
                };
                errorCount += 1;
                hasStructuralErrors = true;
                if (errors.length < 5) errors.push(mismatch);
            }

            table.push(row);
            if (hasStructuralErrors) parser.abort();
        },
    });

    if (tooManyCells) assertSafeTableSize(MAX_TABLE_CELLS + 1, 1);
    const headers = table[0] ?? [];
    const rows = table.slice(1).map((row) => headers.map((_, index) => row[index] ?? ''));

    assertTabularData(headers, rows);

    return {
        headers,
        rows,
        delimiter,
        errorCount,
        hasStructuralErrors,
        errors: errors.map(({ type, code, message, row }) => ({
            type,
            code,
            message: ({
                MissingQuotes: 'Tanda kutip pada CSV tidak ditutup dengan benar.',
                UndetectableDelimiter: 'Delimiter CSV tidak dapat dideteksi; delimiter koma digunakan sebagai nilai awal.',
                TooManyFields: 'Jumlah kolom pada baris ini lebih banyak daripada header.',
                TooFewFields: 'Jumlah kolom pada baris ini lebih sedikit daripada header.',
            })[code] ?? (message || 'CSV tidak dapat dibaca dengan benar.'),
            row,
        })),
    };
}

function excelCellValue(cell) {
    if (!cell) return '';
    if (cell.v !== undefined && cell.v !== null) return cell.v;
    if (cell.f) return `=${cell.f}`;

    return '';
}

export function extractExcelSheet(worksheet) {
    if (!worksheet?.['!ref']) throw new Error('Sheet tidak memiliki data yang dapat diproses.');

    const range = XLSX.utils.decode_range(worksheet['!ref']);
    const rowCount = range.e.r - range.s.r + 1;
    const columnCount = range.e.c - range.s.c + 1;
    assertSafeTableSize(rowCount, columnCount);
    const table = [];
    for (let rowIndex = range.s.r; rowIndex <= range.e.r; rowIndex += 1) {
        const row = [];
        for (let columnIndex = range.s.c; columnIndex <= range.e.c; columnIndex += 1) {
            row.push(excelCellValue(worksheet[XLSX.utils.encode_cell({ r: rowIndex, c: columnIndex })]));
        }
        table.push(row);
    }

    const headers = table[0] ?? [];
    const rows = table.slice(1);
    assertTabularData(headers, rows);

    return { headers: [...headers], rows: cloneRows(rows) };
}

export function sanitizeExportValue(value) {
    if (typeof value !== 'string' || value === '') return value;
    if (/^[\u0000-\u0020]*-\d+(?:[.,]\d+)?$/u.test(value)) return value;
    if (/^[\u0000-\u0020]*[\t\r\n]/u.test(value) || /^[\u0000-\u0020]*[=+@-]/u.test(value)) return `'${value}`;

    return value;
}

function sanitizedTable(headers, rows) {
    return {
        headers: headers.map(sanitizeExportValue),
        rows: rows.map((row) => row.map(sanitizeExportValue)),
    };
}

export function createCsvExport(headers, rows) {
    const safe = sanitizedTable(headers, rows);
    const csv = Papa.unparse({ fields: safe.headers, data: safe.rows }, {
        delimiter: ',',
        header: true,
        newline: '\r\n',
        skipEmptyLines: false,
    });

    return `\uFEFF${csv}`;
}

export function createExcelWorkbook(headers, rows) {
    const safe = sanitizedTable(headers, rows);
    const worksheet = XLSX.utils.aoa_to_sheet([safe.headers, ...safe.rows]);
    worksheet['!cols'] = safe.headers.map((header, index) => ({
        wch: Math.min(40, Math.max(10, String(header).length + 2, ...safe.rows.slice(0, 100).map((row) => String(row[index] ?? '').length + 2))),
    }));
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Data Bersih');

    return workbook;
}

export function buildOutputFilename(originalName, format) {
    const base = String(originalName ?? 'data')
        .replace(/\.(csv|xlsx)$/iu, '')
        .trim() || 'data';

    return `${base}-bersih.${format === 'xlsx' ? 'xlsx' : 'csv'}`;
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function triggerDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.hidden = true;
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    setTimeout(() => URL.revokeObjectURL(url), 0);
}

function structuralCsvErrors(errors) {
    return errors.filter((error) => ['MissingQuotes', 'TooFewFields', 'TooManyFields'].includes(error.code));
}

function memoryError(error) {
    return error instanceof RangeError || /memory|allocation|out of memory/iu.test(String(error?.message ?? ''));
}

export function dataCleaner() {
    return {
        selectedFile: null,
        fileType: '',
        fileInfo: null,
        workbook: null,
        sheetNames: [],
        selectedSheet: '',
        loadedSheet: '',
        delimiter: '',
        originalHeaders: [],
        originalRows: [],
        cleanedHeaders: [],
        cleanedRows: [],
        options: freshOptions(),
        outputFormat: 'csv',
        parsingErrors: [],
        parsingErrorCount: 0,
        initialSummary: null,
        cleaningSummary: null,
        headerChanges: [],
        removedHeaders: [],
        activePreview: 'original',
        isProcessing: false,
        errorMessage: '',
        dragActive: false,
        parseGeneration: 0,

        async handleFileInput(event) {
            await this.acceptFiles(event.currentTarget?.files ?? []);
        },

        async handleDrop(event) {
            this.dragActive = false;
            await this.acceptFiles(event.dataTransfer?.files ?? []);
        },

        async acceptFiles(files) {
            if (files.length !== 1) {
                this.errorMessage = 'Pilih tepat satu file CSV atau XLSX.';
                return;
            }

            await this.parseFile(files[0]);
        },

        async parseFile(file) {
            const generation = this.parseGeneration + 1;
            this.parseGeneration = generation;
            this.isProcessing = true;
            this.errorMessage = '';
            this.parsingErrors = [];
            this.selectedFile = null;
            this.fileType = '';
            this.clearParsedData();

            try {
                const validation = validateFile(file);
                await new Promise((resolve) => setTimeout(resolve, 0));

                if (validation.type === 'csv') {
                    const parsed = await this.parseCsv(file);
                    if (generation !== this.parseGeneration) return;
                    this.parsingErrors = parsed.errors.slice(0, 5);
                    this.parsingErrorCount = parsed.errorCount;
                    if (parsed.hasStructuralErrors) {
                        throw new Error(`Struktur CSV tidak konsisten (${parsed.errorCount} error parsing). Periksa contoh error yang ditampilkan.`);
                    }

                    this.delimiter = parsed.delimiter;
                    this.setOriginalData(parsed.headers, parsed.rows);
                } else {
                    const parsed = await this.parseExcel(file);
                    if (generation !== this.parseGeneration) return;
                    this.workbook = parsed.workbook;
                    this.sheetNames = parsed.sheetNames;
                    this.selectedSheet = parsed.selectedSheet;
                    this.loadedSheet = parsed.selectedSheet;
                    this.setOriginalData(parsed.extracted.headers, parsed.extracted.rows);
                }

                this.selectedFile = file;
                this.fileType = validation.type;
                this.outputFormat = validation.type;
                this.updateFileInfo();
            } catch (error) {
                if (generation !== this.parseGeneration) return;
                this.errorMessage = memoryError(error)
                    ? 'File terlalu besar atau perangkat tidak memiliki cukup memori untuk memproses data ini.'
                    : String(error?.message ?? 'File gagal dibaca. Periksa file lalu coba kembali.');
                this.selectedFile = null;
                this.fileType = '';
                this.clearParsedData(true);
            } finally {
                if (generation === this.parseGeneration) this.isProcessing = false;
            }
        },

        async parseCsv(file) {
            return parseCsvText(await file.text());
        },

        async parseExcel(file) {
            const buffer = await file.arrayBuffer();
            inspectXlsxArchive(buffer);
            let workbook;
            try {
                workbook = XLSX.read(buffer, {
                    cellDates: false,
                    cellFormula: true,
                    cellHTML: false,
                    cellNF: false,
                    cellStyles: false,
                    type: 'array',
                });
            } catch {
                throw new Error('Workbook XLSX gagal dibaca. File mungkin rusak, terenkripsi, atau tidak didukung.');
            }

            const sheetNames = [...(workbook.SheetNames ?? [])];
            if (!sheetNames.length) throw new Error('Workbook Excel tidak memiliki sheet.');

            let selectedSheet = '';
            let extracted = null;
            for (const sheetName of sheetNames) {
                try {
                    extracted = extractExcelSheet(workbook.Sheets[sheetName]);
                    selectedSheet = sheetName;
                    break;
                } catch {
                    // Sheet kosong tetap tersedia di pemilih; cari sheet pertama yang berisi tabel.
                }
            }
            if (!extracted) throw new Error('Workbook Excel tidak memiliki sheet dengan minimal satu baris data.');

            return {
                workbook,
                sheetNames,
                selectedSheet,
                extracted,
            };
        },

        loadExcelSheet(sheetName = this.selectedSheet) {
            if (!this.workbook?.Sheets?.[sheetName]) throw new Error('Sheet yang dipilih tidak dapat dibaca.');
            const extracted = extractExcelSheet(this.workbook.Sheets[sheetName]);
            this.selectedSheet = sheetName;
            this.loadedSheet = sheetName;
            this.setOriginalData(extracted.headers, extracted.rows);
            this.updateFileInfo();
        },

        changeSheet(event) {
            try {
                this.errorMessage = '';
                this.loadExcelSheet(event.currentTarget.value);
            } catch (error) {
                this.selectedSheet = this.loadedSheet;
                this.errorMessage = String(error?.message ?? 'Sheet gagal dibaca.');
                this.clearCleanedData();
            }
        },

        setOriginalData(headers, rows) {
            this.originalHeaders = [...headers];
            this.originalRows = cloneRows(rows);
            this.initialSummary = analyzeData(headers, rows, this.parsingErrorCount, this.sheetNames.length);
            this.clearCleanedData();
            this.activePreview = 'original';
        },

        updateFileInfo() {
            if (!this.selectedFile) return;
            this.fileInfo = {
                name: this.selectedFile.name,
                size: formatFileSize(this.selectedFile.size),
                type: this.fileType === 'xlsx' ? 'Excel XLSX' : 'CSV',
                delimiter: this.fileType === 'csv' ? this.delimiter : '',
                sheetCount: this.sheetNames.length,
                rowCount: this.originalRows.length,
                columnCount: this.originalHeaders.length,
            };
        },

        async runCleaning() {
            if (!this.originalHeaders.length || !this.originalRows.length) {
                this.errorMessage = 'Pilih dan baca file terlebih dahulu.';
                return;
            }

            this.isProcessing = true;
            this.errorMessage = '';
            await new Promise((resolve) => setTimeout(resolve, 0));

            try {
                const result = cleanData(this.originalHeaders, this.originalRows, this.options);
                this.cleanedHeaders = result.headers;
                this.cleanedRows = result.rows;
                this.cleaningSummary = result.summary;
                this.headerChanges = result.headerChanges;
                this.removedHeaders = result.removedHeaders;
                this.activePreview = 'cleaned';
            } catch (error) {
                this.errorMessage = memoryError(error)
                    ? 'File terlalu besar atau perangkat tidak memiliki cukup memori untuk memproses data ini.'
                    : 'Data gagal dibersihkan. Periksa file dan pilihan pembersihan lalu coba kembali.';
            } finally {
                this.isProcessing = false;
            }
        },

        exportCsv() {
            const csv = createCsvExport(this.cleanedHeaders, this.cleanedRows);
            triggerDownload(new Blob([csv], { type: 'text/csv;charset=utf-8' }), buildOutputFilename(this.selectedFile?.name, 'csv'));
        },

        exportExcel() {
            const workbook = createExcelWorkbook(this.cleanedHeaders, this.cleanedRows);
            const bytes = XLSX.write(workbook, { bookType: 'xlsx', compression: true, type: 'array' });
            triggerDownload(
                new Blob([bytes], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
                buildOutputFilename(this.selectedFile?.name, 'xlsx'),
            );
        },

        downloadResult() {
            if (!this.canDownload()) return;

            try {
                if (this.outputFormat === 'xlsx') this.exportExcel();
                else this.exportCsv();
            } catch {
                this.errorMessage = 'File hasil gagal dibuat. Coba pilih format lain atau proses ulang data.';
            }
        },

        setPreview(tab) {
            if (tab === 'cleaned' && !this.cleaningSummary) return;
            this.activePreview = tab;
        },

        handleTabKey(event) {
            const tabs = this.cleaningSummary ? ['original', 'cleaned'] : ['original'];
            const current = tabs.indexOf(this.activePreview);
            let next = current;
            if (event.key === 'ArrowRight') next = (current + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') next = (current - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault();
            this.setPreview(tabs[next]);
            this.$nextTick?.(() => document.getElementById(`data-tab-${tabs[next]}`)?.focus());
        },

        previewHeaders() {
            return this.activePreview === 'cleaned' ? this.cleanedHeaders : this.originalHeaders;
        },

        previewRows() {
            const rows = this.activePreview === 'cleaned' ? this.cleanedRows : this.originalRows;
            return rows.slice(0, PREVIEW_LIMIT);
        },

        previewTotal() {
            return this.activePreview === 'cleaned' ? this.cleanedRows.length : this.originalRows.length;
        },

        canDownload() {
            return Boolean(this.cleaningSummary && this.cleanedHeaders.length && this.cleanedRows.length && !this.isProcessing);
        },

        clearCleanedData() {
            this.cleanedHeaders = [];
            this.cleanedRows = [];
            this.cleaningSummary = null;
            this.headerChanges = [];
            this.removedHeaders = [];
        },

        clearParsedData(keepErrors = false) {
            this.fileInfo = null;
            this.workbook = null;
            this.sheetNames = [];
            this.selectedSheet = '';
            this.loadedSheet = '';
            this.delimiter = '';
            this.originalHeaders = [];
            this.originalRows = [];
            this.initialSummary = null;
            this.clearCleanedData();
            this.activePreview = 'original';
            if (!keepErrors) {
                this.parsingErrors = [];
                this.parsingErrorCount = 0;
            }
        },

        resetData(confirmReset = true) {
            const hasData = Boolean(this.selectedFile || this.originalRows.length || this.cleanedRows.length);
            if (confirmReset && hasData && typeof window !== 'undefined' && !window.confirm('Hapus seluruh file dan data dari browser?')) return false;

            this.selectedFile = null;
            this.fileType = '';
            this.options = freshOptions();
            this.outputFormat = 'csv';
            this.errorMessage = '';
            this.dragActive = false;
            this.parseGeneration += 1;
            this.isProcessing = false;
            this.clearParsedData();
            if (this.$refs?.fileInput) this.$refs.fileInput.value = '';

            return true;
        },

        destroy() {
            this.resetData(false);
        },
    };
}
