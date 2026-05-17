<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Budget;
use App\Repositories\BudgetRepository;

class BudgetService
{
    public const ALERT_WARNING  = 'warning';
    public const ALERT_EXCEEDED = 'exceeded';
    public const ALERT_NONE     = 'none';

    public const THRESHOLD_WARNING  = 0.80;
    public const THRESHOLD_EXCEEDED = 1.00;

    public function __construct(private readonly BudgetRepository $budgetRepository)
    {
    }

    public function createOrUpdate(
        int $userId,
        string $category,
        float $amount,
        int $month,
        int $year,
    ): Budget {
        $existing = $this->budgetRepository->findByCategory($userId, $category, $month, $year);

        if ($existing !== null) {
            return $this->budgetRepository->update($existing->id, $userId, $amount);
        }

        return $this->budgetRepository->create([
            'user_id'  => $userId,
            'category' => $category,
            'amount'   => $amount,
            'month'    => $month,
            'year'     => $year,
        ]);
    }

    public function update(int $id, int $userId, float $amount): ?Budget
    {
        return $this->budgetRepository->update($id, $userId, $amount);
    }

    public function getByUser(int $userId, int $month, int $year): array
    {
        $budgets = $this->budgetRepository->findAllByUser($userId, $month, $year);

        return array_map(
            fn(Budget $b) => $this->withStatus($b, $userId),
            $budgets,
        );
    }

    public function checkStatus(int $userId, string $category, int $month, int $year): array
    {
        $budget = $this->budgetRepository->findByCategory($userId, $category, $month, $year);

        if ($budget === null) {
            return ['budget' => null, 'alert' => self::ALERT_NONE];
        }

        return $this->withStatus($budget, $userId);
    }

    private function withStatus(Budget $budget, int $userId): array
    {
        $spent      = $this->budgetRepository->getTotalSpentByCategory(
            $userId,
            $budget->category,
            $budget->month,
            $budget->year,
        );
        $ratio      = $budget->amount > 0 ? $spent / $budget->amount : 0;
        $percentage = round($ratio * 100, 2);

        $alert = match (true) {
            $ratio >= self::THRESHOLD_EXCEEDED => self::ALERT_EXCEEDED,
            $ratio >= self::THRESHOLD_WARNING  => self::ALERT_WARNING,
            default                            => self::ALERT_NONE,
        };

        return array_merge($budget->toArray(), [
            'spent'      => round($spent, 2),
            'remaining'  => round(max(0, $budget->amount - $spent), 2),
            'percentage' => $percentage,
            'alert'      => $alert,
        ]);
    }
}
