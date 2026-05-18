#!/usr/bin/env bash
# Cenário 4 — Heavy PHPUnit
# Cria 3 arquivos de teste com data providers grandes, I/O de arquivos e usleep()
# para estressar o PHPUnit.
# Branch: scenario/heavy-phpunit
#
# Uso: ./metrics/scenarios/run_scenario4_heavy_phpunit.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/_common.sh"

SCENARIO="heavy-phpunit"
BRANCH="scenario/heavy-phpunit"

log_h "Cenário 4 — Heavy PHPUnit"
echo "  Branch    : $BRANCH"
echo "  Objetivo  : estressar PHPUnit com +300 casos via data providers + I/O + sleeps"

prepare_branch "$BRANCH"

log_h "Criando testes pesados"

# ── HeavyMathTest — 100 casos via dataProvider, 8ms sleep cada ─────────────────
cat > "$REPO_DIR/tests/Functional/HeavyMathTest.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

/**
 * Cenário de carga: operações matemáticas com data provider e micro-delay.
 * Cada caso simula latência de processamento (8ms) e verifica operações básicas.
 * 100 casos × 8ms = ~800ms + overhead do framework.
 */
class HeavyMathTest extends FunctionalTestCase
{
    /**
     * @dataProvider mathOperationsProvider
     */
    public function testArithmeticOperations(
        float $a,
        float $b,
        float $expectedSum,
        float $expectedDiff,
        float $expectedProduct,
    ): void {
        usleep(8_000); // 8ms de latência simulada

        $this->assertEqualsWithDelta($expectedSum, $a + $b, 0.001);
        $this->assertEqualsWithDelta($expectedDiff, $a - $b, 0.001);
        $this->assertEqualsWithDelta($expectedProduct, $a * $b, 0.001);
    }

    /**
     * @return array<string, array{float, float, float, float, float}>
     */
    public static function mathOperationsProvider(): array
    {
        $cases = [];
        for ($i = 1; $i <= 50; $i++) {
            $a = (float) $i;
            $b = (float) ($i * 2);
            $cases["caso_{$i}_inteiros"] = [$a, $b, $a + $b, $a - $b, $a * $b];

            $a2 = round($i * 1.5, 2);
            $b2 = round($i * 0.75, 2);
            $cases["caso_{$i}_decimais"] = [$a2, $b2, $a2 + $b2, $a2 - $b2, $a2 * $b2];
        }
        return $cases;
    }

    /**
     * @dataProvider statisticsProvider
     */
    public function testStatisticsOperations(
        float $sum,
        int $count,
        float $expectedMean,
        float $expectedMin,
        float $expectedMax,
    ): void {
        usleep(8_000);

        $mean = $count > 0 ? $sum / $count : 0.0;
        $this->assertEqualsWithDelta($expectedMean, $mean, 0.01);
        $this->assertLessThanOrEqual($expectedMax, $expectedMax);
        $this->assertGreaterThanOrEqual($expectedMin, $expectedMin);
    }

    /**
     * @return array<string, array{float, int, float, float, float}>
     */
    public static function statisticsProvider(): array
    {
        $cases = [];
        for ($i = 1; $i <= 50; $i++) {
            $count = $i + 1;
            $sum   = (float) ($i * $count);
            $mean  = round($sum / $count, 4);
            $cases["stats_{$i}"] = [$sum, $count, $mean, 0.0, $sum];
        }
        return $cases;
    }
}
PHPEOF
log_ok "HeavyMathTest.php (100 casos)"

# ── HeavyStringTest — 50 casos + I/O em arquivo temp por teste ─────────────────
cat > "$REPO_DIR/tests/Functional/HeavyStringTest.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

/**
 * Cenário de carga: manipulação de strings com data provider e I/O real.
 * Cada caso: 10ms sleep + escreve/lê/deleta arquivo temporário.
 * 50 casos × (10ms + I/O) = estimativa de 1-3s extras.
 */
class HeavyStringTest extends FunctionalTestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/phpunit_heavy_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    /**
     * @dataProvider stringTransformProvider
     */
    public function testStringTransformations(
        string $input,
        string $expectedUpper,
        string $expectedLower,
        int $expectedLength,
    ): void {
        usleep(10_000); // 10ms de latência simulada

        // I/O real: persiste e relê o dado
        $path = $this->tmpDir . '/' . uniqid('str_', true) . '.json';
        $payload = json_encode(['input' => $input, 'ts' => microtime(true)]);
        file_put_contents($path, (string) $payload);
        $recovered = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($recovered);
        $this->assertEquals($input, $recovered['input']);

        $this->assertEquals($expectedUpper, strtoupper($input));
        $this->assertEquals($expectedLower, strtolower($input));
        $this->assertEquals($expectedLength, strlen($input));
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function stringTransformProvider(): array
    {
        $words = [
            'apple', 'banana', 'cherry', 'dragonfruit', 'elderberry',
            'fig', 'grape', 'honeydew', 'kiwi', 'lemon',
            'mango', 'nectarine', 'orange', 'papaya', 'quince',
            'raspberry', 'strawberry', 'tangerine', 'ugli', 'watermelon',
            'apricot', 'blueberry', 'cantaloupe', 'date', 'guava',
            'jackfruit', 'kumquat', 'lychee', 'mulberry', 'olive',
            'peach', 'pear', 'plum', 'pomegranate', 'starfruit',
            'avocado', 'coconut', 'durian', 'feijoa', 'gooseberry',
            'huckleberry', 'ackee', 'bilberry', 'boysenberry', 'clementine',
            'damson', 'dewberry', 'elderflower', 'lingonberry', 'loganberry',
        ];
        $cases = [];
        foreach ($words as $word) {
            $cases[$word] = [$word, strtoupper($word), strtolower($word), strlen($word)];
        }
        return $cases;
    }
}
PHPEOF
log_ok "HeavyStringTest.php (50 casos + I/O)"

# ── HeavyFlowTest — 30 fluxos completos de API + 15ms sleep cada ───────────────
cat > "$REPO_DIR/tests/Functional/HeavyFlowTest.php" << 'PHPEOF'
<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

/**
 * Cenário de carga: fluxos completos de API (registro → auth → CRUD de despesas).
 * Cada caso: banco recriado (setUp pai) + 15ms sleep + 4 operações de serviço/controller.
 * 30 casos × (DB setup + 15ms + ops) = estimativa de 10-20s extras.
 */
class HeavyFlowTest extends FunctionalTestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/phpunit_flow_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    /**
     * @dataProvider expenseFlowProvider
     */
    public function testCompleteExpenseLifecycle(
        string $userSuffix,
        float $amount,
        string $category,
        string $description,
        float $updatedAmount,
    ): void {
        usleep(15_000); // 15ms de latência simulada

        // 1. Registra usuário e obtém token
        $token = $this->registerAndLogin(
            "User $userSuffix",
            "heavy_{$userSuffix}@test.com",
            'Password123!'
        );
        $this->assertNotEmpty($token, "Token vazio para $userSuffix");

        // 2. Configura autenticação
        $this->withAuth($token);

        // 3. Cria despesa via controller
        $created = $this->callExpense('store', [
            'description'    => $description,
            'amount'         => $amount,
            'category'       => $category,
            'payment_method' => 'pix',
            'expense_date'   => date('Y-m-d'),
        ]);
        $expenseId = $created['id'] ?? 0;
        $this->assertGreaterThan(0, $expenseId, "ID da despesa inválido");
        $this->assertEquals($amount, $created['amount'] ?? -1.0, '', 0.01);

        // 4. Atualiza despesa via controller
        $updated = $this->callExpense('update', [
            'description'    => $description . ' (atualizado)',
            'amount'         => $updatedAmount,
            'category'       => $category,
            'payment_method' => 'pix',
            'expense_date'   => date('Y-m-d'),
        ], $expenseId);
        $this->assertEqualsWithDelta($updatedAmount, $updated['amount'] ?? -1.0, 0.01);

        // 5. Lista despesas (I/O adicional)
        $list = $this->callExpense('index');
        $this->assertIsArray($list);

        // 6. Persiste estado no arquivo temp (simula I/O real)
        $statePath = $this->tmpDir . "/flow_{$userSuffix}.json";
        file_put_contents($statePath, (string) json_encode([
            'user'      => $userSuffix,
            'expense'   => $expenseId,
            'amount'    => $updatedAmount,
            'completed' => true,
        ]));
        $state = json_decode((string) file_get_contents($statePath), true);
        $this->assertIsArray($state);
        $this->assertTrue($state['completed'] ?? false);
    }

    /**
     * @return array<string, array{string, float, string, string, float}>
     */
    public static function expenseFlowProvider(): array
    {
        $categories = ['alimentacao', 'transporte', 'moradia', 'saude', 'lazer'];
        $cases      = [];

        for ($i = 1; $i <= 30; $i++) {
            $cat  = $categories[$i % count($categories)];
            $amt  = round(10.0 + $i * 7.5, 2);
            $upd  = round($amt * 1.1, 2);
            $desc = "Despesa de teste #{$i} para cenario heavy-phpunit";
            $cases["fluxo_{$i}_{$cat}"] = ["u{$i}", $amt, $cat, $desc, $upd];
        }

        return $cases;
    }
}
PHPEOF
log_ok "HeavyFlowTest.php (30 fluxos completos de API)"

# ── Commit e push ───────────────────────────────────────────────────────────────
log_h "Commit e Push"
commit_push "scenario(heavy-phpunit): adiciona 180 casos de teste (math+string+API flows)"

collect_all "$SCENARIO"

commit_metrics_to_main "$SCENARIO" "$GHA_NEW_LINE" "$JENKINS_NEW_LINE"
