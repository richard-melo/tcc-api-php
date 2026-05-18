<?php

declare(strict_types=1);

namespace App\DTOs;

final class SummaryDTO
{
    public function __construct(
        public readonly int $total,
        public readonly float $sum,
        public readonly float $average,
        public readonly float $max,
        public readonly float $min,
    ) {
    }

    /** @return array<string, int|float> */
    public function toArray(): array
    {
        return [
            'total'   => $this->total,
            'sum'     => $this->sum,
            'average' => $this->average,
            'max'     => $this->max,
            'min'     => $this->min,
        ];
    }

    public function range(): float
    {
        return $this->max - $this->min;
    }
}
