@extends('layouts.public')
@section('title', 'Fitur Gratis | Jokiinlah')
@section('description', 'Gunakan fitur gratis Jokiinlah tanpa login untuk membuat CV, membersihkan data, dan mengonversi dokumen Word ke PDF.')
@section('content')
<section class='bg-navy py-16 text-white'>
    <div class='container-public'>
        <x-breadcrumb :items="['Fitur Gratis' => null]" />
        <p class='mt-7 text-xs font-bold uppercase tracking-[0.2em] text-rose'>Alat Mandiri</p>
        <h1 class='mt-3 text-balance text-4xl font-bold sm:text-5xl'>Fitur Gratis</h1>
        <p class='mt-5 max-w-3xl leading-8 text-white/75'>Gunakan alat praktis tanpa akun untuk membantu menyiapkan dokumen profesional secara mandiri.</p>
    </div>
</section>

<section class='section-space'>
    <div class='container-public'>
        <div class='max-w-3xl' data-reveal>
            <p class='text-xs font-bold uppercase tracking-[0.2em] text-[#80520d]'>Tersedia Sekarang</p>
            <h2 class='mt-3 text-balance text-3xl font-bold text-navy sm:text-4xl'>Alat praktis untuk dokumen dan data</h2>
            <p class='mt-5 leading-8 text-muted'>Pilih alat yang Anda butuhkan untuk membuat CV, membersihkan data, atau mengonversi dokumen tanpa membuat akun.</p>
        </div>

        <article class='surface-card mt-10 max-w-3xl overflow-hidden' data-reveal>
            <div class='border-b border-navy/10 bg-cream px-6 py-5 sm:px-8'>
                <div class='flex flex-wrap items-center justify-between gap-3'>
                    <span class='inline-flex min-h-8 items-center rounded-full bg-navy px-4 text-xs font-bold uppercase tracking-wider text-gold'>Gratis</span>
                    <span class='text-sm font-semibold text-muted'>Template Academic Classic</span>
                </div>
            </div>
            <div class='p-6 sm:p-8'>
                <h2 class='text-2xl font-bold text-navy sm:text-3xl'>Pembuat CV ATS Gratis</h2>
                <p class='mt-4 leading-8 text-muted'>Buat CV satu kolom yang bersih, formal, dan mudah dibaca. Dirancang agar sederhana dan mudah dibaca oleh perekrut maupun sistem penyaringan dokumen.</p>
                <ul class='mt-6 grid gap-3 text-sm font-semibold text-charcoal sm:grid-cols-2' aria-label='Keunggulan fitur'>
                    <li class='flex items-center gap-3'><span class='flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy text-gold' aria-hidden='true'>✓</span>Tanpa login</li>
                    <li class='flex items-center gap-3'><span class='flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-navy text-gold' aria-hidden='true'>✓</span>Data tidak disimpan di server</li>
                </ul>
                <div class='mt-8'>
                    <x-primary-button :href="route('free-tools.cv-builder')">Buat CV</x-primary-button>
                </div>
            </div>
        </article>

        <article class='surface-card mt-8 max-w-3xl overflow-hidden' data-reveal>
            <div class='border-b border-navy/10 bg-cream px-6 py-5 sm:px-8'>
                <div class='flex flex-wrap items-center gap-2'>
                    <x-badge>Gratis</x-badge>
                    <x-badge>CSV &amp; XLSX</x-badge>
                    <x-badge>Tanpa Upload Server</x-badge>
                </div>
            </div>
            <div class='p-6 sm:p-8'>
                <h2 class='text-2xl font-bold text-navy sm:text-3xl'>Pembersih CSV &amp; Excel Gratis</h2>
                <p class='mt-4 leading-8 text-muted'>Bersihkan baris kosong, data duplikat, spasi berlebih, dan nama kolom yang tidak konsisten dari file CSV atau Excel langsung di browser.</p>
                <div class='mt-8'>
                    <x-primary-button :href="route('free-tools.data-cleaner')">Bersihkan Data</x-primary-button>
                </div>
            </div>
        </article>

        <article class='surface-card mt-8 max-w-3xl overflow-hidden' data-reveal>
            <div class='border-b border-navy/10 bg-cream px-6 py-5 sm:px-8'>
                <div class='flex flex-wrap items-center gap-2'>
                    <x-badge>Gratis</x-badge>
                    <x-badge>DOC &amp; DOCX</x-badge>
                    <x-badge>Tanpa Login</x-badge>
                </div>
            </div>
            <div class='p-6 sm:p-8'>
                <h2 class='text-2xl font-bold text-navy sm:text-3xl'>Word ke PDF Gratis</h2>
                <p class='mt-4 leading-8 text-muted'>Konversikan file Microsoft Word menjadi PDF secara cepat tanpa perlu membuat akun.</p>
                <div class='mt-8'>
                    <x-primary-button :href="route('free-tools.word-to-pdf')">Convert ke PDF</x-primary-button>
                </div>
            </div>
        </article>

        <p class='mt-8 max-w-3xl text-sm leading-7 text-muted'>Butuh bantuan profesional untuk menyunting dokumen? <a class='font-bold text-navy underline decoration-gold decoration-2 underline-offset-4' href='{{ route('services.index') }}'>Lihat layanan Jokiinlah</a>.</p>
    </div>
</section>
@endsection
