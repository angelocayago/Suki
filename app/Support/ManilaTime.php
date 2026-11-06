<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Presentation helpers for Philippine-facing date and time values. */
final class ManilaTime
{
    public const TIMEZONE = 'Asia/Manila';

    /** Interpret timezone-less database values as UTC, then return Manila time. */
    public static function local(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, 'UTC');

        return $date->setTimezone(self::TIMEZONE);
    }

    public static function format(DateTimeInterface|string|null $value, string $format = 'M j, Y · g:i A'): ?string
    {
        return self::local($value)?->format($format);
    }

    public static function calendarLabel(DateTimeInterface|string|null $value, DateTimeInterface|string|null $reference = null): ?string
    {
        $date = self::local($value);
        if (! $date) {
            return null;
        }

        $today = self::local($reference ?? now());
        $day = match (true) {
            $date->isSameDay($today) => 'Today',
            $date->isSameDay($today->subDay()) => 'Yesterday',
            default => $date->format('M j, Y'),
        };

        return $day.' · '.$date->format('M j, Y · g:i A').' PHT';
    }

    public static function relative(DateTimeInterface|string|null $value, DateTimeInterface|string|null $reference = null): ?string
    {
        $date = self::local($value);
        if (! $date) {
            return null;
        }

        $now = self::local($reference ?? now());
        if ($date->isSameDay($now)) {
            return 'Today';
        }
        if ($date->isSameDay($now->subDay())) {
            return 'Yesterday';
        }

        return $date->diffForHumans($now);
    }

    /** Convert inclusive Manila calendar dates to UTC database boundaries. */
    public static function utcDateBounds(?string $from, ?string $to): array
    {
        $start = $from
            ? CarbonImmutable::createFromFormat('!Y-m-d', $from, self::TIMEZONE)->startOfDay()->utc()
            : null;
        $end = $to
            ? CarbonImmutable::createFromFormat('!Y-m-d', $to, self::TIMEZONE)->endOfDay()->utc()
            : null;

        return [$start, $end];
    }
}
