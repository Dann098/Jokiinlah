<x-auth-layout title='Daftar — Jokiinlah'>
    <h1 class='text-2xl font-semibold text-[#0B1933]'>Buat akun pelanggan</h1>
    <form method='POST' action='{{ route('register') }}' class='mt-6 space-y-4'>
        @csrf
        <label class='block text-sm'>Nama lengkap<input class='mt-1 w-full rounded-xl border p-3' name='name' value='{{ old('name') }}' required></label>
        <label class='block text-sm'>Email<input class='mt-1 w-full rounded-xl border p-3' type='email' name='email' value='{{ old('email') }}' required></label>
        <label class='block text-sm'>WhatsApp<input class='mt-1 w-full rounded-xl border p-3' name='phone' value='{{ old('phone') }}'></label>
        <label class='block text-sm'>Password<input class='mt-1 w-full rounded-xl border p-3' type='password' name='password' required></label>
        <label class='block text-sm'>Konfirmasi password<input class='mt-1 w-full rounded-xl border p-3' type='password' name='password_confirmation' required></label>
        <button class='w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white' type='submit'>Daftar</button>
    </form>
</x-auth-layout>
