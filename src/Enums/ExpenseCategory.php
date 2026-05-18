<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseCategory: string
{
    case Food          = 'food';
    case Transport     = 'transport';
    case Housing       = 'housing';
    case Health        = 'health';
    case Entertainment = 'entertainment';
    case Education     = 'education';
    case Other         = 'other';

    public function label(): string
    {
        return ucfirst($this->name);
    }

    public function isEssential(): bool
    {
        return match ($this) {
            self::Food, self::Housing, self::Health => true,
            default                                 => false,
        };
    }
}
