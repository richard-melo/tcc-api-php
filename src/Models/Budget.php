<?php

declare(strict_types=1);

namespace App\Models;

class Budget
{
    public function __construct(
        public readonly ?int   $id,
        public readonly int    $userId,
        public readonly string $category,
        public readonly float  $amount,
        public readonly int    $month,
        public readonly int    $year,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:        isset($data['id']) ? (int) $data['id'] : null,
            userId:    (int)   $data['user_id'],
            category:  $data['category'],
            amount:    (float) $data['amount'],
            month:     (int)   $data['month'],
            year:      (int)   $data['year'],
            createdAt: $data['created_at'] ?? '',
            updatedAt: $data['updated_at'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->userId,
            'category'   => $this->category,
            'amount'     => $this->amount,
            'month'      => $this->month,
            'year'       => $this->year,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
