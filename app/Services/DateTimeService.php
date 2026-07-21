<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class DateTimeService
{
    public function fromUserInput(string $value, bool $endOfDay = false): CarbonImmutable
    {
        $date = CarbonImmutable::parse($value, config('jokiinlah.display_timezone'));

        return ($endOfDay ? $date->endOfDay() : $date)->utc();
    }

    public function forDisplay(DateTimeInterface|string|null $value, string $format = 'd M Y H:i'): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC')
            ->setTimezone(config('jokiinlah.display_timezone'))
            ->translatedFormat($format);
    }
}
