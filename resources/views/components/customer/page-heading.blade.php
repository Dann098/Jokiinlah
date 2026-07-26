@props(['eyebrow' => 'Customer Portal', 'title', 'description' => null])
<header {{ $attributes->merge(['class' => 'mb-7']) }}>
    <p class='text-xs font-bold uppercase tracking-[0.18em] text-rose'>{{ $eyebrow }}</p>
    <h1 class='mt-2 text-balance text-3xl font-bold text-navy sm:text-4xl'>{{ $title }}</h1>
    @if($description)<p class='mt-3 max-w-3xl text-sm leading-7 text-muted sm:text-base'>{{ $description }}</p>@endif
    @if(trim((string) $slot) !== '')<div class='mt-5 flex flex-wrap gap-3'>{{ $slot }}</div>@endif
</header>
