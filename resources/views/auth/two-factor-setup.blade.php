<x-auth-layout title="Keamanan Dua Faktor — Jokiinlah">
    <h1 class="text-2xl font-semibold text-[#0B1933]">Keamanan dua faktor</h1>
    <p class="mt-2 text-sm text-slate-600">
        Admin dan staff wajib menggunakan aplikasi authenticator sebelum membuka panel operasional.
    </p>

    @if (session('warning'))
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
            {{ session('warning') }}
        </div>
    @endif

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900" role="status">
            Perubahan keamanan berhasil disimpan.
        </div>
    @endif

    @if (! $user->two_factor_secret)
        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
            @csrf
            <button class="w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white" type="submit">
                Mulai aktivasi 2FA
            </button>
        </form>
    @elseif (! $user->hasEnabledTwoFactorAuthentication())
        <section class="mt-6 space-y-5" aria-labelledby="setup-heading">
            <h2 id="setup-heading" class="text-lg font-semibold text-[#0B1933]">Pindai dan konfirmasi</h2>
            <div class="mx-auto w-fit max-w-full overflow-hidden rounded-xl border bg-white p-3" aria-label="QR code authenticator">
                {!! $qrCode !!}
            </div>
            <div>
                <p class="text-sm font-medium">Setup key manual</p>
                <code class="mt-1 block break-all rounded-lg bg-slate-100 p-3 text-sm">{{ $setupKey }}</code>
            </div>
            <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                @csrf
                <label class="block text-sm font-medium" for="code">
                    Kode enam digit
                    <input
                        class="mt-1 w-full rounded-xl border p-3 font-mono tracking-[0.35em]"
                        id="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        name="code"
                        pattern="[0-9]{6}"
                        required
                    >
                </label>
                @error('code', 'confirmTwoFactorAuthentication')
                    <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
                <button class="w-full rounded-xl bg-[#0B1933] p-3 font-semibold text-white" type="submit">
                    Konfirmasi aktivasi
                </button>
            </form>
        </section>
    @else
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            Autentikasi dua faktor aktif.
        </div>

        @if ($recoveryCodes !== [])
            <section class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4" aria-labelledby="recovery-heading">
                <h2 id="recovery-heading" class="font-semibold text-amber-950">Simpan kode pemulihan sekarang</h2>
                <p class="mt-1 text-sm text-amber-900">
                    Kode ini hanya ditampilkan sekali. Simpan di password manager atau lokasi terenkripsi.
                </p>
                <ul class="mt-3 grid gap-2 font-mono text-sm sm:grid-cols-2">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <li class="rounded bg-white px-3 py-2">{{ $recoveryCode }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mt-6 space-y-3">
            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                @csrf
                <button class="w-full rounded-xl border border-[#0B1933] p-3 font-semibold text-[#0B1933]" type="submit">
                    Buat ulang kode pemulihan
                </button>
            </form>
            <form method="POST" action="{{ route('two-factor.disable') }}">
                @csrf
                @method('DELETE')
                <button class="w-full rounded-xl border border-red-300 p-3 font-semibold text-red-700" type="submit">
                    Nonaktifkan 2FA
                </button>
            </form>
            <a class="block rounded-xl bg-[#0B1933] p-3 text-center font-semibold text-white" href="{{ route('filament.admin.pages.dashboard') }}">
                Buka panel operasional
            </a>
        </div>
    @endif
</x-auth-layout>
