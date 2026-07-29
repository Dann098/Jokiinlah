<x-auth-layout title="Verifikasi Dua Faktor — Jokiinlah">
    <h1 class="text-2xl font-semibold text-[#0B1933]">Verifikasi dua faktor</h1>
    <p class="mt-2 text-sm text-slate-600">
        Masukkan kode enam digit dari aplikasi authenticator. Jika perangkat tidak tersedia,
        gunakan satu kode pemulihan.
    </p>

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert" tabindex="-1">
            Kode tidak valid atau sudah tidak dapat digunakan.
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-medium" for="code">
            Kode authenticator
            <input
                class="mt-1 w-full rounded-xl border p-3 font-mono tracking-[0.35em]"
                id="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                name="code"
                pattern="[0-9]{6}"
                autofocus
            >
        </label>

        <label class="block text-sm font-medium" for="recovery_code">
            Atau kode pemulihan
            <input
                class="mt-1 w-full rounded-xl border p-3 font-mono"
                id="recovery_code"
                autocomplete="one-time-code"
                name="recovery_code"
            >
        </label>

        <button class="w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white" type="submit">
            Verifikasi dan masuk
        </button>
    </form>
</x-auth-layout>
