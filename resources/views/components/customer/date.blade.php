@props(['value', 'format' => 'd M Y, H:i', 'fallback' => '—'])
{{ $value ? app(\App\Services\DateTimeService::class)->forDisplay($value, $format) : $fallback }}
