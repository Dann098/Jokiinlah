<details {{ $attributes->merge(['class' => 'group mt-4 rounded-xl border border-navy/10 bg-cream/60']) }}>
    <summary class='flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 text-sm font-bold text-navy'>
        Riwayat versi
        <span aria-hidden='true' class='transition group-open:rotate-180'>⌄</span>
    </summary>
    <div class='border-t border-navy/10 p-4'>{{ $slot }}</div>
</details>
