<?php

declare(strict_types=1);

namespace App\Models;

class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $createdAt = '',
        public readonly string $updatedAt = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:        isset($data['id']) ? (int) $data['id'] : null,
            name:      $data['name'],
            email:     $data['email'],
            password:  $data['password'],
            createdAt: $data['created_at'] ?? '',
            updatedAt: $data['updated_at'] ?? '',
        );
    }

    public function toArray(bool $withPassword = false): array
    {
        $data = [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        if ($withPassword) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
