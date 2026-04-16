<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Expense;
use PHPUnit\Framework\TestCase;

class ExpenseTest extends TestCase
{
    public function testFromArrayCreatesExpenseCorrectly(): void
    {
        $expense = Expense::fromArray([
            'id'             => 5,
            'user_id'        => 1,
            'description'    => 'Almoço',
            'amount'         => 35.90,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-01',
            'notes'          => 'restaurante',
        ]);

        $this->assertSame(5, $expense->id);
        $this->assertSame(1, $expense->userId);
        $this->assertSame(35.90, $expense->amount);
        $this->assertSame('alimentacao', $expense->category);
        $this->assertSame('pix', $expense->paymentMethod);
    }

    public function testToArrayContainsAllFields(): void
    {
        $expense = Expense::fromArray([
            'id'             => 1,
            'user_id'        => 1,
            'description'    => 'Uber',
            'amount'         => 18.50,
            'category'       => 'transporte',
            'payment_method' => 'credito',
            'expense_date'   => '2025-06-10',
            'notes'          => null,
        ]);

        $arr = $expense->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('user_id', $arr);
        $this->assertArrayHasKey('description', $arr);
        $this->assertArrayHasKey('amount', $arr);
        $this->assertArrayHasKey('category', $arr);
        $this->assertArrayHasKey('payment_method', $arr);
        $this->assertArrayHasKey('expense_date', $arr);
        $this->assertArrayHasKey('notes', $arr);
        $this->assertNull($arr['notes']);
    }

    public function testCategoriesConstantContainsAllExpectedValues(): void
    {
        $expected = ['alimentacao', 'transporte', 'moradia', 'saude', 'educacao', 'lazer', 'compras', 'outros'];

        $this->assertSame($expected, Expense::CATEGORIES);
    }

    public function testPaymentMethodsConstantContainsAllExpectedValues(): void
    {
        $expected = ['dinheiro', 'credito', 'debito', 'pix', 'transferencia'];

        $this->assertSame($expected, Expense::PAYMENT_METHODS);
    }

    public function testFromArrayWithNullNotes(): void
    {
        $expense = Expense::fromArray([
            'id'             => 1,
            'user_id'        => 1,
            'description'    => 'Farmácia',
            'amount'         => 45.00,
            'category'       => 'saude',
            'payment_method' => 'debito',
            'expense_date'   => '2025-06-01',
            'notes'          => null,
        ]);

        $this->assertNull($expense->notes);
    }

    public function testFromArrayMapsTimestamps(): void
    {
        $expense = Expense::fromArray([
            'id'             => 1,
            'user_id'        => 1,
            'description'    => 'Livro',
            'amount'         => 59.90,
            'category'       => 'educacao',
            'payment_method' => 'credito',
            'expense_date'   => '2025-06-05',
            'created_at'     => '2025-06-05 09:00:00',
            'updated_at'     => '2025-06-05 09:00:00',
        ]);

        $this->assertSame('2025-06-05 09:00:00', $expense->createdAt);
        $this->assertSame('2025-06-05 09:00:00', $expense->updatedAt);
    }

    public function testAmountIsCastToFloat(): void
    {
        $expense = Expense::fromArray([
            'id'             => 1,
            'user_id'        => 1,
            'description'    => 'Gasolina',
            'amount'         => '120',
            'category'       => 'transporte',
            'payment_method' => 'dinheiro',
            'expense_date'   => '2025-06-10',
        ]);

        $this->assertIsFloat($expense->amount);
        $this->assertSame(120.0, $expense->amount);
    }
}
