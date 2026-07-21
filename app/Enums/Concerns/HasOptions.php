<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value',
        );
    }
}
