<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertLevel: string
{
    case None     = 'none';
    case Warning  = 'warning';
    case Exceeded = 'exceeded';

    public function isAlert(): bool
    {
        return $this !== self::None;
    }

    public function color(): string
    {
        return match ($this) {
            self::None     => 'green',
            self::Warning  => 'yellow',
            self::Exceeded => 'red',
        };
    }
}
