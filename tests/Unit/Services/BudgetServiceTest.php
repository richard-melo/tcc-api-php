<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Budget;
use App\Repositories\BudgetRepository;
use App\Services\BudgetService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BudgetServiceTest extends TestCase
{
    private BudgetService $service;
    /** @var BudgetRepository&MockObject */
    private BudgetRepository $repo;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(BudgetRepository::class);
        $this->service = new BudgetService($this->repo);
    }

    private function makeBudget(array $overrides = []): Budget
    {
        return Budget::fromArray(array_merge([
            'id'         => 1,
            'user_id'    => 10,
            'category'   => 'alimentacao',
            'amount'     => 500.00,
            'month'      => 6,
            'year'       => 2025,
            'created_at' => '',
            'updated_at' => '',
        ], $overrides));
    }

    // ── createOrUpdate ────────────────────────────────────────────────────────

    public function testCreateOrUpdateCreatesWhenNotExists(): void
    {
        $budget = $this->makeBudget();

        $this->repo->method('findByCategory')->willReturn(null);
        $this->repo->expects($this->once())->method('create')->willReturn($budget);
        $this->repo->expects($this->never())->method('update');

        $result = $this->service->createOrUpdate(10, 'alimentacao', 500.00, 6, 2025);
        $this->assertSame(1, $result->id);
    }

    public function testCreateOrUpdateUpdatesWhenExists(): void
    {
        $existing = $this->makeBudget();
        $updated  = $this->makeBudget(['amount' => 750.00]);

        $this->repo->method('findByCategory')->willReturn($existing);
        $this->repo->expects($this->never())->method('create');
        $this->repo->expects($this->once())->method('update')->willReturn($updated);

        $result = $this->service->createOrUpdate(10, 'alimentacao', 750.00, 6, 2025);
        $this->assertEqualsWithDelta(750.00, $result->amount, 0.001);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function testUpdateDelegatesToRepository(): void
    {
        $budget = $this->makeBudget(['amount' => 300.00]);
        $this->repo->method('update')->willReturn($budget);

        $result = $this->service->update(1, 10, 300.00);
        $this->assertEqualsWithDelta(300.00, $result->amount, 0.001);
    }

    public function testUpdateReturnsNullWhenNotFound(): void
    {
        $this->repo->method('update')->willReturn(null);
        $this->assertNull($this->service->update(999, 10, 100.00));
    }

    // ── checkStatus — alertas ─────────────────────────────────────────────────

    public function testAlertNoneWhenBelow80Percent(): void
    {
        $budget = $this->makeBudget(['amount' => 500.00]);
        $this->repo->method('findByCategory')->willReturn($budget);
        $this->repo->method('getTotalSpentByCategory')->willReturn(300.00); // 60%

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertSame(BudgetService::ALERT_NONE, $result['alert']);
        $this->assertEqualsWithDelta(60.0, $result['percentage'], 0.01);
    }

    public function testAlertWarningWhenExactly80Percent(): void
    {
        $budget = $this->makeBudget(['amount' => 500.00]);
        $this->repo->method('findByCategory')->willReturn($budget);
        $this->repo->method('getTotalSpentByCategory')->willReturn(400.00); // 80%

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertSame(BudgetService::ALERT_WARNING, $result['alert']);
        $this->assertEqualsWithDelta(80.0, $result['percentage'], 0.01);
    }

    public function testAlertWarningWhenBetween80And100Percent(): void
    {
        $budget = $this->makeBudget(['amount' => 500.00]);
        $this->repo->method('findByCategory')->willReturn($budget);
        $this->repo->method('getTotalSpentByCategory')->willReturn(450.00); // 90%

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertSame(BudgetService::ALERT_WARNING, $result['alert']);
    }

    public function testAlertExceededWhenExactly100Percent(): void
    {
        $budget = $this->makeBudget(['amount' => 500.00]);
        $this->repo->method('findByCategory')->willReturn($budget);
        $this->repo->method('getTotalSpentByCategory')->willReturn(500.00); // 100%

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertSame(BudgetService::ALERT_EXCEEDED, $result['alert']);
    }

    public function testAlertExceededWhenOver100Percent(): void
    {
        $budget = $this->makeBudget(['amount' => 500.00]);
        $this->repo->method('findByCategory')->willReturn($budget);
        $this->repo->method('getTotalSpentByCategory')->willReturn(600.00); // 120%

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertSame(BudgetService::ALERT_EXCEEDED, $result['alert']);
    }

    public function testRemainingIsZeroWhenExceeded(): void
    {
        $budget = $this->makeBudget(['amount' => 500.00]);
        $this->repo->method('findByCategory')->willReturn($budget);
        $this->repo->method('getTotalSpentByCategory')->willReturn(600.00);

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertSame(0.0, $result['remaining']);
    }

    public function testRemainingCalculation(): void
    {
        $budget = $this->makeBudget(['amount' => 500.00]);
        $this->repo->method('findByCategory')->willReturn($budget);
        $this->repo->method('getTotalSpentByCategory')->willReturn(200.00);

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertEqualsWithDelta(300.00, $result['remaining'], 0.01);
    }

    public function testStatusWhenNoBudget(): void
    {
        $this->repo->method('findByCategory')->willReturn(null);

        $result = $this->service->checkStatus(10, 'alimentacao', 6, 2025);

        $this->assertNull($result['budget']);
        $this->assertSame(BudgetService::ALERT_NONE, $result['alert']);
    }

    // ── getByUser ─────────────────────────────────────────────────────────────

    public function testGetByUserReturnsMappedBudgets(): void
    {
        $budgets = [
            $this->makeBudget(['category' => 'alimentacao']),
            $this->makeBudget(['id' => 2, 'category' => 'transporte', 'amount' => 200.00]),
        ];

        $this->repo->method('findAllByUser')->willReturn($budgets);
        $this->repo->method('getTotalSpentByCategory')->willReturn(0.0);

        $result = $this->service->getByUser(10, 6, 2025);

        $this->assertCount(2, $result);
        $this->assertSame(BudgetService::ALERT_NONE, $result[0]['alert']);
        $this->assertSame(BudgetService::ALERT_NONE, $result[1]['alert']);
    }
}
