@props(['status'])
@php
    $label = method_exists($status, 'label') ? $status->label() : (string) $status;
    $color = method_exists($status, 'color') ? $status->color() : 'gray';
    $classes = match($color) {
        'success' => 'border-green-200 bg-green-50 text-green-800',
        'danger' => 'border-red-200 bg-red-50 text-red-800',
        'primary' => 'border-blue-200 bg-blue-50 text-blue-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold '.$classes]) }}>
    <span class='h-1.5 w-1.5 rounded-full bg-current' aria-hidden='true'></span>{{ $label }}
</span>
