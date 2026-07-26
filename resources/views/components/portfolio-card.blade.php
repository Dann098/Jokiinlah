@props(['portfolio'])
<article class='surface-card hover-lift overflow-hidden' data-reveal>
    <a href='{{ route('portfolios.show', $portfolio) }}' class='block'>
        @if($portfolio->thumbnail && file_exists(public_path($portfolio->thumbnail)))
            <img src='{{ asset($portfolio->thumbnail) }}' alt='Pratinjau {{ $portfolio->title }}' width='720' height='440' loading='lazy' class='aspect-[16/10] w-full object-cover'>
        @else
            <div class='hero-grid flex aspect-[16/10] items-center justify-center bg-navy-light p-8'><img src='{{ asset('images/logo-small.webp') }}' alt='' aria-hidden='true' width='160' height='160' loading='lazy' class='h-24 w-24 rounded-full opacity-90'></div>
        @endif
    </a>
    <div class='p-6'><div class='flex flex-wrap items-center gap-2'><x-badge>{{ $portfolio->category }}</x-badge><x-badge>Studi Kasus</x-badge></div><h3 class='mt-4 text-2xl font-bold text-navy'><a href='{{ route('portfolios.show', $portfolio) }}'>{{ $portfolio->title }}</a></h3><p class='mt-3 line-clamp-3 text-sm leading-7 text-muted'>{{ $portfolio->description }}</p>@if($portfolio->technologies)<div class='mt-4 flex flex-wrap gap-2'>@foreach(array_slice($portfolio->technologies, 0, 3) as $technology)<span class='rounded-lg bg-cream px-2 py-1 text-xs font-semibold text-navy'>{{ $technology }}</span>@endforeach</div>@endif</div>
</article>
