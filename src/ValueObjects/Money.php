<?php

declare(strict_types=1);

namespace App\ValueObjects;

final class Money
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency = 'BRL',
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function format(): string
    {
        return number_format($this->amount, 2, ',', '.') . ' ' . $this->currency;
    }

    public function isZero(): bool
    {
        return abs($this->amount) < 0.001;
    }

    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency
            && abs($this->amount - $other->amount) < 0.001;
    }
}
