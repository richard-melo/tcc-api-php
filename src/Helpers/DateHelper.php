<?php

declare(strict_types=1);

namespace App\Helpers;

final class DateHelper
{
    public static function startOfMonth(int $month, int $year): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-n-j', "{$year}-{$month}-1");
        if ($d === false) {
            throw new \RuntimeException('Invalid month/year');
        }
        return $d;
    }

    public static function endOfMonth(int $month, int $year): \DateTimeImmutable
    {
        $days = self::daysInMonth($month, $year);
        $d    = \DateTimeImmutable::createFromFormat('Y-n-j', "{$year}-{$month}-{$days}");
        if ($d === false) {
            throw new \RuntimeException('Invalid month/year');
        }
        return $d;
    }

    public static function daysInMonth(int $month, int $year): int
    {
        return (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    public static function isWeekend(\DateTimeImmutable $date): bool
    {
        return in_array((int) $date->format('N'), [6, 7], true);
    }

    public static function diffInDays(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->days;
    }

    public static function formatBr(\DateTimeImmutable $date): string
    {
        return $date->format('d/m/Y');
    }

    public static function parseDate(string $s): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $s);
        return $d !== false ? $d : null;
    }

    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }
}
