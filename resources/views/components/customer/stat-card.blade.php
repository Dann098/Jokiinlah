@props(['label', 'value', 'hint' => null, 'tone' => 'navy'])
@php($toneClass = match($tone) { 'green' => 'bg-green-50 text-green-800', 'gold' => 'bg-amber-50 text-amber-800', 'rose' => 'bg-rose/15 text-navy', default => 'bg-navy text-white' })
<article {{ $attributes->merge(['class' => 'surface-card overflow-hidden p-5']) }}>
    <div class='inline-flex rounded-lg px-2.5 py-1 text-xs font-bold {{ $toneClass }}'>{{ $label }}</div>
    <p class='mt-4 text-3xl font-bold text-navy'>{{ $value }}</p>
    @if($hint)<p class='mt-2 text-xs leading-5 text-muted'>{{ $hint }}</p>@endif
</article>
