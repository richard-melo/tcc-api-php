<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Repositories\ExpenseRepository;

class ExportService
{
    public function __construct(private readonly ExpenseRepository $expenseRepository)
    {
    }

    public function getExpenses(int $userId, array $filters = []): array
    {
        return $this->expenseRepository->findAllByUser($userId, $filters);
    }

    public function toCsv(array $expenses): string
    {
        $lines = ["id,description,amount,category,payment_method,expense_date,notes,created_at"];

        foreach ($expenses as $expense) {
            $lines[] = implode(',', [
                $expense->id,
                self::csvEscape($expense->description),
                number_format($expense->amount, 2, '.', ''),
                $expense->category,
                $expense->paymentMethod,
                $expense->expenseDate,
                self::csvEscape((string) ($expense->notes ?? '')),
                $expense->createdAt,
            ]);
        }

        return implode("\n", $lines);
    }

    public function getSummary(int $userId, string $startDate, string $endDate): array
    {
        $expenses = $this->expenseRepository->findAllByUser($userId, [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);

        if (empty($expenses)) {
            return [
                'period'      => ['start' => $startDate, 'end' => $endDate],
                'total'       => 0.0,
                'count'       => 0,
                'average'     => 0.0,
                'highest'     => null,
                'lowest'      => null,
                'by_category' => [],
            ];
        }

        $amounts  = array_map(fn(Expense $e) => $e->amount, $expenses);
        $total    = array_sum($amounts);
        $count    = count($expenses);
        $average  = $total / $count;

        $maxAmount = max($amounts);
        $minAmount = min($amounts);
        $highest   = array_values(array_filter($expenses, fn(Expense $e) => $e->amount === $maxAmount))[0];
        $lowest    = array_values(array_filter($expenses, fn(Expense $e) => $e->amount === $minAmount))[0];

        $byCategory = $this->expenseRepository->getSummaryByCategory($userId, $startDate, $endDate);

        return [
            'period'      => ['start' => $startDate, 'end' => $endDate],
            'total'       => round($total, 2),
            'count'       => $count,
            'average'     => round($average, 2),
            'highest'     => [
                'amount'      => $highest->amount,
                'description' => $highest->description,
                'category'    => $highest->category,
                'date'        => $highest->expenseDate,
            ],
            'lowest'      => [
                'amount'      => $lowest->amount,
                'description' => $lowest->description,
                'category'    => $lowest->category,
                'date'        => $lowest->expenseDate,
            ],
            'by_category' => $byCategory,
        ];
    }

    private static function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
