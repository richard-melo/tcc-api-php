<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Config\Database;
use App\Repositories\UserRepository;
use Tests\Support\DatabaseTestCase;

class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository(Database::getConnection());
    }

    public function testCreateAndFindById(): void
    {
        $user = $this->repo->create('Richard', 'richard@teste.com', password_hash('senha123', PASSWORD_BCRYPT));

        $this->assertNotNull($user->id);
        $this->assertSame('Richard', $user->name);
        $this->assertSame('richard@teste.com', $user->email);

        $found = $this->repo->findById($user->id);
        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function testFindByEmail(): void
    {
        $this->repo->create('Richard', 'richard@teste.com', 'hashed');

        $found = $this->repo->findByEmail('richard@teste.com');

        $this->assertNotNull($found);
        $this->assertSame('richard@teste.com', $found->email);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $result = $this->repo->findByEmail('inexistente@teste.com');
        $this->assertNull($result);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repo->findById(9999);
        $this->assertNull($result);
    }

    public function testUpdate(): void
    {
        $user = $this->repo->create('Richard', 'richard@teste.com', 'hashed');

        $updated = $this->repo->update($user->id, ['name' => 'Richard Atualizado']);

        $this->assertSame('Richard Atualizado', $updated->name);
        $this->assertSame('richard@teste.com', $updated->email);
    }

    public function testDelete(): void
    {
        $user = $this->repo->create('Richard', 'richard@teste.com', 'hashed');

        $this->repo->delete($user->id);

        $found = $this->repo->findById($user->id);
        $this->assertNull($found);
    }

    public function testEmailMustBeUnique(): void
    {
        $this->repo->create('Richard', 'richard@teste.com', 'hashed');

        $this->expectException(\PDOException::class);

        $this->repo->create('Outro', 'richard@teste.com', 'hashed');
    }
}
