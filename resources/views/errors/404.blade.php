@extends('layouts.public')
@section('title', 'Halaman Tidak Ditemukan | Jokiinlah')
@section('description', 'Halaman atau konten yang Anda cari tidak tersedia.')
@section('robots', 'noindex,nofollow')
@section('content')
<section class='hero-grid flex min-h-[65vh] items-center bg-navy py-20 text-white'>
    <div class='container-public text-center'>
        <p class='text-sm font-bold uppercase tracking-[0.2em] text-gold'>Error 404</p>
        <h1 class='mt-4 text-balance text-4xl font-bold sm:text-6xl'>Halaman Tidak Ditemukan</h1>
        <p class='mx-auto mt-5 max-w-2xl leading-8 text-white/75'>Alamat mungkin berubah, konten belum dipublikasikan, atau halaman sudah tidak tersedia.</p>
        <div class='mt-8 flex flex-col justify-center gap-3 sm:flex-row'>
            <x-primary-button :href="route('home')">Kembali ke Beranda</x-primary-button>
            <x-secondary-button :href="route('services.index')">Lihat Layanan</x-secondary-button>
        </div>
    </div>
</section>
@endsection
