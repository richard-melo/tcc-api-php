<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

/**
 * Testes funcionais do endpoint de relatório.
 * GET /api/reports/summary
 */
class ReportTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $token = $this->registerAndLogin();
        $this->withAuth($token);

        // Seed: gastos em junho de 2025
        $expenses = [
            ['category' => 'alimentacao', 'amount' => 150.00, 'payment_method' => 'pix',      'expense_date' => '2025-06-05'],
            ['category' => 'alimentacao', 'amount' => 80.00,  'payment_method' => 'debito',   'expense_date' => '2025-06-12'],
            ['category' => 'transporte',  'amount' => 45.00,  'payment_method' => 'credito',  'expense_date' => '2025-06-18'],
            ['category' => 'moradia',     'amount' => 900.00, 'payment_method' => 'transferencia', 'expense_date' => '2025-06-01'],
            ['category' => 'lazer',       'amount' => 60.00,  'payment_method' => 'dinheiro', 'expense_date' => '2025-06-25'],
        ];

        foreach ($expenses as $e) {
            $this->callExpense('store', array_merge([
                'description' => 'Gasto de teste',
            ], $e));
        }
    }

    public function testSummaryReturnsCorrectStructure(): void
    {
        $response = $this->callReport('summary', [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);

        $this->assertArrayHasKey('period', $response);
        $this->assertArrayHasKey('total_amount', $response);
        $this->assertArrayHasKey('total_expenses', $response);
        $this->assertArrayHasKey('by_category', $response);

        $this->assertSame('2025-06-01', $response['period']['start']);
        $this->assertSame('2025-06-30', $response['period']['end']);
    }

    public function testSummaryTotalAmountIsCorrect(): void
    {
        $response = $this->callReport('summary', [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);

        // 150 + 80 + 45 + 900 + 60 = 1235
        $this->assertEqualsWithDelta(1235.00, $response['total_amount'], 0.01);
        $this->assertSame(5, $response['total_expenses']);
    }

    public function testSummaryGroupsByCategory(): void
    {
        $response = $this->callReport('summary', [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);

        $this->assertCount(4, $response['by_category']);

        $categories = array_column($response['by_category'], 'category');
        $this->assertContains('alimentacao', $categories);
        $this->assertContains('moradia', $categories);
        $this->assertContains('transporte', $categories);
        $this->assertContains('lazer', $categories);
    }

    public function testSummaryCalculatesPercentages(): void
    {
        $response    = $this->callReport('summary', [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);
        $byCategory  = $response['by_category'];

        // moradia = 900 / 1235 ≈ 72.87%
        $moradia = array_filter($byCategory, fn($c) => $c['category'] === 'moradia');
        $moradia = array_values($moradia)[0];

        $this->assertEqualsWithDelta(72.87, $moradia['percentage'], 0.1);
    }

    public function testSummaryAggregatesMultipleExpensesInSameCategory(): void
    {
        $response   = $this->callReport('summary', [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);
        $byCategory = $response['by_category'];

        $alimentacao = array_values(array_filter($byCategory, fn($c) => $c['category'] === 'alimentacao'))[0];

        $this->assertSame(2, $alimentacao['total_count']);
        $this->assertEqualsWithDelta(230.00, $alimentacao['total_amount'], 0.01);
        $this->assertEqualsWithDelta(115.00, $alimentacao['avg_amount'], 0.01);
        $this->assertEqualsWithDelta(80.00,  $alimentacao['min_amount'], 0.01);
        $this->assertEqualsWithDelta(150.00, $alimentacao['max_amount'], 0.01);
    }

    public function testSummaryReturnsEmptyForPeriodWithNoExpenses(): void
    {
        $response = $this->callReport('summary', [
            'start_date' => '2020-01-01',
            'end_date'   => '2020-01-31',
        ]);

        $this->assertEqualsWithDelta(0.0, $response['total_amount'], 0.01);
        $this->assertSame(0, $response['total_expenses']);
        $this->assertCount(0, $response['by_category']);
    }

    public function testSummaryRequiresAuthentication(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $response = $this->callReport('summary', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);

        $this->assertArrayHasKey('error', $response);
    }

    public function testSummaryDefaultsToCurrentMonth(): void
    {
        // Sem passar datas — deve retornar algo (estrutura correta)
        $response = $this->callReport('summary');

        $this->assertArrayHasKey('period', $response);
        $this->assertArrayHasKey('total_amount', $response);
    }

    public function testSummaryMinMaxAmountsAreCorrect(): void
    {
        $response   = $this->callReport('summary', [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);
        $byCategory = $response['by_category'];

        // alimentacao: 150 e 80
        $alimentacao = array_values(array_filter($byCategory, fn($c) => $c['category'] === 'alimentacao'))[0];

        $this->assertEqualsWithDelta(80.00,  $alimentacao['min_amount'], 0.01);
        $this->assertEqualsWithDelta(150.00, $alimentacao['max_amount'], 0.01);
    }

    public function testSummaryOnlyIncludesCurrentUserData(): void
    {
        // Usuário isolado sem nenhum gasto no período
        $otherToken = $this->registerAndLogin('Outro', 'outro@teste.com', 'senha456');
        $this->withAuth($otherToken);

        $response = $this->callReport('summary', [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);

        // Este usuário não tem gastos em junho — deve retornar zero
        $this->assertEqualsWithDelta(0.0, $response['total_amount'], 0.01);
        $this->assertSame(0, $response['total_expenses']);
    }
}
