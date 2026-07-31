@props(['article'])
@php
    $thumbnailUrl = $article->thumbnailUrl();
@endphp
<article class='surface-card hover-lift flex h-full flex-col overflow-hidden' data-reveal>
    @if($thumbnailUrl)
        <div class='relative aspect-[16/9]'><img src='{{ $thumbnailUrl }}' alt='Ilustrasi {{ $article->title }}' width='720' height='405' loading='lazy' class='h-full w-full object-cover'><x-badge class='absolute bottom-5 left-5'>{{ $article->category->label() }}</x-badge></div>
    @else
        <div class='hero-grid flex aspect-[16/9] items-end bg-navy-light p-5'><x-badge>{{ $article->category->label() }}</x-badge></div>
    @endif
    <div class='flex flex-1 flex-col p-6'><p class='text-xs font-semibold uppercase tracking-wider text-muted'>{{ $article->published_at?->setTimezone(config('jokiinlah.display_timezone'))->translatedFormat('d M Y') }}</p><h3 class='mt-3 text-2xl font-bold leading-snug text-navy'><a href='{{ route('articles.show', $article) }}'>{{ $article->title }}</a></h3><p class='mt-3 flex-1 text-sm leading-7 text-muted'>{{ $article->excerpt }}</p><a class='mt-5 inline-flex min-h-11 items-center font-bold text-navy' href='{{ route('articles.show', $article) }}'>Baca artikel <span aria-hidden='true' class='ml-2'>→</span></a></div>
</article>
