@extends('layouts.public')

@section('title', 'Pembuat CV ATS Gratis | Jokiinlah')
@section('description', 'Buat CV profesional dengan template akademik klasik secara gratis. Isi data, lihat preview, lalu cetak atau simpan sebagai PDF tanpa login.')

@section('content')
<div class='cv-builder-shell' x-data="cvBuilder" data-storage-key='jokiinlah_cv_academic_classic_v1' x-on:keydown.escape.window='dismissToast'>
    <section class='cv-builder-hero no-print'>
        <div class='container-public py-9 sm:py-12'>
            <x-breadcrumb :items="['Fitur Gratis' => route('free-tools.index'), 'Pembuat CV' => null]" />
            <div class='mt-7 grid gap-6 lg:grid-cols-[1fr_auto] lg:items-end'>
                <div>
                    <p class='text-xs font-bold uppercase tracking-[0.22em] text-gold'>Pembuat CV ATS Gratis</p>
                    <h1 class='mt-3 max-w-4xl text-3xl font-bold leading-tight text-white sm:text-5xl'>Buat CV Profesional Secara Gratis</h1>
                    <p class='mt-4 max-w-3xl text-sm leading-7 text-white/75 sm:text-base'>Isi data Anda, lihat hasilnya secara langsung, lalu cetak atau simpan sebagai PDF. Data diproses di perangkat Anda dan tidak disimpan di server.</p>
                </div>
                <div class='rounded-2xl border border-white/15 bg-white/10 px-5 py-4 text-sm text-white/85'>
                    <p class='font-bold text-gold'>Academic Classic</p>
                    <p class='mt-1 max-w-xs leading-6'>Template satu kolom dengan struktur yang bersih, formal, dan mudah dibaca.</p>
                </div>
            </div>
        </div>
    </section>

    <div class='container-public no-print py-5'>
        <x-free-tools.privacy-notice compact />
    </div>

    <div class='cv-action-bar no-print'>
        <div class='container-public flex flex-wrap items-center gap-2 py-3'>
            <button type='button' class='cv-action-button' x-on:click='loadSampleData'>Muat Data Contoh</button>
            <button type='button' class='cv-action-button' x-on:click='clearAllData'>Reset</button>
            <button type='button' class='cv-action-button cv-action-button--danger' x-on:click='clearAllData'>Hapus Semua Data</button>
            <button type='button' class='cv-action-button cv-mobile-only' x-on:click="setMobileTab('preview')">Preview</button>
            <button type='button' class='cv-action-button cv-action-button--primary' x-on:click='window.print()'>Cetak / Simpan PDF</button>
            <span class='ml-auto hidden text-xs font-semibold text-emerald-800 sm:inline' x-text='draftStatusLabel()' aria-live='polite'></span>
        </div>
    </div>

    <div class='container-public no-print pt-5 lg:hidden'>
        <div class='grid grid-cols-2 rounded-xl bg-navy/5 p-1' role='tablist' aria-label='Tampilan pembuat CV'>
            <button type='button' role='tab' class='cv-mobile-tab' x-bind:class="mobileTab === 'form' ? 'is-active' : ''" x-bind:aria-selected="(mobileTab === 'form').toString()" x-on:click="setMobileTab('form')">Isi Data</button>
            <button type='button' role='tab' class='cv-mobile-tab' x-bind:class="mobileTab === 'preview' ? 'is-active' : ''" x-bind:aria-selected="(mobileTab === 'preview').toString()" x-on:click="setMobileTab('preview')">Preview CV</button>
        </div>
    </div>

    <section class='container-public cv-workspace'>
        <aside class='cv-editor no-print' x-bind:class="mobileTab !== 'form' ? 'cv-mobile-hidden' : ''">
            @include('public.free-tools.partials.form')
        </aside>

        <div class='cv-preview-column' x-bind:class="mobileTab !== 'preview' ? 'cv-mobile-hidden' : ''">
            <div class='cv-preview-toolbar no-print'>
                <div>
                    <p class='text-sm font-bold text-navy'>Preview Academic Classic</p>
                    <p class='text-xs text-muted'>Zoom hanya mengubah tampilan layar.</p>
                </div>
                <div class='flex items-center gap-1' aria-label='Atur zoom preview'>
                    <template x-for='value in [75, 90, 100]' x-bind:key='value'>
                        <button type='button' class='cv-zoom-button' x-bind:class="zoom === value ? 'is-active' : ''" x-on:click='setZoom(value)' x-text="value + '%'" x-bind:aria-pressed="(zoom === value).toString()"></button>
                    </template>
                </div>
            </div>
            <div class='cv-paper-scroller' data-cv-preview-scroller>
                <div class='cv-paper-scale' x-bind:style="`--cv-zoom: ${zoom / 100}`">
                    @include('public.free-tools.partials.preview')
                </div>
            </div>
            <p class='mt-3 text-center text-xs leading-5 text-muted no-print'>Pada dialog cetak, pilih “Save as PDF” atau “Simpan sebagai PDF”.</p>
        </div>
    </section>

    <div class='cv-toast no-print' x-cloak x-show='toast.visible' x-transition role='status' aria-live='polite'>
        <span x-text='toast.message'></span>
        <button type='button' x-on:click='dismissToast' aria-label='Tutup notifikasi'>×</button>
    </div>
</div>
@endsection

@push('structured-data')
    <x-structured-data :data="[
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Pembuat CV ATS Gratis - Academic Classic',
        'url' => route('free-tools.cv-builder'),
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Any',
        'isAccessibleForFree' => true,
        'offers' => ['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'IDR'],
        'description' => 'Pembuat CV satu kolom yang diproses sepenuhnya di browser pengguna.',
    ]" />
@endpush
