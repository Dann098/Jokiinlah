<x-auth-layout title='Verifikasi Email — Jokiinlah'>
    <h1 class='text-2xl font-semibold text-[#0B1933]'>Verifikasi email Anda</h1>
    <p class='mt-3 text-sm text-[#687386]'>Buka tautan yang kami kirimkan sebelum mengakses portal pelanggan.</p>
    <form method='POST' action='{{ route('verification.send') }}' class='mt-6'>@csrf<button class='w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white'>Kirim ulang email</button></form>
    <form method='POST' action='{{ route('logout') }}' class='mt-3'>@csrf<button class='w-full rounded-xl border p-3'>Keluar</button></form>
</x-auth-layout>
