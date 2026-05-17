<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Expense;
use App\Repositories\ExpenseRepository;
use App\Services\ExportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportServiceTest extends TestCase
{
    private ExportService $service;
    /** @var ExpenseRepository&MockObject */
    private ExpenseRepository $repo;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(ExpenseRepository::class);
        $this->service = new ExportService($this->repo);
    }

    private function makeExpense(array $overrides = []): Expense
    {
        return Expense::fromArray(array_merge([
            'id'             => 1,
            'user_id'        => 10,
            'description'    => 'Almoço',
            'amount'         => 42.50,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-15',
            'notes'          => null,
            'created_at'     => '2025-06-15 12:00:00',
            'updated_at'     => '2025-06-15 12:00:00',
        ], $overrides));
    }

    // ── toCsv ─────────────────────────────────────────────────────────────────

    public function testToCsvWithNoExpensesReturnsHeaderOnly(): void
    {
        $csv = $this->service->toCsv([]);
        $this->assertStringContainsString('id,description', $csv);
        $lines = explode("\n", $csv);
        $this->assertCount(1, $lines);
    }

    public function testToCsvWithOneExpenseHasTwoLines(): void
    {
        $csv   = $this->service->toCsv([$this->makeExpense()]);
        $lines = explode("\n", $csv);
        $this->assertCount(2, $lines);
    }

    public function testToCsvContainsExpenseData(): void
    {
        $csv = $this->service->toCsv([$this->makeExpense()]);
        $this->assertStringContainsString('Almoço', $csv);
        $this->assertStringContainsString('42.50', $csv);
        $this->assertStringContainsString('alimentacao', $csv);
        $this->assertStringContainsString('pix', $csv);
        $this->assertStringContainsString('2025-06-15', $csv);
    }

    public function testToCsvEscapesDescriptionWithComma(): void
    {
        $expense = $this->makeExpense(['description' => 'Almoço, jantar']);
        $csv     = $this->service->toCsv([$expense]);
        $this->assertStringContainsString('"Almoço, jantar"', $csv);
    }

    public function testToCsvEscapesDescriptionWithQuotes(): void
    {
        $expense = $this->makeExpense(['description' => 'Restaurante "Bom"']);
        $csv     = $this->service->toCsv([$expense]);
        $this->assertStringContainsString('"Restaurante ""Bom"""', $csv);
    }

    public function testToCsvAmountFormattedWithTwoDecimals(): void
    {
        $expense = $this->makeExpense(['amount' => 100.0]);
        $csv     = $this->service->toCsv([$expense]);
        $this->assertStringContainsString('100.00', $csv);
    }

    public function testToCsvMultipleExpenses(): void
    {
        $expenses = [
            $this->makeExpense(['id' => 1, 'amount' => 10.0]),
            $this->makeExpense(['id' => 2, 'amount' => 20.0]),
            $this->makeExpense(['id' => 3, 'amount' => 30.0]),
        ];
        $csv   = $this->service->toCsv($expenses);
        $lines = explode("\n", $csv);
        $this->assertCount(4, $lines); // header + 3 rows
    }

    // ── getSummary ────────────────────────────────────────────────────────────

    public function testSummaryWithNoExpenses(): void
    {
        $this->repo->method('findAllByUser')->willReturn([]);
        $this->repo->method('getSummaryByCategory')->willReturn([]);

        $summary = $this->service->getSummary(10, '2025-06-01', '2025-06-30');

        $this->assertSame(0.0, $summary['total']);
        $this->assertSame(0, $summary['count']);
        $this->assertSame(0.0, $summary['average']);
        $this->assertNull($summary['highest']);
        $this->assertNull($summary['lowest']);
        $this->assertEmpty($summary['by_category']);
    }

    public function testSummaryPeriodFields(): void
    {
        $this->repo->method('findAllByUser')->willReturn([]);
        $this->repo->method('getSummaryByCategory')->willReturn([]);

        $summary = $this->service->getSummary(10, '2025-06-01', '2025-06-30');

        $this->assertSame('2025-06-01', $summary['period']['start']);
        $this->assertSame('2025-06-30', $summary['period']['end']);
    }

    public function testSummaryTotalAndCount(): void
    {
        $expenses = [
            $this->makeExpense(['id' => 1, 'amount' => 100.0]),
            $this->makeExpense(['id' => 2, 'amount' => 200.0]),
            $this->makeExpense(['id' => 3, 'amount' => 50.0]),
        ];

        $this->repo->method('findAllByUser')->willReturn($expenses);
        $this->repo->method('getSummaryByCategory')->willReturn([]);

        $summary = $this->service->getSummary(10, '2025-06-01', '2025-06-30');

        $this->assertEqualsWithDelta(350.0, $summary['total'], 0.01);
        $this->assertSame(3, $summary['count']);
        $this->assertEqualsWithDelta(116.67, $summary['average'], 0.01);
    }

    public function testSummaryHighestAndLowest(): void
    {
        $expenses = [
            $this->makeExpense(['id' => 1, 'amount' => 100.0, 'description' => 'Médio']),
            $this->makeExpense(['id' => 2, 'amount' => 500.0, 'description' => 'Caro']),
            $this->makeExpense(['id' => 3, 'amount' => 10.0,  'description' => 'Barato']),
        ];

        $this->repo->method('findAllByUser')->willReturn($expenses);
        $this->repo->method('getSummaryByCategory')->willReturn([]);

        $summary = $this->service->getSummary(10, '2025-06-01', '2025-06-30');

        $this->assertEqualsWithDelta(500.0, $summary['highest']['amount'], 0.01);
        $this->assertSame('Caro', $summary['highest']['description']);
        $this->assertEqualsWithDelta(10.0, $summary['lowest']['amount'], 0.01);
        $this->assertSame('Barato', $summary['lowest']['description']);
    }

    public function testSummaryByCategory(): void
    {
        $categoryData = [
            ['category' => 'alimentacao', 'total_count' => 5, 'total_amount' => 250.0, 'avg_amount' => 50.0, 'min_amount' => 20.0, 'max_amount' => 100.0],
        ];

        $this->repo->method('findAllByUser')->willReturn([$this->makeExpense()]);
        $this->repo->method('getSummaryByCategory')->willReturn($categoryData);

        $summary = $this->service->getSummary(10, '2025-06-01', '2025-06-30');

        $this->assertCount(1, $summary['by_category']);
        $this->assertSame('alimentacao', $summary['by_category'][0]['category']);
    }

    // ── getExpenses ───────────────────────────────────────────────────────────

    public function testGetExpensesDelegatesToRepository(): void
    {
        $expenses = [$this->makeExpense()];
        $this->repo->method('findAllByUser')->willReturn($expenses);

        $result = $this->service->getExpenses(10, []);
        $this->assertCount(1, $result);
    }
}
