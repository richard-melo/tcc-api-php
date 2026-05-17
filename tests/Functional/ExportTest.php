<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

class ExportTest extends FunctionalTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->registerAndLogin();
        $this->withAuth($this->token);
    }

    private function createExpense(array $overrides = []): array
    {
        return $this->callExpense('store', array_merge([
            'description'    => 'Gasto teste',
            'amount'         => 50.00,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-15',
        ], $overrides));
    }

    // ── GET /api/exports/csv ───────────────────────────────────────────────────

    public function testExportCsvRequiresAuth(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $result = $this->callExport('csv');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testExportCsvWithNoExpensesReturnsHeaderOnly(): void
    {
        $csv = $this->callExport('csv');
        $this->assertIsString($csv);
        $this->assertStringContainsString('id,description', $csv);
        $lines = explode("\n", trim($csv));
        $this->assertCount(1, $lines);
    }

    public function testExportCsvWithExpensesHasDataRows(): void
    {
        $this->createExpense(['amount' => 100.0]);
        $this->createExpense(['amount' => 200.0]);

        $csv = $this->callExport('csv');
        $this->assertIsString($csv);
        $lines = explode("\n", trim($csv));
        $this->assertCount(3, $lines); // header + 2 rows
    }

    public function testExportCsvContainsCorrectData(): void
    {
        $this->createExpense(['description' => 'Almoço especial', 'amount' => 75.50]);

        $csv = $this->callExport('csv');
        $this->assertStringContainsString('Almoço especial', $csv);
        $this->assertStringContainsString('75.50', $csv);
        $this->assertStringContainsString('alimentacao', $csv);
    }

    public function testExportCsvWithCategoryFilter(): void
    {
        $this->createExpense(['category' => 'alimentacao', 'amount' => 50.0]);
        $this->createExpense(['category' => 'transporte',  'amount' => 30.0]);

        $csv   = $this->callExport('csv', ['category' => 'alimentacao']);
        $this->assertIsString($csv);
        $lines = explode("\n", trim($csv));
        $this->assertCount(2, $lines); // header + 1 row
    }

    public function testExportCsvWithDateFilter(): void
    {
        $this->createExpense(['expense_date' => '2025-06-15', 'amount' => 50.0]);
        $this->createExpense(['expense_date' => '2025-07-10', 'amount' => 30.0]);

        $csv   = $this->callExport('csv', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);
        $this->assertIsString($csv);
        $lines = explode("\n", trim($csv));
        $this->assertCount(2, $lines); // header + 1 row
    }

    // ── GET /api/exports/summary ───────────────────────────────────────────────

    public function testExportSummaryRequiresAuth(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $result = $this->callExport('summary');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testExportSummaryWithNoExpenses(): void
    {
        $result = $this->callExport('summary', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);
        $this->assertIsString($result);
        $summary = json_decode($result, true);

        $this->assertEqualsWithDelta(0.0, $summary['total'], 0.001);
        $this->assertSame(0, $summary['count']);
        $this->assertNull($summary['highest']);
        $this->assertNull($summary['lowest']);
    }

    public function testExportSummaryWithExpenses(): void
    {
        $this->createExpense(['amount' => 100.0, 'expense_date' => '2025-06-10']);
        $this->createExpense(['amount' => 200.0, 'expense_date' => '2025-06-20']);
        $this->createExpense(['amount' => 50.0,  'expense_date' => '2025-06-25']);

        $result  = $this->callExport('summary', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);
        $this->assertIsString($result);
        $summary = json_decode($result, true);

        $this->assertEqualsWithDelta(350.0, $summary['total'], 0.01);
        $this->assertSame(3, $summary['count']);
        $this->assertEqualsWithDelta(116.67, $summary['average'], 0.01);
    }

    public function testExportSummaryHighestAndLowest(): void
    {
        $this->createExpense(['description' => 'Caro',   'amount' => 500.0, 'expense_date' => '2025-06-10']);
        $this->createExpense(['description' => 'Barato', 'amount' => 10.0,  'expense_date' => '2025-06-15']);

        $result  = $this->callExport('summary', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);
        $summary = json_decode($result, true);

        $this->assertSame('Caro',   $summary['highest']['description']);
        $this->assertSame('Barato', $summary['lowest']['description']);
    }

    public function testExportSummaryPeriodFields(): void
    {
        $result  = $this->callExport('summary', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);
        $summary = json_decode($result, true);

        $this->assertSame('2025-06-01', $summary['period']['start']);
        $this->assertSame('2025-06-30', $summary['period']['end']);
    }

    public function testExportSummaryByCategoryBreakdown(): void
    {
        $this->createExpense(['category' => 'alimentacao', 'amount' => 100.0, 'expense_date' => '2025-06-10']);
        $this->createExpense(['category' => 'transporte',  'amount' => 50.0,  'expense_date' => '2025-06-15']);

        $result  = $this->callExport('summary', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);
        $summary = json_decode($result, true);

        $this->assertCount(2, $summary['by_category']);
    }

    public function testExportSummaryUserIsolation(): void
    {
        $this->createExpense(['amount' => 500.0, 'expense_date' => '2025-06-10']);

        // Outro usuário
        $otherToken = $this->registerAndLogin('Outro', 'outro@test.com');
        $this->withAuth($otherToken);
        $result  = $this->callExport('summary', ['start_date' => '2025-06-01', 'end_date' => '2025-06-30']);
        $summary = json_decode($result, true);

        $this->assertSame(0, $summary['count']);
    }
}
