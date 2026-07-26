@props(['title', 'category' => null, 'version' => null])
<article {{ $attributes->merge(['class' => 'min-w-0 rounded-xl border border-navy/10 bg-white p-4']) }}>
    <div class='flex flex-wrap items-start justify-between gap-3'>
        <div class='min-w-0'>
            <h3 class='safe-content font-bold text-navy'>{{ $title }}</h3>
            @if($category)<p class='mt-1 text-xs text-muted'>{{ $category }}</p>@endif
        </div>
        @if($version)<span class='rounded-full bg-cream px-2.5 py-1 text-xs font-bold text-navy'>Versi {{ $version }}</span>@endif
    </div>
    <div class='mt-4'>{{ $slot }}</div>
</article>
