<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Config\Database;
use App\Repositories\ExpenseRepository;
use App\Repositories\UserRepository;
use Tests\Support\DatabaseTestCase;

class ExpenseRepositoryTest extends DatabaseTestCase
{
    private ExpenseRepository $expenseRepo;
    private int               $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $db               = Database::getConnection();
        $userRepo         = new UserRepository($db);
        $this->expenseRepo = new ExpenseRepository($db);

        $user           = $userRepo->create('Richard', 'richard@teste.com', 'hashed');
        $this->userId   = $user->id;
    }

    private function makeData(array $overrides = []): array
    {
        return array_merge([
            'user_id'        => $this->userId,
            'description'    => 'Almoço no restaurante',
            'amount'         => 45.90,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-01',
            'notes'          => null,
        ], $overrides);
    }

    public function testCreateAndFindById(): void
    {
        $expense = $this->expenseRepo->create($this->makeData());

        $this->assertNotNull($expense->id);
        $this->assertSame($this->userId, $expense->userId);
        $this->assertSame(45.90, $expense->amount);
        $this->assertSame('alimentacao', $expense->category);

        $found = $this->expenseRepo->findById($expense->id, $this->userId);
        $this->assertNotNull($found);
        $this->assertSame($expense->id, $found->id);
    }

    public function testFindByIdReturnsNullForWrongUser(): void
    {
        $expense = $this->expenseRepo->create($this->makeData());

        $found = $this->expenseRepo->findById($expense->id, userId: 9999);
        $this->assertNull($found);
    }

    public function testFindAllByUserReturnsOnlyOwnExpenses(): void
    {
        $this->expenseRepo->create($this->makeData(['description' => 'Gasto 1']));
        $this->expenseRepo->create($this->makeData(['description' => 'Gasto 2']));

        $expenses = $this->expenseRepo->findAllByUser($this->userId);

        $this->assertCount(2, $expenses);
    }

    public function testFindAllByUserFiltersByCategory(): void
    {
        $this->expenseRepo->create($this->makeData(['category' => 'alimentacao']));
        $this->expenseRepo->create($this->makeData(['category' => 'transporte']));

        $result = $this->expenseRepo->findAllByUser($this->userId, ['category' => 'transporte']);

        $this->assertCount(1, $result);
        $this->assertSame('transporte', $result[0]->category);
    }

    public function testFindAllByUserFiltersByDateRange(): void
    {
        $this->expenseRepo->create($this->makeData(['expense_date' => '2025-05-15']));
        $this->expenseRepo->create($this->makeData(['expense_date' => '2025-06-01']));
        $this->expenseRepo->create($this->makeData(['expense_date' => '2025-07-20']));

        $result = $this->expenseRepo->findAllByUser($this->userId, [
            'start_date' => '2025-06-01',
            'end_date'   => '2025-06-30',
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('2025-06-01', $result[0]->expenseDate);
    }

    public function testUpdate(): void
    {
        $expense = $this->expenseRepo->create($this->makeData());

        $updated = $this->expenseRepo->update($expense->id, $this->userId, [
            'description' => 'Jantar atualizado',
            'amount'      => 80.00,
        ]);

        $this->assertSame('Jantar atualizado', $updated->description);
        $this->assertSame(80.00, $updated->amount);
        $this->assertSame('alimentacao', $updated->category); // não alterada
    }

    public function testUpdateReturnsNullForWrongUser(): void
    {
        $expense = $this->expenseRepo->create($this->makeData());

        $result = $this->expenseRepo->update($expense->id, userId: 9999, fields: ['description' => 'Hack']);

        $this->assertNull($result);
    }

    public function testDelete(): void
    {
        $expense = $this->expenseRepo->create($this->makeData());

        $deleted = $this->expenseRepo->delete($expense->id, $this->userId);

        $this->assertTrue($deleted);
        $this->assertNull($this->expenseRepo->findById($expense->id, $this->userId));
    }

    public function testDeleteReturnsFalseForWrongUser(): void
    {
        $expense = $this->expenseRepo->create($this->makeData());

        $deleted = $this->expenseRepo->delete($expense->id, userId: 9999);

        $this->assertFalse($deleted);
    }

    public function testGetSummaryByCategory(): void
    {
        $this->expenseRepo->create($this->makeData(['category' => 'alimentacao', 'amount' => 50.00, 'expense_date' => '2025-06-05']));
        $this->expenseRepo->create($this->makeData(['category' => 'alimentacao', 'amount' => 30.00, 'expense_date' => '2025-06-10']));
        $this->expenseRepo->create($this->makeData(['category' => 'transporte', 'amount' => 20.00, 'expense_date' => '2025-06-15']));

        $summary = $this->expenseRepo->getSummaryByCategory($this->userId, '2025-06-01', '2025-06-30');

        $this->assertCount(2, $summary);

        // alimentacao deve vir primeiro (maior total)
        $this->assertSame('alimentacao', $summary[0]['category']);
        $this->assertSame('2', $summary[0]['total_count']);
        $this->assertEqualsWithDelta(80.00, (float) $summary[0]['total_amount'], 0.01);
    }

    public function testGetSummaryExcludesOtherUsers(): void
    {
        $this->expenseRepo->create($this->makeData(['user_id' => $this->userId, 'amount' => 100.00, 'expense_date' => '2025-06-01']));

        $summary = $this->expenseRepo->getSummaryByCategory(userId: 9999, startDate: '2025-06-01', endDate: '2025-06-30');

        $this->assertCount(0, $summary);
    }
}
