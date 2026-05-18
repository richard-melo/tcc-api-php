<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class Period
{
    public function __construct(
        private readonly int $month,
        private readonly int $year,
    ) {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Month must be between 1 and 12');
        }
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function label(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function daysInMonth(): int
    {
        return (int) date('t', mktime(0, 0, 0, $this->month, 1, $this->year));
    }

    public function startDate(): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-n-j', "{$this->year}-{$this->month}-1");
        if ($date === false) {
            throw new \RuntimeException('Failed to create start date');
        }
        return $date;
    }

    public function endDate(): \DateTimeImmutable
    {
        $days = $this->daysInMonth();
        $date = \DateTimeImmutable::createFromFormat('Y-n-j', "{$this->year}-{$this->month}-{$days}");
        if ($date === false) {
            throw new \RuntimeException('Failed to create end date');
        }
        return $date;
    }
}
