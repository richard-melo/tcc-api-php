<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class DateRange
{
    public function __construct(
        private readonly \DateTimeImmutable $from,
        private readonly \DateTimeImmutable $to,
    ) {
        if ($from > $to) {
            throw new \InvalidArgumentException('DateRange: from must be <= to');
        }
    }

    public function getFrom(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): \DateTimeImmutable
    {
        return $this->to;
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days;
    }

    public function contains(\DateTimeImmutable $date): bool
    {
        return $date >= $this->from && $date <= $this->to;
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->from <= $other->to && $this->to >= $other->from;
    }
}
