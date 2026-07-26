@props(['href' => null, 'type' => 'button'])
@php($classes = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-gold px-5 py-3 text-center text-sm font-bold text-navy shadow-lg shadow-gold/15 transition hover:-translate-y-0.5 hover:bg-[#e3b94f] active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60')
@if($href)<a href='{{ $href }}' {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>@else<button type='{{ $type }}' {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>@endif
