<?php

declare(strict_types=1);

namespace App\DTOs;

final class ExpenseDTO
{
    public function __construct(
        public readonly string $description,
        public readonly float $amount,
        public readonly string $category,
        public readonly string $date,
        public readonly int $userId,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'amount'      => $this->amount,
            'category'    => $this->category,
            'date'        => $this->date,
            'user_id'     => $this->userId,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            description: (string) ($data['description'] ?? ''),
            amount:      (float)  ($data['amount']      ?? 0.0),
            category:    (string) ($data['category']    ?? ''),
            date:        (string) ($data['date']        ?? ''),
            userId:      (int)    ($data['user_id']     ?? 0),
        );
    }
}
