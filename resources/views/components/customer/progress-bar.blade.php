@props(['value' => 0])
@php($progress = max(0, min(100, (int) $value)))
<div {{ $attributes }}>
    <div class='mb-2 flex items-center justify-between gap-3 text-xs font-bold text-navy'>
        <span>Progress proyek</span><span>{{ $progress }}%</span>
    </div>
    <div class='h-2.5 overflow-hidden rounded-full bg-navy/10' role='progressbar' aria-label='Progress proyek {{ $progress }} persen' aria-valuenow='{{ $progress }}' aria-valuemin='0' aria-valuemax='100'>
        <div class='h-full rounded-full bg-gradient-to-r from-rose to-gold transition-[width] duration-500' style='width: {{ $progress }}%'></div>
    </div>
</div>
