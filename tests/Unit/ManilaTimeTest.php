<?php

namespace Tests\Unit;

use App\Support\ManilaTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ManilaTimeTest extends TestCase
{
    public function test_utc_timestamp_is_formatted_as_manila_time_without_changing_utc_configuration(): void
    {
        $timestamp = CarbonImmutable::parse('2026-11-06 05:56:00', 'UTC');

        $this->assertSame('Nov 6, 2026 · 1:56 PM', ManilaTime::format($timestamp));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('2026-11-06 05:56:00', $timestamp->utc()->format('Y-m-d H:i:s'));
    }

    public function test_manila_calendar_day_handles_the_utc_midnight_boundary(): void
    {
        $beforeMidnight = '2026-11-05 15:59:00';
        $atMidnight = '2026-11-05 16:00:00';

        $this->assertSame('Nov 5, 2026 · 11:59 PM', ManilaTime::format($beforeMidnight));
        $this->assertSame('Nov 6, 2026 · 12:00 AM', ManilaTime::format($atMidnight));
    }

    public function test_today_and_yesterday_are_based_on_the_manila_calendar_day(): void
    {
        $reference = CarbonImmutable::parse('2026-11-05 16:30:00', 'UTC'); // Nov 6, 12:30 AM PHT.

        $this->assertSame('Today', ManilaTime::relative('2026-11-05 16:01:00', $reference));
        $this->assertSame('Yesterday', ManilaTime::relative('2026-11-04 16:01:00', $reference));
        $this->assertSame('Today · Nov 6, 2026 · 12:00 AM PHT', ManilaTime::calendarLabel('2026-11-05 16:00:00', $reference));
    }

    public function test_manila_date_filters_are_converted_to_utc_boundaries(): void
    {
        [$from, $to] = ManilaTime::utcDateBounds('2026-11-06', '2026-11-06');

        $this->assertSame('2026-11-05 16:00:00', $from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-06 15:59:59', $to->format('Y-m-d H:i:s'));
    }
}
