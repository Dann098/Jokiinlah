<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <meta name='robots' content='noindex,nofollow'>
    <title>Akses Ditolak | Jokiinlah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class='bg-cream'>
    <main class='hero-grid flex min-h-screen items-center bg-navy px-5 py-16 text-white'>
        <div class='mx-auto max-w-2xl text-center'>
            <p class='text-sm font-bold uppercase tracking-[0.2em] text-gold'>Error 403</p>
            <h1 class='mt-4 text-balance text-4xl font-bold sm:text-6xl'>Akses Tidak Diizinkan</h1>
            <p class='mx-auto mt-5 max-w-xl leading-8 text-white/75'>Anda tidak memiliki izin untuk membuka data ini. Pastikan proyek atau dokumen terhubung dengan akun Anda.</p>
            <a href='{{ auth()->check() && auth()->user()->isCustomer() ? route('customer.dashboard') : route('home') }}' class='mt-8 inline-flex min-h-11 items-center rounded-xl bg-gold px-5 text-sm font-bold text-navy'>Kembali ke halaman aman</a>
        </div>
    </main>
</body>
</html>
