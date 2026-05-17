<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Config\Database;
use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\UserRepository;
use Tests\Support\DatabaseTestCase;

class BudgetRepositoryTest extends DatabaseTestCase
{
    private BudgetRepository  $repo;
    private int               $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $db         = Database::getConnection();
        $this->repo = new BudgetRepository($db);

        $userRepo     = new UserRepository($db);
        $user         = $userRepo->create('Richard', 'r@test.com', 'hash');
        $this->userId = $user->id;
    }

    private function createBudget(array $overrides = []): array
    {
        return array_merge([
            'user_id'  => $this->userId,
            'category' => 'alimentacao',
            'amount'   => 500.00,
            'month'    => 6,
            'year'     => 2025,
        ], $overrides);
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function testCreateReturnsBudgetWithId(): void
    {
        $budget = $this->repo->create($this->createBudget());

        $this->assertNotNull($budget->id);
        $this->assertSame($this->userId, $budget->userId);
        $this->assertSame('alimentacao', $budget->category);
        $this->assertEqualsWithDelta(500.00, $budget->amount, 0.001);
        $this->assertSame(6, $budget->month);
        $this->assertSame(2025, $budget->year);
    }

    public function testCreateMultipleBudgetsDifferentCategories(): void
    {
        $b1 = $this->repo->create($this->createBudget(['category' => 'alimentacao']));
        $b2 = $this->repo->create($this->createBudget(['category' => 'transporte']));

        $this->assertNotSame($b1->id, $b2->id);
        $this->assertSame('alimentacao', $b1->category);
        $this->assertSame('transporte', $b2->category);
    }

    // ── findById ──────────────────────────────────────────────────────────────

    public function testFindByIdReturnsCorrectBudget(): void
    {
        $created = $this->repo->create($this->createBudget());
        $found   = $this->repo->findById($created->id, $this->userId);

        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
    }

    public function testFindByIdReturnsNullForWrongUser(): void
    {
        $created = $this->repo->create($this->createBudget());
        $found   = $this->repo->findById($created->id, 9999);

        $this->assertNull($found);
    }

    public function testFindByIdReturnsNullWhenNotExists(): void
    {
        $this->assertNull($this->repo->findById(9999, $this->userId));
    }

    // ── findByCategory ────────────────────────────────────────────────────────

    public function testFindByCategoryReturnsBudget(): void
    {
        $this->repo->create($this->createBudget());
        $found = $this->repo->findByCategory($this->userId, 'alimentacao', 6, 2025);

        $this->assertNotNull($found);
        $this->assertSame('alimentacao', $found->category);
    }

    public function testFindByCategoryReturnsNullForDifferentMonth(): void
    {
        $this->repo->create($this->createBudget(['month' => 6]));
        $found = $this->repo->findByCategory($this->userId, 'alimentacao', 7, 2025);

        $this->assertNull($found);
    }

    public function testFindByCategoryReturnsNullForDifferentYear(): void
    {
        $this->repo->create($this->createBudget(['year' => 2025]));
        $found = $this->repo->findByCategory($this->userId, 'alimentacao', 6, 2026);

        $this->assertNull($found);
    }

    // ── findAllByUser ─────────────────────────────────────────────────────────

    public function testFindAllByUserReturnsOnlyUserBudgets(): void
    {
        $db       = Database::getConnection();
        $userRepo = new UserRepository($db);
        $other    = $userRepo->create('Outro', 'outro@test.com', 'hash');

        $this->repo->create($this->createBudget(['category' => 'alimentacao']));
        $this->repo->create($this->createBudget(['category' => 'transporte']));
        $this->repo->create($this->createBudget(['user_id' => $other->id, 'category' => 'lazer']));

        $results = $this->repo->findAllByUser($this->userId, 6, 2025);
        $this->assertCount(2, $results);
    }

    public function testFindAllByUserFiltersMonthAndYear(): void
    {
        $this->repo->create($this->createBudget(['month' => 6, 'year' => 2025]));
        $this->repo->create($this->createBudget(['month' => 7, 'year' => 2025, 'category' => 'transporte']));

        $results = $this->repo->findAllByUser($this->userId, 6, 2025);
        $this->assertCount(1, $results);
        $this->assertSame(6, $results[0]->month);
    }

    public function testFindAllByUserReturnsEmptyWhenNoBudgets(): void
    {
        $results = $this->repo->findAllByUser($this->userId, 6, 2025);
        $this->assertEmpty($results);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function testUpdateChangesAmount(): void
    {
        $created = $this->repo->create($this->createBudget(['amount' => 500.00]));
        $updated = $this->repo->update($created->id, $this->userId, 800.00);

        $this->assertNotNull($updated);
        $this->assertEqualsWithDelta(800.00, $updated->amount, 0.001);
    }

    public function testUpdateReturnsNullForWrongUser(): void
    {
        $created = $this->repo->create($this->createBudget());
        $updated = $this->repo->update($created->id, 9999, 800.00);

        $this->assertNull($updated);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $created = $this->repo->create($this->createBudget());
        $result  = $this->repo->delete($created->id, $this->userId);

        $this->assertTrue($result);
        $this->assertNull($this->repo->findById($created->id, $this->userId));
    }

    public function testDeleteReturnsFalseForWrongUser(): void
    {
        $created = $this->repo->create($this->createBudget());
        $result  = $this->repo->delete($created->id, 9999);

        $this->assertFalse($result);
    }

    // ── getTotalSpentByCategory ───────────────────────────────────────────────

    public function testGetTotalSpentReturnsZeroWithNoExpenses(): void
    {
        $total = $this->repo->getTotalSpentByCategory($this->userId, 'alimentacao', 6, 2025);
        $this->assertSame(0.0, $total);
    }

    public function testGetTotalSpentSumsExpensesInMonth(): void
    {
        $db          = Database::getConnection();
        $expenseRepo = new ExpenseRepository($db);

        $expenseRepo->create([
            'user_id' => $this->userId, 'description' => 'A', 'amount' => 100.0,
            'category' => 'alimentacao', 'payment_method' => 'pix', 'expense_date' => '2025-06-10', 'notes' => null,
        ]);
        $expenseRepo->create([
            'user_id' => $this->userId, 'description' => 'B', 'amount' => 50.0,
            'category' => 'alimentacao', 'payment_method' => 'pix', 'expense_date' => '2025-06-20', 'notes' => null,
        ]);

        $total = $this->repo->getTotalSpentByCategory($this->userId, 'alimentacao', 6, 2025);
        $this->assertEqualsWithDelta(150.0, $total, 0.001);
    }

    public function testGetTotalSpentIgnoresDifferentCategory(): void
    {
        $db          = Database::getConnection();
        $expenseRepo = new ExpenseRepository($db);

        $expenseRepo->create([
            'user_id' => $this->userId, 'description' => 'X', 'amount' => 200.0,
            'category' => 'transporte', 'payment_method' => 'pix', 'expense_date' => '2025-06-15', 'notes' => null,
        ]);

        $total = $this->repo->getTotalSpentByCategory($this->userId, 'alimentacao', 6, 2025);
        $this->assertSame(0.0, $total);
    }

    public function testGetTotalSpentIgnoresDifferentMonth(): void
    {
        $db          = Database::getConnection();
        $expenseRepo = new ExpenseRepository($db);

        $expenseRepo->create([
            'user_id' => $this->userId, 'description' => 'X', 'amount' => 300.0,
            'category' => 'alimentacao', 'payment_method' => 'pix', 'expense_date' => '2025-07-01', 'notes' => null,
        ]);

        $total = $this->repo->getTotalSpentByCategory($this->userId, 'alimentacao', 6, 2025);
        $this->assertSame(0.0, $total);
    }
}
