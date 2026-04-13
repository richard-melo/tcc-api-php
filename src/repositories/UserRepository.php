<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use PDO;

class UserRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? User::fromArray($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row ? User::fromArray($row) : null;
    }

    public function create(string $name, string $email, string $passwordHash): User
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $email, $passwordHash]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, array $fields): User
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');

        $setClauses = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($fields)));
        $values     = array_values($fields);
        $values[]   = $id;

        $this->db->prepare("UPDATE users SET {$setClauses} WHERE id = ?")->execute($values);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
