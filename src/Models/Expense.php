<?php

declare(strict_types=1);

namespace App\Models;

class Expense
{
    public const CATEGORIES = [
        'alimentacao',
        'transporte',
        'moradia',
        'saude',
        'educacao',
        'lazer',
        'compras',
        'outros',
    ];

    public const PAYMENT_METHODS = [
        'dinheiro',
        'credito',
        'debito',
        'pix',
        'transferencia',
    ];

    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $description,
        public readonly float $amount,
        public readonly string $category,
        public readonly string $paymentMethod,
        public readonly string $expenseDate,
        public readonly ?string $notes,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:            isset($data['id']) ? (int) $data['id'] : null,
            userId:        (int) $data['user_id'],
            description:   $data['description'],
            amount:        (float) $data['amount'],
            category:      $data['category'],
            paymentMethod: $data['payment_method'],
            expenseDate:   $data['expense_date'],
            notes:         $data['notes'] ?? null,
            createdAt:     $data['created_at'] ?? '',
            updatedAt:     $data['updated_at'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->userId,
            'description'    => $this->description,
            'amount'         => $this->amount,
            'category'       => $this->category,
            'payment_method' => $this->paymentMethod,
            'expense_date'   => $this->expenseDate,
            'notes'          => $this->notes,
            'created_at'     => $this->createdAt,
            'updated_at'     => $this->updatedAt,
        ];
    }
}
