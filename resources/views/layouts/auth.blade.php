<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <meta name='csrf-token' content='{{ csrf_token() }}'>
    <title>{{ $title ?? 'Jokiinlah — Pendampingan Akademik & Digital' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class='min-h-screen bg-[#F8F4EE] text-[#202735] antialiased'>
    <main class='mx-auto flex min-h-screen max-w-md items-center px-6 py-12'>
        <section class='w-full rounded-2xl border border-black/10 bg-white p-8 shadow-xl shadow-[#0B1933]/10'>
            <a href='{{ route('home') }}' class='text-xl font-semibold text-[#0B1933]'>Jokiinlah</a>
            <p class='mt-1 text-sm text-[#687386]'>Academic and Digital Solution</p>
            @if (session('status'))
                <div class='mt-5 rounded-xl bg-green-50 p-3 text-sm text-green-800'>{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class='mt-5 rounded-xl bg-red-50 p-3 text-sm text-red-800'>{{ $errors->first() }}</div>
            @endif
            <div class='mt-7'>{{ $slot }}</div>
        </section>
    </main>
</body>
</html>
