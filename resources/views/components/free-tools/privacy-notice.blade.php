@props(['compact' => false])

<aside {{ $attributes->class([
    'rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-950',
    'p-4' => $compact,
    'p-5 sm:p-6' => ! $compact,
]) }} aria-label='Informasi privasi CV'>
    <div class='flex gap-3'>
        <svg class='mt-0.5 h-5 w-5 shrink-0 text-emerald-700' aria-hidden='true' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
            <path d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z'/><path d='m9 12 2 2 4-4'/>
        </svg>
        <div>
            <p class='font-bold'>Data CV diproses di perangkat Anda dan tidak dikirim ke server.</p>
            <p class='mt-1 text-sm leading-6 text-emerald-900/80'>Draft teks hanya tersimpan sementara di browser ini. Foto tidak disimpan di browser.</p>
            <p class='mt-1 text-sm leading-6 text-emerald-900/80'>Foto perlu dipilih kembali setelah halaman ditutup atau dimuat ulang.</p>
        </div>
    </div>
</aside>
