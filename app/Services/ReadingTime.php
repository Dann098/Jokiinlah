<?php

namespace App\Services;

class ReadingTime
{
    public function minutes(string $content): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags($content)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return max(1, (int) ceil(count($words) / 200));
    }
}
