@props(['href' => null, 'type' => 'button'])
@php($classes = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-current px-5 py-3 text-center text-sm font-bold transition hover:-translate-y-0.5 hover:bg-white/10 active:translate-y-0 disabled:opacity-60')
@if($href)<a href='{{ $href }}' {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>@else<button type='{{ $type }}' {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>@endif
