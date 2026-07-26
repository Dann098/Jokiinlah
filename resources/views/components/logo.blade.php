@props(['compact' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <span class='inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border border-white/20 bg-white shadow-sm {{ $compact ? 'h-11 w-11' : 'h-14 w-14' }}'>
        <picture>
            <source srcset='{{ asset($compact ? 'images/logo-small.webp' : 'images/logo.webp') }}' type='image/webp'>
            <img src='{{ asset('images/logo.jpeg') }}' alt='Logo Jokiinlah – Pendampingan Akademik dan Digital' width='640' height='640' class='h-full w-full object-cover'>
        </picture>
    </span>
    @unless($compact)
        <span><span class='block font-display text-xl font-bold text-white'>Jokiinlah</span><span class='block text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-rose'>Academic & Digital</span></span>
    @endunless
</span>
