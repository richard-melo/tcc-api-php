<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Abstração leve de leitura de input HTTP.
 * Em produção lê php://input; em testes, usa $stub injetado diretamente.
 */
class Request
{
    /** @var array<string,mixed>|null Preenchido pelos testes funcionais */
    public static ?array $stub = null;

    /** @return array<string,mixed> */
    public static function body(): array
    {
        if (self::$stub !== null) {
            return self::$stub;
        }

        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    public static function reset(): void
    {
        self::$stub = null;
    }
}
