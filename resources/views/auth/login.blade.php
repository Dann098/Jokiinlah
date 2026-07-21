<x-auth-layout title='Masuk — Jokiinlah'>
    <h1 class='text-2xl font-semibold text-[#0B1933]'>Masuk ke akun</h1>
    <form method='POST' action='{{ route('login') }}' class='mt-6 space-y-4'>
        @csrf
        <label class='block text-sm'>Email<input class='mt-1 w-full rounded-xl border p-3' type='email' name='email' value='{{ old('email') }}' required autofocus></label>
        <label class='block text-sm'>Password<input class='mt-1 w-full rounded-xl border p-3' type='password' name='password' required></label>
        <label class='flex gap-2 text-sm'><input type='checkbox' name='remember'> Ingat saya</label>
        <button class='w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white hover:bg-[#162B4D]' type='submit'>Masuk</button>
    </form>
    <div class='mt-5 flex justify-between text-sm'><a href='{{ route('password.request') }}'>Lupa password?</a><a href='{{ route('register') }}'>Daftar</a></div>
</x-auth-layout>
