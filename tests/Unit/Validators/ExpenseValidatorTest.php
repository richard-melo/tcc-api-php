<?php

declare(strict_types=1);

namespace Tests\Unit\Validators;

use App\Models\Expense;
use App\Validators\ExpenseValidator;
use PHPUnit\Framework\TestCase;

class ExpenseValidatorTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'description'    => 'Almoço',
            'amount'         => 50.00,
            'category'       => 'alimentacao',
            'payment_method' => 'pix',
            'expense_date'   => '2025-06-15',
        ], $overrides);
    }

    // ── Validação geral ────────────────────────────────────────────────────────

    public function testValidPayloadReturnsNull(): void
    {
        $this->assertNull(ExpenseValidator::validate($this->validPayload()));
    }

    // ── Description ───────────────────────────────────────────────────────────

    public function testMissingDescriptionReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['description' => '']));
        $this->assertStringContainsString('description', $result);
    }

    public function testWhitespaceDescriptionReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['description' => '   ']));
        $this->assertStringContainsString('description', $result);
    }

    public function testDescriptionTooLongReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload([
            'description' => str_repeat('a', ExpenseValidator::MAX_DESCRIPTION_LENGTH + 1),
        ]));
        $this->assertStringContainsString('description', $result);
    }

    public function testDescriptionAtMaxLengthIsValid(): void
    {
        $result = ExpenseValidator::validate($this->validPayload([
            'description' => str_repeat('a', ExpenseValidator::MAX_DESCRIPTION_LENGTH),
        ]));
        $this->assertNull($result);
    }

    // ── Amount ────────────────────────────────────────────────────────────────

    public function testMissingAmountReturnsError(): void
    {
        $payload = $this->validPayload();
        unset($payload['amount']);
        $this->assertNotNull(ExpenseValidator::validate($payload));
    }

    public function testNonNumericAmountReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['amount' => 'abc']));
        $this->assertStringContainsString('amount', $result);
    }

    public function testZeroAmountReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['amount' => 0]));
        $this->assertStringContainsString('positivo', $result);
    }

    public function testNegativeAmountReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['amount' => -10]));
        $this->assertStringContainsString('positivo', $result);
    }

    public function testAmountExceedingMaxReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['amount' => 1_000_000]));
        $this->assertStringContainsString('exceder', $result);
    }

    public function testAmountAtMaxIsValid(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['amount' => ExpenseValidator::MAX_AMOUNT]));
        $this->assertNull($result);
    }

    // ── Category ──────────────────────────────────────────────────────────────

    public function testInvalidCategoryReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['category' => 'invalida']));
        $this->assertStringContainsString('Categoria', $result);
    }

    public function testAllValidCategoriesPass(): void
    {
        foreach (Expense::CATEGORIES as $cat) {
            $this->assertNull(
                ExpenseValidator::validate($this->validPayload(['category' => $cat])),
                "Category '{$cat}' should be valid"
            );
        }
    }

    // ── Payment method ────────────────────────────────────────────────────────

    public function testInvalidPaymentMethodReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['payment_method' => 'cheque']));
        $this->assertStringContainsString('pagamento', $result);
    }

    public function testAllValidPaymentMethodsPass(): void
    {
        foreach (Expense::PAYMENT_METHODS as $method) {
            $this->assertNull(
                ExpenseValidator::validate($this->validPayload(['payment_method' => $method])),
                "Payment method '{$method}' should be valid"
            );
        }
    }

    // ── Date ──────────────────────────────────────────────────────────────────

    public function testMissingDateReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['expense_date' => '']));
        $this->assertStringContainsString('expense_date', $result);
    }

    public function testInvalidDateFormatReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['expense_date' => '15/06/2025']));
        $this->assertStringContainsString('expense_date', $result);
    }

    public function testInvalidDateValueReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['expense_date' => '2025-13-45']));
        $this->assertStringContainsString('expense_date', $result);
    }

    public function testInvalidDayReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['expense_date' => '2025-02-30']));
        $this->assertStringContainsString('expense_date', $result);
    }

    public function testLeapYearDateIsValid(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['expense_date' => '2024-02-29']));
        $this->assertNull($result);
    }

    public function testNonLeapYearFeb29ReturnsError(): void
    {
        $result = ExpenseValidator::validate($this->validPayload(['expense_date' => '2025-02-29']));
        $this->assertStringContainsString('expense_date', $result);
    }

    // ── validateDate ──────────────────────────────────────────────────────────

    public function testValidateDateAcceptsValidDate(): void
    {
        $this->assertTrue(ExpenseValidator::validateDate('2025-06-15'));
    }

    public function testValidateDateRejectsWrongFormat(): void
    {
        $this->assertFalse(ExpenseValidator::validateDate('06/15/2025'));
        $this->assertFalse(ExpenseValidator::validateDate('2025/06/15'));
        $this->assertFalse(ExpenseValidator::validateDate('not-a-date'));
    }

    public function testValidateDateRejectsImpossibleDate(): void
    {
        $this->assertFalse(ExpenseValidator::validateDate('2025-00-01'));
        $this->assertFalse(ExpenseValidator::validateDate('2025-13-01'));
        $this->assertFalse(ExpenseValidator::validateDate('2025-01-32'));
    }

    // ── Sanitize ──────────────────────────────────────────────────────────────

    public function testSanitizeTrimsDescription(): void
    {
        $result = ExpenseValidator::sanitize(['description' => '  Almoço  ']);
        $this->assertSame('Almoço', $result['description']);
    }

    public function testSanitizeCastsAmount(): void
    {
        $result = ExpenseValidator::sanitize(['amount' => '42.50']);
        $this->assertSame(42.50, $result['amount']);
        $this->assertIsFloat($result['amount']);
    }

    public function testSanitizeLowerCasesCategory(): void
    {
        $result = ExpenseValidator::sanitize(['category' => 'ALIMENTACAO']);
        $this->assertSame('alimentacao', $result['category']);
    }

    public function testSanitizeLowerCasesPaymentMethod(): void
    {
        $result = ExpenseValidator::sanitize(['payment_method' => 'PIX']);
        $this->assertSame('pix', $result['payment_method']);
    }

    public function testSanitizeNullNotes(): void
    {
        $result = ExpenseValidator::sanitize(['notes' => null]);
        $this->assertNull($result['notes']);
    }

    public function testSanitizeEmptyNotesToNull(): void
    {
        $result = ExpenseValidator::sanitize(['notes' => '   ']);
        $this->assertNull($result['notes']);
    }

    public function testSanitizeTrimsNotes(): void
    {
        $result = ExpenseValidator::sanitize(['notes' => '  nota aqui  ']);
        $this->assertSame('nota aqui', $result['notes']);
    }

    // ── validateBudget ────────────────────────────────────────────────────────

    public function testValidBudgetPayloadReturnsNull(): void
    {
        $this->assertNull(ExpenseValidator::validateBudget([
            'category' => 'alimentacao',
            'amount'   => 500.0,
            'month'    => 6,
            'year'     => 2025,
        ]));
    }

    public function testBudgetInvalidCategoryReturnsError(): void
    {
        $result = ExpenseValidator::validateBudget([
            'category' => 'invalida',
            'amount'   => 500.0,
            'month'    => 6,
            'year'     => 2025,
        ]);
        $this->assertStringContainsString('Categoria', $result);
    }

    public function testBudgetInvalidMonthReturnsError(): void
    {
        $result = ExpenseValidator::validateBudget([
            'category' => 'alimentacao',
            'amount'   => 500.0,
            'month'    => 13,
            'year'     => 2025,
        ]);
        $this->assertStringContainsString('month', $result);
    }

    public function testBudgetInvalidYearReturnsError(): void
    {
        $result = ExpenseValidator::validateBudget([
            'category' => 'alimentacao',
            'amount'   => 500.0,
            'month'    => 6,
            'year'     => 1999,
        ]);
        $this->assertStringContainsString('year', $result);
    }
}
