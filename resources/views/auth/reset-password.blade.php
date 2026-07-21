<x-auth-layout title='Reset Password — Jokiinlah'>
    <h1 class='text-2xl font-semibold text-[#0B1933]'>Password baru</h1>
    <form method='POST' action='{{ route('password.update') }}' class='mt-6 space-y-4'>@csrf
        <input type='hidden' name='token' value='{{ $request->route('token') }}'>
        <label class='block text-sm'>Email<input class='mt-1 w-full rounded-xl border p-3' type='email' name='email' value='{{ $request->email }}' required></label>
        <label class='block text-sm'>Password<input class='mt-1 w-full rounded-xl border p-3' type='password' name='password' required></label>
        <label class='block text-sm'>Konfirmasi password<input class='mt-1 w-full rounded-xl border p-3' type='password' name='password_confirmation' required></label>
        <button class='w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white'>Simpan password</button>
    </form>
</x-auth-layout>
