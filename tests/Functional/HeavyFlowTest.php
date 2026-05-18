<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

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
        usleep(15_000);

        $token = $this->registerAndLogin(
            "User $userSuffix",
            "heavy_{$userSuffix}@test.com",
            'Password123!'
        );
        $this->assertNotEmpty($token, "Token vazio para $userSuffix");
        $this->withAuth($token);

        $created = $this->callExpense('store', [
            'description'    => $description,
            'amount'         => $amount,
            'category'       => $category,
            'payment_method' => 'pix',
            'expense_date'   => date('Y-m-d'),
        ]);
        $expenseId = $created['id'] ?? 0;
        $this->assertGreaterThan(0, $expenseId, "ID da despesa inválido");
        $this->assertEqualsWithDelta($amount, $created['amount'] ?? -1.0, 0.01);

        $updated = $this->callExpense('update', [
            'description'    => $description . ' (atualizado)',
            'amount'         => $updatedAmount,
            'category'       => $category,
            'payment_method' => 'pix',
            'expense_date'   => date('Y-m-d'),
        ], $expenseId);
        $this->assertEqualsWithDelta($updatedAmount, $updated['amount'] ?? -1.0, 0.01);

        $list = $this->callExpense('index');
        $this->assertIsArray($list);

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
            $desc = "Despesa de teste #{$i} para cenario full-heavy";
            $cases["fluxo_{$i}_{$cat}"] = ["u{$i}", $amt, $cat, $desc, $upd];
        }

        return $cases;
    }
}
