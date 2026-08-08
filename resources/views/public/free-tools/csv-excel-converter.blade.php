@extends('layouts.public')

@section('title')
Konverter CSV ke Excel & Excel ke CSV Gratis | Jokiinlah
@endsection
@section('description', 'Konversi CSV ke Excel XLSX atau Excel ke CSV secara gratis langsung di browser tanpa upload file ke server.')

@section('content')
<div class='csv-converter-shell bg-[#f4f6f9]' x-data='csvExcelConverter'>
    <section class='bg-navy text-white'>
        <div class='container-public py-10 sm:py-14'>
            <x-breadcrumb :items="['Fitur Gratis' => route('free-tools.index'), 'Konverter CSV & Excel' => null]" />
            <div class='mt-7 max-w-4xl'>
                <div class='flex flex-wrap gap-2' aria-label='Karakteristik fitur'>
                    @foreach(['Gratis', 'CSV ↔ XLSX', 'Tanpa Login', 'Diproses di Browser'] as $label)
                        <span class='inline-flex min-h-8 items-center rounded-full border border-white/20 bg-white/10 px-3 text-xs font-bold uppercase tracking-wider text-gold'>{{ $label }}</span>
                    @endforeach
                </div>
                <h1 class='mt-5 text-balance text-3xl font-bold leading-tight sm:text-5xl'>Konversi CSV ke Excel &amp; Excel ke CSV</h1>
                <p class='mt-5 max-w-3xl text-sm leading-7 text-white/75 sm:text-base'>Ubah file CSV menjadi Excel XLSX atau Excel menjadi CSV langsung di browser tanpa mengunggah file ke server.</p>
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
                    <p class='mt-1 leading-6'>Data hanya berada di memori halaman dan dibuang saat reset atau halaman ditutup.</p>
                </div>
            </div>
        </div>

        <ol class='mt-6 grid gap-2 sm:grid-cols-3' aria-label='Tahapan konversi data'>
            @foreach(['Pilih File', 'Periksa Data', 'Konversi & Download'] as $step)
                <li class='rounded-xl border border-navy/10 bg-white px-3 py-3 text-center text-xs font-bold text-navy'>
                    {{ $loop->iteration }}. {{ $step }}
                </li>
            @endforeach
        </ol>

        <div class='mt-6' role='alert' aria-live='assertive' hidden>
            <div class='rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-900'></div>
        </div>

        <div class='mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]'>
            <div class='space-y-6'>
                <section class='surface-card p-5 sm:p-7' aria-labelledby='converter-upload-title'>
                    <div class='flex flex-wrap items-start justify-between gap-4'>
                        <div>
                            <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Langkah 1</p>
                            <h2 id='converter-upload-title' class='mt-2 text-2xl font-bold text-navy'>Pilih File</h2>
                        </div>
                        <span class='rounded-full bg-navy/5 px-3 py-1 text-xs font-bold text-navy'>CSV ≤ 10 MB · XLSX ≤ 5 MB</span>
                    </div>

                    <label for='csv-excel-converter-file' class='csv-converter-dropzone mt-5'>
                        <input id='csv-excel-converter-file' class='sr-only' type='file' accept='.csv,.xlsx'>
                        <span class='flex h-12 w-12 items-center justify-center rounded-full bg-navy text-xl text-gold' aria-hidden='true'>↑</span>
                        <span class='mt-3 block text-base font-bold text-navy'>Klik untuk memilih atau tarik file ke area ini</span>
                        <span class='mt-1 block text-sm leading-6 text-muted'>Satu file .csv atau .xlsx untuk setiap proses.</span>
                    </label>
                    <div class='mt-4 min-h-6 text-sm font-semibold text-navy' aria-live='polite'></div>

                    <div class='mt-5'>
                        <label class='text-sm font-bold text-navy' for='csv-excel-converter-sheet'>Pilih Sheet yang akan dikonversi</label>
                        <select id='csv-excel-converter-sheet' class='mt-2 min-h-11 w-full rounded-xl border border-navy/20 bg-white px-3 text-sm text-charcoal focus:border-navy focus:outline-none focus:ring-2 focus:ring-gold/40' disabled>
                            <option>Pilih file XLSX terlebih dahulu</option>
                        </select>
                        <p class='mt-2 text-xs leading-5 text-muted'>Satu sheet Excel dikonversi pada satu waktu.</p>
                    </div>
                </section>

                <section class='surface-card p-5 sm:p-7' aria-labelledby='converter-settings-title'>
                    <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Pengaturan otomatis</p>
                    <h2 id='converter-settings-title' class='mt-2 text-2xl font-bold text-navy'>Arah Konversi</h2>
                    <p class='mt-4 text-sm leading-7 text-muted'>Arah konversi ditentukan otomatis dari format file. CSV akan menjadi Excel XLSX, sedangkan XLSX akan menjadi CSV.</p>
                    <p class='mt-4 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-6 text-amber-950'>Formula Excel tidak dihitung ulang oleh fitur ini.</p>
                    <button type='button' class='mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-navy px-5 py-3 text-sm font-bold text-white opacity-50' disabled>Konversi</button>
                </section>
            </div>

            <div class='min-w-0 space-y-6'>
                <section class='surface-card min-w-0 overflow-hidden' aria-labelledby='converter-preview-title'>
                    <div class='border-b border-navy/10 p-5 sm:p-7'>
                        <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Langkah 2</p>
                        <h2 id='converter-preview-title' class='mt-2 text-2xl font-bold text-navy'>Preview Data</h2>
                        <p class='mt-3 text-sm leading-6 text-muted'>Pilih file untuk menampilkan maksimal 100 baris pertama. Semua baris tetap disertakan dalam file hasil.</p>
                    </div>
                    <div class='p-8 text-center text-sm text-muted'>Belum ada data untuk ditampilkan.</div>
                </section>

                <section class='surface-card p-5 sm:p-7' aria-labelledby='converter-download-title'>
                    <p class='text-xs font-bold uppercase tracking-[0.18em] text-[#80520d]'>Langkah 3</p>
                    <h2 id='converter-download-title' class='mt-2 text-2xl font-bold text-navy'>Konversi &amp; Download</h2>
                    <p class='mt-4 text-sm leading-7 text-muted'>File hasil menggunakan nama aman dengan akhiran <strong>-konversi</strong>.</p>
                    <div class='mt-6 grid gap-3 sm:grid-cols-2'>
                        <button type='button' class='inline-flex min-h-11 items-center justify-center rounded-xl bg-gold px-5 py-3 text-sm font-bold text-navy opacity-50' disabled>Download Hasil</button>
                        <button type='button' class='inline-flex min-h-11 items-center justify-center rounded-xl border border-red-300 bg-white px-5 py-3 text-sm font-bold text-red-800'>Reset Data</button>
                    </div>
                </section>
            </div>
        </div>

        <section class='mt-8 rounded-2xl border border-navy/10 bg-white p-5 sm:p-8' aria-labelledby='converter-limitations-title'>
            <h2 id='converter-limitations-title' class='text-2xl font-bold text-navy'>Batasan Fitur</h2>
            <ul class='mt-5 grid gap-x-8 gap-y-3 text-sm leading-6 text-muted sm:grid-cols-2 lg:grid-cols-3'>
                @foreach([
                    'Hanya mendukung file CSV dan XLSX.',
                    'CSV maksimal 10 MB dan XLSX maksimal 5 MB.',
                    'Hanya satu file untuk setiap proses.',
                    'Satu sheet Excel untuk setiap konversi.',
                    'Formula tidak dihitung ulang.',
                    'Macro tidak didukung.',
                    'Style workbook asli tidak dipertahankan sepenuhnya.',
                    'File tidak disimpan di server.',
                    'Hasil sebaiknya diperiksa kembali.',
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
        'name' => 'Konverter CSV & Excel Gratis',
        'url' => route('free-tools.csv-excel-converter'),
        'applicationCategory' => 'UtilitiesApplication',
        'operatingSystem' => 'Any',
        'browserRequirements' => 'Requires JavaScript and a modern browser',
        'isAccessibleForFree' => true,
        'offers' => ['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'IDR'],
        'description' => 'Konverter CSV dan Excel yang memproses seluruh data langsung di browser pengguna.',
    ]" />
@endpush
