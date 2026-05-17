<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

class BudgetTest extends FunctionalTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->registerAndLogin();
        $this->withAuth($this->token);
    }

    private function validBudget(array $overrides = []): array
    {
        return array_merge([
            'category' => 'alimentacao',
            'amount'   => 500.00,
            'month'    => 6,
            'year'     => 2025,
        ], $overrides);
    }

    // ── POST /api/budgets ──────────────────────────────────────────────────────

    public function testCreateBudgetReturns201(): void
    {
        $response = $this->callBudget('store', $this->validBudget());

        $this->assertArrayHasKey('id', $response);
        $this->assertSame('alimentacao', $response['category']);
        $this->assertEqualsWithDelta(500.00, $response['amount'], 0.001);
        $this->assertSame(6, $response['month']);
        $this->assertSame(2025, $response['year']);
    }

    public function testCreateBudgetRequiresAuth(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $response = $this->callBudget('store', $this->validBudget());
        $this->assertArrayHasKey('error', $response);
    }

    public function testCreateBudgetInvalidCategory(): void
    {
        $response = $this->callBudget('store', $this->validBudget(['category' => 'invalida']));
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Categoria', $response['error']);
    }

    public function testCreateBudgetNegativeAmount(): void
    {
        $response = $this->callBudget('store', $this->validBudget(['amount' => -100]));
        $this->assertArrayHasKey('error', $response);
    }

    public function testCreateBudgetZeroAmount(): void
    {
        $response = $this->callBudget('store', $this->validBudget(['amount' => 0]));
        $this->assertArrayHasKey('error', $response);
    }

    public function testCreateBudgetInvalidMonth(): void
    {
        $response = $this->callBudget('store', $this->validBudget(['month' => 13]));
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('month', $response['error']);
    }

    public function testCreateBudgetInvalidYear(): void
    {
        $response = $this->callBudget('store', $this->validBudget(['year' => 1990]));
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('year', $response['error']);
    }

    public function testCreateBudgetSameCategoryUpdatesExisting(): void
    {
        $this->callBudget('store', $this->validBudget(['amount' => 500.00]));
        $second = $this->callBudget('store', $this->validBudget(['amount' => 800.00]));

        $this->assertArrayHasKey('id', $second);
        $this->assertEqualsWithDelta(800.00, $second['amount'], 0.001);
    }

    public function testCreateBudgetAllCategories(): void
    {
        $categories = ['alimentacao', 'transporte', 'moradia', 'saude', 'educacao'];
        foreach ($categories as $cat) {
            $response = $this->callBudget('store', $this->validBudget(['category' => $cat]));
            $this->assertArrayHasKey('id', $response, "Category '{$cat}' should succeed");
        }
    }

    // ── GET /api/budgets ───────────────────────────────────────────────────────

    public function testGetBudgetsReturnsEmptyArray(): void
    {
        $response = $this->callBudget('index', [], null, ['month' => 6, 'year' => 2025]);
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetBudgetsReturnsCreatedBudgets(): void
    {
        $this->callBudget('store', $this->validBudget(['category' => 'alimentacao']));
        $this->callBudget('store', $this->validBudget(['category' => 'transporte']));

        $response = $this->callBudget('index', [], null, ['month' => 6, 'year' => 2025]);
        $this->assertCount(2, $response);
    }

    public function testGetBudgetsIncludesStatusFields(): void
    {
        $this->callBudget('store', $this->validBudget());
        $response = $this->callBudget('index', [], null, ['month' => 6, 'year' => 2025]);

        $this->assertCount(1, $response);
        $this->assertArrayHasKey('spent', $response[0]);
        $this->assertArrayHasKey('remaining', $response[0]);
        $this->assertArrayHasKey('percentage', $response[0]);
        $this->assertArrayHasKey('alert', $response[0]);
    }

    public function testGetBudgetsAlertNoneWhenNoExpenses(): void
    {
        $this->callBudget('store', $this->validBudget());
        $response = $this->callBudget('index', [], null, ['month' => 6, 'year' => 2025]);

        $this->assertSame('none', $response[0]['alert']);
        $this->assertEqualsWithDelta(0.0, $response[0]['spent'], 0.001);
    }

    public function testGetBudgetsAlertWarningWhen80PercentReached(): void
    {
        $this->callBudget('store', $this->validBudget(['amount' => 100.00]));
        // cria gasto de 80%
        $this->callExpense('store', [
            'description'    => 'Gasto',
            'amount'         => 80.00,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-15',
        ]);

        $response = $this->callBudget('index', [], null, ['month' => 6, 'year' => 2025]);
        $this->assertSame('warning', $response[0]['alert']);
    }

    public function testGetBudgetsAlertExceededWhenOver100Percent(): void
    {
        $this->callBudget('store', $this->validBudget(['amount' => 100.00]));
        $this->callExpense('store', [
            'description'    => 'Gasto',
            'amount'         => 150.00,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-15',
        ]);

        $response = $this->callBudget('index', [], null, ['month' => 6, 'year' => 2025]);
        $this->assertSame('exceeded', $response[0]['alert']);
    }

    public function testGetBudgetsRequiresAuth(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $response = $this->callBudget('index', [], null, ['month' => 6, 'year' => 2025]);
        $this->assertArrayHasKey('error', $response);
    }

    public function testGetBudgetsInvalidMonthReturnsError(): void
    {
        $response = $this->callBudget('index', [], null, ['month' => 0, 'year' => 2025]);
        $this->assertArrayHasKey('error', $response);
    }

    // ── PUT /api/budgets/{id} ──────────────────────────────────────────────────

    public function testUpdateBudgetChangesAmount(): void
    {
        $created  = $this->callBudget('store', $this->validBudget(['amount' => 500.00]));
        $updated  = $this->callBudget('update', ['amount' => 800.00], $created['id']);

        $this->assertEqualsWithDelta(800.00, $updated['amount'], 0.001);
    }

    public function testUpdateBudgetNotFoundReturns404(): void
    {
        $response = $this->callBudget('update', ['amount' => 800.00], 9999);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('não encontrado', $response['error']);
    }

    public function testUpdateBudgetInvalidAmountReturnsError(): void
    {
        $created  = $this->callBudget('store', $this->validBudget());
        $response = $this->callBudget('update', ['amount' => -50], $created['id']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testUpdateBudgetRequiresAuth(): void
    {
        $created = $this->callBudget('store', $this->validBudget());
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $response = $this->callBudget('update', ['amount' => 800.00], $created['id']);
        $this->assertArrayHasKey('error', $response);
    }
}
