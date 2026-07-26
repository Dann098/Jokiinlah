@props(['title', 'description' => null])
<section {{ $attributes->merge(['class' => 'surface-card p-5 sm:p-6']) }}>
    <h2 class='text-xl font-bold text-navy'>{{ $title }}</h2>
    @if($description)<p class='mt-2 text-sm leading-6 text-muted'>{{ $description }}</p>@endif
    <div class='mt-5'>{{ $slot }}</div>
</section>
