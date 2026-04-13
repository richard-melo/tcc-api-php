<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Expense;
use PDO;

class ExpenseRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findById(int $id, int $userId): ?Expense
    {
        $stmt = $this->db->prepare('SELECT * FROM expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row ? Expense::fromArray($row) : null;
    }

    public function findAllByUser(int $userId, array $filters = []): array
    {
        $sql    = 'SELECT * FROM expenses WHERE user_id = ?';
        $params = [$userId];

        if (!empty($filters['category'])) {
            $sql      .= ' AND category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['start_date'])) {
            $sql      .= ' AND expense_date >= ?';
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql      .= ' AND expense_date <= ?';
            $params[] = $filters['end_date'];
        }

        $sql .= ' ORDER BY expense_date DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($row) => Expense::fromArray($row), $stmt->fetchAll());
    }

    public function create(array $data): Expense
    {
        $stmt = $this->db->prepare('
            INSERT INTO expenses (user_id, description, amount, category, payment_method, expense_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['user_id'],
            $data['description'],
            $data['amount'],
            $data['category'],
            $data['payment_method'],
            $data['expense_date'],
            $data['notes'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id, $data['user_id']);
    }

    public function update(int $id, int $userId, array $fields): ?Expense
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');

        $setClauses = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($fields)));
        $values     = array_values($fields);
        $values[]   = $id;
        $values[]   = $userId;

        $affected = $this->db->prepare(
            "UPDATE expenses SET {$setClauses} WHERE id = ? AND user_id = ?"
        )->execute($values);

        return $this->findById($id, $userId);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function getSummaryByCategory(int $userId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare('
            SELECT
                category,
                COUNT(*)    AS total_count,
                SUM(amount) AS total_amount,
                AVG(amount) AS avg_amount,
                MIN(amount) AS min_amount,
                MAX(amount) AS max_amount
            FROM expenses
            WHERE user_id = ?
              AND expense_date BETWEEN ? AND ?
            GROUP BY category
            ORDER BY total_amount DESC
        ');
        $stmt->execute([$userId, $startDate, $endDate]);

        return $stmt->fetchAll();
    }
}
