<x-auth-layout title='Konfirmasi Password — Jokiinlah'>
    <h1 class='text-2xl font-semibold text-[#0B1933]'>Konfirmasi password</h1>
    <form method='POST' action='{{ route('password.confirm') }}' class='mt-6 space-y-4'>@csrf
        <label class='block text-sm'>Password<input class='mt-1 w-full rounded-xl border p-3' type='password' name='password' required></label>
        <button class='w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white'>Konfirmasi</button>
    </form>
</x-auth-layout>
