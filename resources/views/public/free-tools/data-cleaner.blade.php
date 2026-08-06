@extends('layouts.public')

@section('title')
Pembersih CSV & Excel Gratis | Jokiinlah
@endsection
@section('description', 'Bersihkan file CSV dan Excel dari baris kosong, data duplikat, spasi berlebih, dan nama kolom yang tidak konsisten langsung di browser tanpa upload ke server.')

@section('content')
<div class='data-cleaner-shell bg-[#f4f6f9]' x-data='dataCleaner' x-on:keydown.escape.window='errorMessage = ""'>
    <section class='bg-navy text-white'>
        <div class='container-public py-10 sm:py-14'>
            <x-breadcrumb :items="['Fitur Gratis' => route('free-tools.index'), 'Pembersih Data' => null]" />
            <div class='mt-7 max-w-4xl'>
                <div class='flex flex-wrap gap-2' aria-label='Karakteristik fitur'>
                    @foreach(['Gratis', 'CSV & XLSX', 'Tanpa Login', 'Diproses di Browser'] as $label)
                        <span class='inline-flex min-h-8 items-center rounded-full border border-white/20 bg-white/10 px-3 text-xs font-bold uppercase tracking-wider text-gold'>{{ $label }}</span>
                    @endforeach
                </div>
                <h1 class='mt-5 text-balance text-3xl font-bold leading-tight sm:text-5xl'>Bersihkan Data CSV &amp; Excel Secara Gratis</h1>
                <p class='mt-5 max-w-3xl text-sm leading-7 text-white/75 sm:text-base'>Hapus baris kosong, data duplikat, spasi berlebih, dan rapikan nama kolom langsung di browser tanpa mengunggah file ke server.</p>
            </div>
        </div>
    </section>

    <div class='container-public py-6 sm:py-8'>
        <div class='rounded-2xl border border-emerald-700/20 bg-emerald-50 p-5 text-sm text-emerald-950' role='note'>
            <div class='flex items-start gap-3'>
                <span class='mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-700 font-bold text-white' aria-hidden='true'>✓</span>
                <div>
                    <p class='font-bold'>Privasi file Anda terlindungi</p>
                    <p class='mt-1 leading-6'>File diproses langsung di perangkat Anda dan tidak dikirim atau disimpan di server Jokiinlah.</p>
                    <p class='mt-1 leading-6'>Data hanya berada di memori halaman ini dan dihapus saat Anda melakukan reset atau menutup halaman.</p>
                </div>
            </div>
        </div>

        <ol class='mt-6 grid gap-2 sm:grid-cols-5' aria-label='Tahapan pembersihan data'>
            @foreach(['Pilih File', 'Pilih Sheet', 'Pilih Pembersihan', 'Periksa Hasil', 'Unduh Data'] as $step)
                <li class='rounded-xl border border-navy/10 bg-white px-3 py-3 text-center text-xs font-bold text-navy'>
                    <span class='mr-1 text-[#80520d]'>{{ $loop->iteration }}.</span>{{ $step }}
                </li>
            @endforeach
        </ol>

        <div class='mt-6' role='alert' aria-live='assertive' x-cloak x-show='errorMessage'>
            <div class='rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-900' x-text='errorMessage'></div>
        </div>

        <div class='mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]'>
            <div class='space-y-6'>
                <section class='surface-card p-5 sm:p-7' aria-labelledby='data-upload-title'>
                    <div class='flex items-start justify-between gap-4'>
                        <div>
                            <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Langkah 1</p>
                            <h2 id='data-upload-title' class='mt-2 text-2xl font-bold text-navy'>Pilih File</h2>
                        </div>
                        <span class='rounded-full bg-navy/5 px-3 py-1 text-xs font-bold text-navy'>CSV ≤ 10 MB · XLSX ≤ 5 MB</span>
                    </div>

                    <label for='data-cleaner-file' class='data-cleaner-dropzone mt-5' x-bind:class="dragActive ? 'is-dragging' : ''" x-on:dragenter.prevent='dragActive = true' x-on:dragover.prevent='dragActive = true' x-on:dragleave.prevent='dragActive = false' x-on:drop.prevent='handleDrop($event)'>
                        <input id='data-cleaner-file' x-ref='fileInput' class='sr-only' type='file' accept='.csv,.xlsx' x-on:change='handleFileInput($event)'>
                        <span class='flex h-12 w-12 items-center justify-center rounded-full bg-navy text-xl text-gold' aria-hidden='true'>↑</span>
                        <span class='mt-3 block text-base font-bold text-navy'>Klik untuk memilih atau tarik file ke area ini</span>
                        <span class='mt-1 block text-sm leading-6 text-muted'>Hanya satu file dengan format .csv atau .xlsx.</span>
                    </label>
                    <div class='mt-4 min-h-6 text-sm font-semibold text-navy' aria-live='polite'>
                        <span x-show='isProcessing'>Membaca dan memvalidasi file…</span>
                        <span x-show='!isProcessing && selectedFile' x-text='selectedFile?.name'></span>
                    </div>

                    <dl class='mt-5 grid gap-3 text-sm sm:grid-cols-2' x-cloak x-show='fileInfo'>
                        <div class='data-cleaner-info'><dt>Nama file</dt><dd x-text='fileInfo?.name'></dd></div>
                        <div class='data-cleaner-info'><dt>Ukuran</dt><dd x-text='fileInfo?.size'></dd></div>
                        <div class='data-cleaner-info'><dt>Jenis</dt><dd x-text='fileInfo?.type'></dd></div>
                        <div class='data-cleaner-info' x-show="fileType === 'csv'"><dt>Delimiter</dt><dd x-text="delimiter === '\t' ? 'Tab' : delimiter"></dd></div>
                        <div class='data-cleaner-info' x-show="fileType === 'xlsx'"><dt>Jumlah sheet</dt><dd x-text='fileInfo?.sheetCount'></dd></div>
                        <div class='data-cleaner-info'><dt>Baris data</dt><dd x-text='fileInfo?.rowCount?.toLocaleString("id-ID")'></dd></div>
                        <div class='data-cleaner-info'><dt>Kolom</dt><dd x-text='fileInfo?.columnCount?.toLocaleString("id-ID")'></dd></div>
                    </dl>

                    <button type='button' class='mt-4 inline-flex min-h-11 items-center justify-center rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-bold text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2' x-cloak x-show='selectedFile' x-on:click='resetData'>Reset Data</button>

                    <div class='mt-5' x-cloak x-show="fileType === 'xlsx' && sheetNames.length">
                        <label class='text-sm font-bold text-navy' for='data-cleaner-sheet'>Pilih Sheet</label>
                        <select id='data-cleaner-sheet' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 bg-white px-3 text-sm text-charcoal focus:border-navy focus:outline-none focus:ring-2 focus:ring-gold/40' x-model='selectedSheet' x-on:change='changeSheet($event)'>
                            <template x-for='sheet in sheetNames' x-bind:key='sheet'>
                                <option x-bind:value='sheet' x-text='sheet'></option>
                            </template>
                        </select>
                        <p class='mt-2 text-xs leading-5 text-muted'>Pembersihan Excel hanya memproses nilai tabel pada sheet yang dipilih.</p>
                    </div>

                    <div class='mt-5 rounded-xl bg-amber-50 p-4 text-xs leading-6 text-amber-950' x-cloak x-show='parsingErrors.length'>
                        <p class='font-bold'>Contoh error parsing (maksimal 5)</p>
                        <ul class='mt-2 list-disc space-y-1 pl-5'>
                            <template x-for='(error, index) in parsingErrors' x-bind:key='`${error.code}-${index}`'>
                                <li><span x-text='error.message'></span><span x-show='Number.isInteger(error.row)' x-text='` — baris ${error.row + 2}`'></span></li>
                            </template>
                        </ul>
                    </div>
                </section>

                <section class='surface-card p-5 sm:p-7' x-cloak x-show='initialSummary' aria-labelledby='initial-summary-title'>
                    <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Data belum diubah</p>
                    <h2 id='initial-summary-title' class='mt-2 text-2xl font-bold text-navy'>Ringkasan Data Awal</h2>
                    <dl class='mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3'>
                        <template x-for='stat in [
                            ["Baris", initialSummary?.rowCount],
                            ["Kolom", initialSummary?.columnCount],
                            ["Baris kosong", initialSummary?.emptyRowCount],
                            ["Duplikat", initialSummary?.duplicateRowCount],
                            ["Kolom kosong", initialSummary?.emptyColumnCount],
                            ["Nama kolom perlu dirapikan", initialSummary?.headersNeedingNormalization],
                            ["Error parsing", initialSummary?.parsingErrorCount],
                            ["Sheet", initialSummary?.sheetCount],
                        ]' x-bind:key='stat[0]'>
                            <div class='data-cleaner-stat'><dt x-text='stat[0]'></dt><dd x-text='Number(stat[1] || 0).toLocaleString("id-ID")'></dd></div>
                        </template>
                    </dl>
                </section>

                <section class='surface-card p-5 sm:p-7' x-cloak x-show='initialSummary' aria-labelledby='cleaning-options-title'>
                    <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Langkah 3</p>
                    <h2 id='cleaning-options-title' class='mt-2 text-2xl font-bold text-navy'>Pilih Pembersihan</h2>
                    <fieldset class='mt-5 space-y-3'>
                        <legend class='sr-only'>Jenis pembersihan data</legend>
                        @foreach([
                            ['removeEmptyRows', 'Hapus Baris Kosong', 'Menghapus baris yang seluruh nilainya kosong.'],
                            ['removeDuplicates', 'Hapus Data Duplikat', 'Menghapus baris dengan seluruh nilai yang sama dan mempertahankan baris pertama.'],
                            ['trimText', 'Rapikan Spasi pada Teks', 'Menghapus spasi di awal, akhir, dan spasi berulang pada nilai teks.'],
                            ['normalizeHeaders', 'Normalisasi Nama Kolom', 'Mengubah nama kolom menjadi format snake_case yang konsisten.'],
                            ['removeEmptyColumns', 'Hapus Kolom Kosong', 'Menghapus kolom yang tidak memiliki nilai pada seluruh baris.'],
                        ] as [$key, $title, $description])
                            <label class='data-cleaner-option'>
                                <input class='mt-1 h-5 w-5 shrink-0 accent-navy' type='checkbox' x-model='options.{{ $key }}'>
                                <span><span class='block font-bold text-navy'>{{ $title }}</span><span class='mt-1 block text-xs leading-5 text-muted'>{{ $description }}</span></span>
                            </label>
                        @endforeach
                    </fieldset>

                    <button type='button' class='mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-navy px-5 py-3 text-sm font-bold text-white transition hover:bg-[#162b4d] focus:outline-none focus:ring-2 focus:ring-gold focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50' x-on:click='runCleaning' x-bind:disabled='isProcessing || !initialSummary'>
                        <span x-show='!isProcessing'>Proses Pembersihan</span>
                        <span x-show='isProcessing'>Sedang Memproses…</span>
                    </button>
                </section>
            </div>

            <div class='min-w-0 space-y-6'>
                <section class='surface-card p-5 sm:p-7' x-cloak x-show='cleaningSummary' aria-labelledby='result-summary-title'>
                    <div class='flex flex-wrap items-start justify-between gap-3'>
                        <div>
                            <p class='text-xs font-bold uppercase tracking-[0.18em] text-emerald-700'>Pembersihan selesai</p>
                            <h2 id='result-summary-title' class='mt-2 text-2xl font-bold text-navy'>Ringkasan Hasil</h2>
                        </div>
                        <span class='rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800'>Periksa sebelum unduh</span>
                    </div>
                    <dl class='mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3'>
                        <template x-for='stat in [
                            ["Baris awal", cleaningSummary?.initialRows],
                            ["Baris akhir", cleaningSummary?.finalRows],
                            ["Kolom awal", cleaningSummary?.initialColumns],
                            ["Kolom akhir", cleaningSummary?.finalColumns],
                            ["Baris kosong dihapus", cleaningSummary?.emptyRowsRemoved],
                            ["Duplikat dihapus", cleaningSummary?.duplicatesRemoved],
                            ["Kolom kosong dihapus", cleaningSummary?.emptyColumnsRemoved],
                            ["Nama kolom dinormalisasi", cleaningSummary?.headersNormalized],
                            ["Nilai teks dirapikan", cleaningSummary?.textValuesTrimmed],
                        ]' x-bind:key='stat[0]'>
                            <div class='data-cleaner-stat'><dt x-text='stat[0]'></dt><dd x-text='Number(stat[1] || 0).toLocaleString("id-ID")'></dd></div>
                        </template>
                    </dl>
                    <p class='mt-5 rounded-xl bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-950'>Periksa preview hasil sebelum mengunduh file.</p>

                    <div class='mt-4 grid gap-3 sm:grid-cols-2' x-show='headerChanges.length || removedHeaders.length'>
                        <div class='rounded-xl border border-navy/10 p-4' x-show='headerChanges.length'>
                            <p class='text-sm font-bold text-navy'>Perubahan nama kolom</p>
                            <ul class='mt-2 max-h-40 space-y-1 overflow-y-auto text-xs text-muted'>
                                <template x-for='change in headerChanges.slice(0, 20)' x-bind:key='change.index'>
                                    <li><span x-text='change.original || `(kosong ${change.index + 1})`'></span> → <strong class='text-navy' x-text='change.normalized'></strong></li>
                                </template>
                            </ul>
                        </div>
                        <div class='rounded-xl border border-navy/10 p-4' x-show='removedHeaders.length'>
                            <p class='text-sm font-bold text-navy'>Kolom kosong dihapus</p>
                            <p class='mt-2 text-xs leading-5 text-muted' x-text='removedHeaders.join(", ")'></p>
                        </div>
                    </div>
                </section>

                <section class='surface-card min-w-0 overflow-hidden' x-cloak x-show='initialSummary' aria-labelledby='preview-title'>
                    <div class='border-b border-navy/10 p-5 sm:p-7'>
                        <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Langkah 4</p>
                        <h2 id='preview-title' class='mt-2 text-2xl font-bold text-navy'>Preview Data</h2>
                        <div class='mt-4 inline-flex rounded-xl bg-navy/5 p-1' role='tablist' aria-label='Pilih preview data' x-on:keydown='handleTabKey($event)'>
                            <button id='data-tab-original' type='button' role='tab' class='data-cleaner-tab' x-bind:class="activePreview === 'original' ? 'is-active' : ''" x-bind:aria-selected="(activePreview === 'original').toString()" aria-controls='data-panel-preview' x-bind:tabindex="activePreview === 'original' ? 0 : -1" x-on:click="setPreview('original')">Data Awal</button>
                            <button id='data-tab-cleaned' type='button' role='tab' class='data-cleaner-tab' x-show='cleaningSummary' x-bind:class="activePreview === 'cleaned' ? 'is-active' : ''" x-bind:aria-selected="(activePreview === 'cleaned').toString()" aria-controls='data-panel-preview' x-bind:tabindex="activePreview === 'cleaned' ? 0 : -1" x-on:click="setPreview('cleaned')">Hasil Bersih</button>
                        </div>
                        <p class='mt-3 text-xs leading-5 text-muted' x-show='previewTotal() > 100' x-text='`Preview menampilkan 100 baris pertama dari total ${previewTotal().toLocaleString("id-ID")} baris.`'></p>
                    </div>

                    <div id='data-panel-preview' role='tabpanel' x-bind:aria-labelledby='`data-tab-${activePreview}`'>
                        <div class='data-cleaner-table-wrap' x-show='previewRows().length'>
                            <table class='data-cleaner-table'>
                                <caption class='sr-only'>Preview data CSV atau Excel</caption>
                                <thead><tr><th scope='col'>#</th><template x-for='(header, index) in previewHeaders()' x-bind:key='`header-${index}`'><th scope='col' x-text='header || `(Kolom ${index + 1})`'></th></template></tr></thead>
                                <tbody><template x-for='(row, rowIndex) in previewRows()' x-bind:key='`row-${rowIndex}`'><tr><th scope='row' x-text='rowIndex + 1'></th><template x-for='(value, columnIndex) in row' x-bind:key='`cell-${rowIndex}-${columnIndex}`'><td><span class='data-cleaner-cell' x-bind:title='String(value ?? "")' x-text='value ?? ""'></span></td></template></tr></template></tbody>
                            </table>
                        </div>
                        <div class='p-8 text-center' x-show='!previewRows().length'>
                            <p class='font-bold text-navy'>Tidak ada baris untuk ditampilkan.</p>
                            <p class='mt-2 text-sm text-muted' x-show='cleaningSummary'>Seluruh baris terhapus berdasarkan pilihan pembersihan. Tombol download dinonaktifkan.</p>
                        </div>
                    </div>
                </section>

                <section class='surface-card p-5 sm:p-7' x-cloak x-show='cleaningSummary' aria-labelledby='download-title'>
                    <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Langkah 5</p>
                    <h2 id='download-title' class='mt-2 text-2xl font-bold text-navy'>Unduh Data</h2>
                    <fieldset class='mt-5'>
                        <legend class='text-sm font-bold text-navy'>Format Unduhan</legend>
                        <div class='mt-3 grid gap-3 sm:grid-cols-2'>
                            <label class='data-cleaner-option'><input class='h-5 w-5 accent-navy' type='radio' name='output-format' value='csv' x-model='outputFormat'><span><strong class='block text-navy'>CSV</strong><span class='text-xs text-muted'>UTF-8 BOM, delimiter koma</span></span></label>
                            <label class='data-cleaner-option'><input class='h-5 w-5 accent-navy' type='radio' name='output-format' value='xlsx' x-model='outputFormat'><span><strong class='block text-navy'>Excel XLSX</strong><span class='text-xs text-muted'>Workbook baru, satu sheet</span></span></label>
                        </div>
                    </fieldset>
                    <p class='mt-4 text-xs leading-5 text-muted'>Nilai yang berpotensi dijalankan sebagai formula spreadsheet diamankan pada file hasil.</p>
                    <div class='mt-6 grid gap-3 sm:grid-cols-2'>
                        <button type='button' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-gold px-5 py-3 text-sm font-bold text-navy transition hover:bg-[#e3b94f] focus:outline-none focus:ring-2 focus:ring-navy focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50' x-on:click='downloadResult' x-bind:disabled='!canDownload()'>Download Hasil</button>
                        <button type='button' class='inline-flex min-h-11 items-center justify-center rounded-xl border border-red-300 bg-white px-5 py-3 text-sm font-bold text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2' x-on:click='resetData'>Reset Data</button>
                    </div>
                </section>
            </div>
        </div>

        <section class='mt-8 rounded-2xl border border-navy/10 bg-white p-5 sm:p-8' aria-labelledby='limitations-title'>
            <h2 id='limitations-title' class='text-2xl font-bold text-navy'>Batasan Fitur</h2>
            <ul class='mt-5 grid gap-x-8 gap-y-3 text-sm leading-6 text-muted sm:grid-cols-2 lg:grid-cols-3'>
                @foreach([
                    'Hanya mendukung CSV dan XLSX.',
                    'CSV maksimal 10 MB dan XLSX maksimal 5 MB.',
                    'Maksimal 1.000.000 sel per file atau sheet agar browser tetap responsif.',
                    'Satu sheet diproses pada satu waktu.',
                    'Style Excel dan merged cell tidak dipertahankan.',
                    'Formula tidak dihitung dan macro tidak diproses.',
                    'Gambar dan komentar tidak dipertahankan.',
                    'Format tanggal tidak diperbaiki otomatis.',
                    'Tipe data tidak diubah otomatis.',
                    'Hasil perlu diperiksa kembali sebelum digunakan.',
                    'File tidak disimpan di server.',
                ] as $limitation)
                    <li class='flex gap-2'><span class='font-bold text-[#80520d]' aria-hidden='true'>•</span><span>{{ $limitation }}</span></li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
@endsection

@push('structured-data')
    <x-structured-data :data="[
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Pembersih CSV & Excel Gratis',
        'url' => route('free-tools.data-cleaner'),
        'applicationCategory' => 'UtilitiesApplication',
        'operatingSystem' => 'Any',
        'browserRequirements' => 'Requires JavaScript and a modern browser',
        'isAccessibleForFree' => true,
        'offers' => ['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'IDR'],
        'description' => 'Pembersih CSV dan Excel yang memproses seluruh data langsung di browser pengguna.',
    ]" />
@endpush
