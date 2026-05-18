<?php

declare(strict_types=1);

namespace App\DTOs;

final class BudgetDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $category,
        public readonly float $amount,
        public readonly int $month,
        public readonly int $year,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id'  => $this->userId,
            'category' => $this->category,
            'amount'   => $this->amount,
            'month'    => $this->month,
            'year'     => $this->year,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId:   (int)    ($data['user_id']  ?? 0),
            category: (string) ($data['category'] ?? ''),
            amount:   (float)  ($data['amount']   ?? 0.0),
            month:    (int)    ($data['month']     ?? 1),
            year:     (int)    ($data['year']      ?? (int) date('Y')),
        );
    }
}
