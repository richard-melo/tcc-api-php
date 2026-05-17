<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Expense;

class ExpenseValidator
{
    public const MAX_AMOUNT             = 999999.99;
    public const MAX_DESCRIPTION_LENGTH = 255;

    public static function validate(array $body): ?string
    {
        if (!isset($body['description']) || trim((string) $body['description']) === '') {
            return 'O campo description é obrigatório.';
        }
        if (strlen(trim((string) $body['description'])) > self::MAX_DESCRIPTION_LENGTH) {
            return 'O campo description deve ter no máximo ' . self::MAX_DESCRIPTION_LENGTH . ' caracteres.';
        }
        if (!isset($body['amount']) || !is_numeric($body['amount'])) {
            return 'O campo amount deve ser um número.';
        }
        $amount = (float) $body['amount'];
        if ($amount <= 0) {
            return 'O campo amount deve ser um número positivo.';
        }
        if ($amount > self::MAX_AMOUNT) {
            return 'O campo amount não pode exceder ' . number_format(self::MAX_AMOUNT, 2, '.', '') . '.';
        }
        if (empty($body['category']) || !in_array($body['category'], Expense::CATEGORIES, true)) {
            return 'Categoria inválida. Opções: ' . implode(', ', Expense::CATEGORIES);
        }
        if (empty($body['payment_method']) || !in_array($body['payment_method'], Expense::PAYMENT_METHODS, true)) {
            return 'Método de pagamento inválido. Opções: ' . implode(', ', Expense::PAYMENT_METHODS);
        }
        if (empty($body['expense_date'])) {
            return 'O campo expense_date é obrigatório.';
        }
        if (!self::validateDate($body['expense_date'])) {
            return 'O campo expense_date deve ser uma data válida no formato YYYY-MM-DD.';
        }

        return null;
    }

    public static function validateDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$year, $month, $day] = explode('-', $date);
        return checkdate((int) $month, (int) $day, (int) $year);
    }

    public static function validateBudget(array $body): ?string
    {
        if (empty($body['category']) || !in_array($body['category'], Expense::CATEGORIES, true)) {
            return 'Categoria inválida. Opções: ' . implode(', ', Expense::CATEGORIES);
        }
        if (!isset($body['amount']) || !is_numeric($body['amount'])) {
            return 'O campo amount deve ser um número.';
        }
        $amount = (float) $body['amount'];
        if ($amount <= 0) {
            return 'O campo amount deve ser um número positivo.';
        }
        if ($amount > self::MAX_AMOUNT) {
            return 'O campo amount não pode exceder ' . number_format(self::MAX_AMOUNT, 2, '.', '') . '.';
        }
        if (!isset($body['month']) || !is_numeric($body['month'])) {
            return 'O campo month deve ser um número.';
        }
        $month = (int) $body['month'];
        if ($month < 1 || $month > 12) {
            return 'O campo month deve ser um valor entre 1 e 12.';
        }
        if (!isset($body['year']) || !is_numeric($body['year'])) {
            return 'O campo year deve ser um número.';
        }
        $year = (int) $body['year'];
        if ($year < 2000 || $year > 2100) {
            return 'O campo year deve ser um valor entre 2000 e 2100.';
        }

        return null;
    }

    public static function sanitize(array $body): array
    {
        if (isset($body['description'])) {
            $body['description'] = trim((string) $body['description']);
        }
        if (array_key_exists('notes', $body)) {
            $body['notes'] = ($body['notes'] !== null && trim((string) $body['notes']) !== '')
                ? trim((string) $body['notes'])
                : null;
        }
        if (isset($body['amount'])) {
            $body['amount'] = (float) $body['amount'];
        }
        if (isset($body['category'])) {
            $body['category'] = strtolower(trim((string) $body['category']));
        }
        if (isset($body['payment_method'])) {
            $body['payment_method'] = strtolower(trim((string) $body['payment_method']));
        }

        return $body;
    }
}
