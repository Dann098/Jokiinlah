@props(['testimonial'])
@php
    $photoUrl = $testimonial->photoUrl();
@endphp
<figure class='surface-card h-full p-6' data-reveal>
    <div class='flex gap-1 text-gold' aria-label='Rating {{ $testimonial->rating }} dari 5'>
        @for($i = 0; $i < $testimonial->rating; $i++)
            <span aria-hidden='true'>★</span>
        @endfor
    </div>
    <blockquote class='mt-4 text-sm leading-7 text-charcoal'>“{{ $testimonial->content }}”</blockquote>
    <figcaption class='mt-6 flex items-center justify-between gap-3'>
        <span class='flex min-w-0 items-center gap-3'>
            @if($photoUrl)
                <img src='{{ $photoUrl }}' alt='Foto {{ $testimonial->customer_name }}' width='48' height='48' loading='lazy' class='h-12 w-12 shrink-0 rounded-full object-cover'>
            @endif
            <span class='min-w-0'>
                <strong class='block text-navy'>{{ $testimonial->customer_name }}</strong>
                <span class='text-xs text-muted'>{{ $testimonial->customer_role }}</span>
            </span>
        </span>
        @if($testimonial->is_demo)
            <x-badge>Data Demo</x-badge>
        @endif
    </figcaption>
</figure>
