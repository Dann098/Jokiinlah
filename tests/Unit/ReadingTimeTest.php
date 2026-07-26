<?php

namespace Tests\Unit;

use App\Services\ReadingTime;
use PHPUnit\Framework\TestCase;

class ReadingTimeTest extends TestCase
{
    public function test_reading_time_has_one_minute_minimum_and_rounds_up(): void
    {
        $service = new ReadingTime;

        $this->assertSame(1, $service->minutes(''));
        $this->assertSame(1, $service->minutes('<p>'.str_repeat('kata ', 200).'</p>'));
        $this->assertSame(2, $service->minutes(str_repeat('kata ', 201)));
    }
}
