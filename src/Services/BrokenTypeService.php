<?php

declare(strict_types=1);

namespace App\Services;

class BrokenTypeService
{
    public function getTotal(int $a, int $b): string
    {
        return $a + $b;
    }

    public function getLabel(bool $active): int
    {
        return $active ? 'ativo' : 'inativo';
    }
}
