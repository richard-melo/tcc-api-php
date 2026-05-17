<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Budget;
use PHPUnit\Framework\TestCase;

class BudgetTest extends TestCase
{
    private function makeData(array $overrides = []): array
    {
        return array_merge([
            'id'         => 1,
            'user_id'    => 42,
            'category'   => 'alimentacao',
            'amount'     => 500.00,
            'month'      => 6,
            'year'       => 2025,
            'created_at' => '2025-06-01 00:00:00',
            'updated_at' => '2025-06-01 00:00:00',
        ], $overrides);
    }

    public function testFromArrayCreatesModel(): void
    {
        $budget = Budget::fromArray($this->makeData());

        $this->assertSame(1, $budget->id);
        $this->assertSame(42, $budget->userId);
        $this->assertSame('alimentacao', $budget->category);
        $this->assertEqualsWithDelta(500.00, $budget->amount, 0.001);
        $this->assertSame(6, $budget->month);
        $this->assertSame(2025, $budget->year);
    }

    public function testFromArrayWithNullId(): void
    {
        $budget = Budget::fromArray($this->makeData(['id' => null]));
        $this->assertNull($budget->id);
    }

    public function testFromArrayCastsTypes(): void
    {
        $budget = Budget::fromArray($this->makeData([
            'id'      => '5',
            'user_id' => '10',
            'amount'  => '250.75',
            'month'   => '3',
            'year'    => '2026',
        ]));

        $this->assertIsInt($budget->id);
        $this->assertIsInt($budget->userId);
        $this->assertIsFloat($budget->amount);
        $this->assertIsInt($budget->month);
        $this->assertIsInt($budget->year);
    }

    public function testToArrayReturnsAllFields(): void
    {
        $budget = Budget::fromArray($this->makeData());
        $arr    = $budget->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('user_id', $arr);
        $this->assertArrayHasKey('category', $arr);
        $this->assertArrayHasKey('amount', $arr);
        $this->assertArrayHasKey('month', $arr);
        $this->assertArrayHasKey('year', $arr);
        $this->assertArrayHasKey('created_at', $arr);
        $this->assertArrayHasKey('updated_at', $arr);
    }

    public function testToArrayValuesMatchConstructor(): void
    {
        $data   = $this->makeData();
        $budget = Budget::fromArray($data);
        $arr    = $budget->toArray();

        $this->assertSame(1, $arr['id']);
        $this->assertSame(42, $arr['user_id']);
        $this->assertSame('alimentacao', $arr['category']);
        $this->assertEqualsWithDelta(500.00, $arr['amount'], 0.001);
        $this->assertSame(6, $arr['month']);
        $this->assertSame(2025, $arr['year']);
    }

    public function testRoundTripFromArrayToArray(): void
    {
        $data   = $this->makeData();
        $budget = Budget::fromArray($data);
        $arr    = $budget->toArray();

        $this->assertSame($data['category'], $arr['category']);
        $this->assertEqualsWithDelta($data['amount'], $arr['amount'], 0.001);
        $this->assertSame($data['month'], $arr['month']);
        $this->assertSame($data['year'], $arr['year']);
    }
}
