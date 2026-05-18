<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

/**
 * Cenário de falha intencional para benchmark de detecção de erros.
 * Todos os testes desta classe falham propositalmente.
 */
class IntentionalFailureTest extends FunctionalTestCase
{
    public function testAlwaysFails(): void
    {
        $this->fail('Falha intencional — cenário de benchmark de erro no PHPUnit');
    }

    public function testAssertionFails(): void
    {
        $expected = 42;
        $actual   = 0;
        $this->assertEquals($expected, $actual, 'Valor incorreto intencional para benchmark');
    }

    public function testTypeMismatch(): void
    {
        $value = 'texto';
        $this->assertIsInt($value, 'Tipo incorreto intencional para benchmark');
    }
}
