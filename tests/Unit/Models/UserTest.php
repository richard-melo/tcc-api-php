<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testFromArrayCreatesUserCorrectly(): void
    {
        $user = User::fromArray([
            'id'         => 1,
            'name'       => 'Richard',
            'email'      => 'richard@teste.com',
            'password'   => 'hashed',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ]);

        $this->assertSame(1, $user->id);
        $this->assertSame('Richard', $user->name);
        $this->assertSame('richard@teste.com', $user->email);
        $this->assertSame('hashed', $user->password);
    }

    public function testFromArrayWithNullId(): void
    {
        $user = User::fromArray([
            'name'     => 'Novo',
            'email'    => 'novo@teste.com',
            'password' => 'hashed',
        ]);

        $this->assertNull($user->id);
    }

    public function testToArrayExcludesPasswordByDefault(): void
    {
        $user = User::fromArray([
            'id'       => 1,
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => 'supersecret',
        ]);

        $arr = $user->toArray();

        $this->assertArrayNotHasKey('password', $arr);
        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('email', $arr);
    }

    public function testToArrayWithPasswordIncludesIt(): void
    {
        $user = User::fromArray([
            'id'       => 1,
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => 'supersecret',
        ]);

        $arr = $user->toArray(withPassword: true);

        $this->assertArrayHasKey('password', $arr);
        $this->assertSame('supersecret', $arr['password']);
    }

    public function testFromArrayMapsTimestamps(): void
    {
        $user = User::fromArray([
            'name'       => 'Ana',
            'email'      => 'ana@teste.com',
            'password'   => 'hashed',
            'created_at' => '2025-03-01 10:00:00',
            'updated_at' => '2025-03-15 12:00:00',
        ]);

        $this->assertSame('2025-03-01 10:00:00', $user->createdAt);
        $this->assertSame('2025-03-15 12:00:00', $user->updatedAt);
    }

    public function testFromArrayDefaultsTimestampsToEmptyString(): void
    {
        $user = User::fromArray([
            'name'     => 'Carlos',
            'email'    => 'carlos@teste.com',
            'password' => 'hashed',
        ]);

        $this->assertSame('', $user->createdAt);
        $this->assertSame('', $user->updatedAt);
    }

    public function testToArrayContainsExactKeys(): void
    {
        $user = User::fromArray([
            'id'       => 2,
            'name'     => 'Teste',
            'email'    => 'teste@teste.com',
            'password' => 'hashed',
        ]);

        $keys = array_keys($user->toArray());
        sort($keys);

        $this->assertSame(['created_at', 'email', 'id', 'name', 'updated_at'], $keys);
    }
}
