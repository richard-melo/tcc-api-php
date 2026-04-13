<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Config\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base para testes que precisam de banco de dados.
 * Usa SQLite :memory: — sem arquivo, sem Docker.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Garante instância limpa a cada teste
        Database::reset();

        $_ENV['DB_PATH']    = ':memory:';
        $_ENV['JWT_SECRET'] = 'test-secret-phpunit';
        $_ENV['APP_ENV']    = 'testing';

        // Dispara a conexão e roda as migrations automaticamente
        Database::getConnection();
    }

    protected function tearDown(): void
    {
        Database::reset();
        parent::tearDown();
    }
}
