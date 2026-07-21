<x-auth-layout title='Lupa Password — Jokiinlah'>
    <h1 class='text-2xl font-semibold text-[#0B1933]'>Atur ulang password</h1>
    <form method='POST' action='{{ route('password.email') }}' class='mt-6 space-y-4'>@csrf
        <label class='block text-sm'>Email<input class='mt-1 w-full rounded-xl border p-3' type='email' name='email' required></label>
        <button class='w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white'>Kirim tautan reset</button>
    </form>
</x-auth-layout>
