@props(['eyebrow' => null, 'title', 'description' => null, 'align' => 'left', 'theme' => 'light'])
<div class='{{ $align === 'center' ? 'mx-auto text-center' : '' }} max-w-3xl' data-reveal>
    @if($eyebrow)<p class='mb-3 text-xs font-bold uppercase tracking-[0.2em] {{ $theme === 'dark' ? 'text-gold' : 'text-[#80520d]' }}'>{{ $eyebrow }}</p>@endif
    <h2 class='text-balance text-3xl font-bold leading-tight {{ $theme === 'dark' ? 'text-white' : 'text-navy' }} sm:text-4xl lg:text-5xl'>{{ $title }}</h2>
    @if($description)<p class='mt-5 text-base leading-8 {{ $theme === 'dark' ? 'text-white/75' : 'text-muted' }} sm:text-lg'>{{ $description }}</p>@endif
</div>
