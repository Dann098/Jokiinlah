@props(['bytes'])
@php
    $size = max(0, (int) $bytes);
    $units = ['B', 'KB', 'MB', 'GB'];
    $index = 0;
    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }
@endphp
{{ number_format($size, $index === 0 ? 0 : 1, ',', '.') }} {{ $units[$index] }}
