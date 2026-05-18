#!/usr/bin/env bash
# Cenário 6 — Intentional Failures
# Introduz falhas propositais em 3 stages:
#   - PHPStan: classe com retorno de tipo errado
#   - PHPCS:   arquivo com violações PSR-12
#   - PHPUnit: teste que sempre falha
# Objetivo: medir como cada plataforma detecta e reporta falhas.
# Branch: scenario/intentional-failures
#
# Uso: ./metrics/scenarios/run_scenario6_intentional_failures.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/_common.sh"

SCENARIO="intentional-failures"
BRANCH="scenario/intentional-failures"

log_h "Cenário 6 — Intentional Failures"
echo "  Branch    : $BRANCH"
echo "  Objetivo  : medir detecção de falha em PHPStan + PHPCS + PHPUnit"

prepare_branch "$BRANCH"

# ── Falha 1: PHPStan — tipo de retorno errado ──────────────────────────────────
log_h "Falha 1 — PHPStan: tipo de retorno incorreto"

mkdir -p "$REPO_DIR/src/Services"
cat > "$REPO_DIR/src/Services/BrokenTypeService.php" << 'PHPEOF'
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
PHPEOF
log_ok "BrokenTypeService.php criado (2 erros de tipo PHPStan)"

# ── Falha 2: PHPCS — violações PSR-12 ─────────────────────────────────────────
log_h "Falha 2 — PHPCS: violações de estilo PSR-12"

cat > "$REPO_DIR/src/Services/BadStyleService.php" << 'PHPEOF'
<?php
declare(strict_types=1);
namespace App\Services;
class BadStyleService{
public function calculate($x,$y){
$result=$x+$y;
if($result>100){return true;}
return false;
}
public function format( $value ){
    return    trim($value)   ;
}
}
PHPEOF
log_ok "BadStyleService.php criado (múltiplas violações PSR-12)"

# ── Falha 3: PHPUnit — teste que sempre falha ─────────────────────────────────
log_h "Falha 3 — PHPUnit: teste que sempre falha"

cat > "$REPO_DIR/tests/Functional/IntentionalFailureTest.php" << 'PHPEOF'
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
PHPEOF
log_ok "IntentionalFailureTest.php criado (3 falhas intencionais)"

# ── Commit e push ───────────────────────────────────────────────────────────────
log_h "Commit e Push"
commit_push "scenario(intentional-failures): adiciona falhas em PHPStan, PHPCS e PHPUnit"

collect_all "$SCENARIO"

commit_metrics_to_main "$SCENARIO" "$GHA_NEW_LINE" "$JENKINS_NEW_LINE"
