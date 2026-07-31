@props(['service'])
@php
    $imageUrl = $service->imageUrl();
@endphp
<article class='surface-card hover-lift flex h-full flex-col p-6' data-reveal>
    @if($imageUrl)
        <img src='{{ $imageUrl }}' alt='Ilustrasi {{ $service->name }}' width='720' height='405' loading='lazy' class='-mx-6 -mt-6 mb-6 aspect-[16/9] w-[calc(100%+3rem)] max-w-none rounded-t-[1.25rem] object-cover'>
    @endif
    <div class='flex items-start justify-between gap-4'><span class='flex h-12 w-12 items-center justify-center rounded-2xl bg-navy text-gold'><svg aria-hidden='true' class='h-6 w-6' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7'><path d='M4 19.5V6a2 2 0 0 1 2-2h12v15.5M4 19.5A1.5 1.5 0 0 0 5.5 21H20V7H6a2 2 0 0 0-2 2m4-1h8m-8 4h6'/></svg></span><x-badge>{{ $service->category->label() }}</x-badge></div>
    <h3 class='mt-6 text-2xl font-bold text-navy'><a class='hover:text-[#8a5a0e]' href='{{ route('services.show', $service) }}'>{{ $service->name }}</a></h3>
    <p class='mt-3 flex-1 text-sm leading-7 text-muted'>{{ $service->short_description }}</p>
    <a class='mt-6 inline-flex min-h-11 items-center font-bold text-navy hover:text-[#8a5a0e]' href='{{ route('services.show', $service) }}'>Lihat detail <span aria-hidden='true' class='ml-2'>→</span></a>
</article>
