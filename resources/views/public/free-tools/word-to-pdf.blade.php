@extends('layouts.public')
@section('title', 'Convert Word ke PDF Gratis | Jokiinlah')
@section('description', 'Convert dokumen Word DOC dan DOCX menjadi PDF secara gratis tanpa perlu membuat akun.')

@section('content')
<section class='bg-navy py-14 text-white sm:py-16'>
    <div class='container-public'>
        <x-breadcrumb :items="['Fitur Gratis' => route('free-tools.index'), 'Word ke PDF' => null]" />
        <p class='mt-7 text-xs font-bold uppercase tracking-[0.2em] text-rose'>Fitur Gratis</p>
        <h1 class='mt-3 text-balance text-4xl font-bold sm:text-5xl'>Convert Word ke PDF Gratis</h1>
        <p class='mt-5 max-w-3xl leading-8 text-white/75'>Ubah dokumen DOC atau DOCX menjadi PDF dengan mudah. File digunakan sementara untuk proses konversi dan tidak disimpan secara permanen.</p>
    </div>
</section>

<div class='word-converter-shell section-space'>
    <div class='container-public'>
        <ol class='grid gap-3 sm:grid-cols-3' aria-label='Alur konversi Word ke PDF'>
            @foreach(['1. Pilih Dokumen', '2. Convert', '3. Download PDF'] as $step)
                <li class='rounded-xl border border-navy/10 bg-white px-4 py-4 text-center text-sm font-bold text-navy shadow-sm'>{{ $step }}</li>
            @endforeach
        </ol>

        <div class='mx-auto mt-8 max-w-3xl'>
            <form
                class='surface-card overflow-hidden'
                method='POST'
                action='{{ route('free-tools.word-to-pdf.convert') }}'
                enctype='multipart/form-data'
                x-data='wordToPdfUpload({{ $maximumMegabytes }}, {{ $conversionTimeout }})'
                x-on:submit='submit($event)'
            >
                @csrf
                <div class='border-b border-navy/10 bg-cream px-5 py-4 sm:px-8'>
                    <h2 class='text-xl font-bold text-navy'>Pilih Dokumen Word</h2>
                    <p id='word-file-help' class='mt-1 text-sm text-muted'>Format DOC atau DOCX, satu file, maksimal {{ $maximumMegabytes }} MB.</p>
                </div>

                <div class='p-5 sm:p-8'>
                    <div
                        class='word-converter-dropzone'
                        x-bind:class="dragging ? 'is-dragging' : ''"
                        x-on:dragover.prevent='dragging = true'
                        x-on:dragleave.prevent='dragging = false'
                        x-on:drop.prevent='onDrop($event)'
                    >
                        <svg class='h-12 w-12 text-[#80520d]' aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7'>
                            <path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z'/><path d='M14 2v6h6M12 18v-6m-3 3 3-3 3 3'/>
                        </svg>
                        <p class='mt-4 font-bold text-navy'>Tarik dokumen ke area ini</p>
                        <p class='mt-1 text-sm text-muted'>atau pilih melalui tombol berikut</p>
                        <button class='mt-5 min-h-11 rounded-xl bg-navy px-5 py-3 text-sm font-bold text-white transition hover:bg-navy/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold' type='button' x-on:click='chooseFile' x-bind:disabled='submitting'>Pilih File</button>
                        <label class='sr-only' for='word-document'>Pilih file Microsoft Word</label>
                        <input
                            id='word-document'
                            class='sr-only'
                            type='file'
                            name='document'
                            accept='.doc,.docx'
                            aria-describedby='word-file-help'
                            x-ref='document'
                            x-on:change='onFileChange($event)'
                        >
                    </div>

                    <div class='mt-4 min-w-0 rounded-xl bg-slate-50 p-4' x-show='file' x-cloak>
                        <p class='text-xs font-bold uppercase tracking-wider text-muted'>File dipilih</p>
                        <p class='mt-1 break-all font-bold text-navy' x-text='file?.name'></p>
                        <p class='mt-1 text-sm text-muted' x-text='fileSize()'></p>
                    </div>

                    <div class='mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800' role='alert' x-show='clientError || {{ $errors->has('document') ? 'true' : 'false' }}' x-cloak>
                        @error('document')<p>{{ $message }}</p>@enderror
                        <p x-show='clientError' x-text='clientError'></p>
                    </div>

                    <button class='mt-6 flex min-h-11 w-full items-center justify-center gap-3 rounded-xl bg-navy px-5 py-3 font-bold text-white transition hover:bg-navy/90 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold' type='submit' x-bind:disabled='submitting || !file'>
                        <svg x-show='submitting' class='h-5 w-5 animate-spin' aria-hidden='true' viewBox='0 0 24 24' fill='none'><circle class='opacity-25' cx='12' cy='12' r='9' stroke='currentColor' stroke-width='3'/><path class='opacity-75' fill='currentColor' d='M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z'/></svg>
                        <span x-text="submitting ? 'Sedang mengonversi dokumen...' : 'Convert ke PDF'">Convert ke PDF</span>
                    </button>
                    <p class='sr-only' aria-live='polite' x-text="submitting ? 'Sedang mengonversi dokumen.' : ''"></p>
                </div>
            </form>

            <aside class='mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950' aria-label='Informasi privasi file'>
                <h2 class='font-bold'>Privasi file Anda</h2>
                <p class='mt-2 text-sm leading-6'>File digunakan sementara untuk proses konversi dan tidak disimpan secara permanen. File sumber dan hasil dibersihkan setelah proses selesai.</p>
            </aside>

            <aside class='mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950'>
                <p class='text-sm leading-6'>Hasil PDF dapat sedikit berbeda apabila dokumen menggunakan font yang tidak tersedia pada server.</p>
            </aside>

            <section class='mt-8 rounded-2xl border border-navy/10 bg-white p-5 sm:p-8' aria-labelledby='word-limitations-title'>
                <h2 id='word-limitations-title' class='text-2xl font-bold text-navy'>Batasan Fitur</h2>
                <ul class='mt-5 grid gap-x-8 gap-y-3 text-sm leading-6 text-muted sm:grid-cols-2'>
                    @foreach([
                        'Mendukung DOC dan DOCX.',
                        'Maksimal '.$maximumMegabytes.' MB.',
                        'Hanya satu file per proses.',
                        'Tidak mendukung DOCM.',
                        'Tidak mendukung file terenkripsi atau dilindungi password.',
                        'Hasil dapat berbeda jika font tidak tersedia.',
                        'File hanya digunakan sementara.',
                        'File tidak disimpan secara permanen.',
                    ] as $limitation)
                        <li class='flex gap-2'><span class='font-bold text-[#80520d]' aria-hidden='true'>•</span><span>{{ $limitation }}</span></li>
                    @endforeach
                </ul>
            </section>

            <p class='mt-8 text-center text-sm text-muted'><a class='font-bold text-navy underline decoration-gold decoration-2 underline-offset-4' href='{{ route('free-tools.index') }}'>Kembali ke Fitur Gratis</a></p>
        </div>
    </div>
</div>
@endsection

@push('structured-data')
    <x-structured-data :data="[
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Convert Word ke PDF Gratis',
        'url' => route('free-tools.word-to-pdf'),
        'applicationCategory' => 'UtilitiesApplication',
        'operatingSystem' => 'Any',
        'isAccessibleForFree' => true,
        'offers' => ['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'IDR'],
        'description' => 'Convert dokumen Word DOC dan DOCX menjadi PDF secara gratis tanpa perlu membuat akun.',
    ]" />
@endpush
