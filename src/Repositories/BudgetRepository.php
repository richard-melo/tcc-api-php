<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Budget;
use PDO;

class BudgetRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findById(int $id, int $userId): ?Budget
    {
        $stmt = $this->db->prepare('SELECT * FROM budgets WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row ? Budget::fromArray($row) : null;
    }

    public function findByCategory(int $userId, string $category, int $month, int $year): ?Budget
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM budgets WHERE user_id = ? AND category = ? AND month = ? AND year = ?'
        );
        $stmt->execute([$userId, $category, $month, $year]);
        $row = $stmt->fetch();

        return $row ? Budget::fromArray($row) : null;
    }

    public function findAllByUser(int $userId, int $month, int $year): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM budgets WHERE user_id = ? AND month = ? AND year = ? ORDER BY category'
        );
        $stmt->execute([$userId, $month, $year]);

        return array_map(fn($row) => Budget::fromArray($row), $stmt->fetchAll());
    }

    public function create(array $data): Budget
    {
        $stmt = $this->db->prepare('
            INSERT INTO budgets (user_id, category, amount, month, year)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['user_id'],
            $data['category'],
            $data['amount'],
            $data['month'],
            $data['year'],
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id, $data['user_id']);
    }

    public function update(int $id, int $userId, float $amount): ?Budget
    {
        $stmt = $this->db->prepare(
            "UPDATE budgets SET amount = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$amount, $id, $userId]);

        return $this->findById($id, $userId);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function getTotalSpentByCategory(int $userId, string $category, int $month, int $year): float
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));

        $stmt = $this->db->prepare('
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM expenses
            WHERE user_id = ?
              AND category = ?
              AND expense_date BETWEEN ? AND ?
        ');
        $stmt->execute([$userId, $category, $startDate, $endDate]);
        $row = $stmt->fetch();

        return (float) ($row['total'] ?? 0);
    }
}
