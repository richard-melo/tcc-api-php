<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    /** @var UserRepository&MockObject */
    private UserRepository $userRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['JWT_SECRET'] = 'test-secret-phpunit';
        $_ENV['JWT_TTL']    = '86400';
        $_ENV['APP_ENV']    = 'testing';

        $this->userRepo    = $this->createMock(UserRepository::class);
        $this->authService = new AuthService($this->userRepo);
    }

    // ── register ────────────────────────────────────────────────────────────

    public function testRegisterReturnsUserAndToken(): void
    {
        $fakeUser = User::fromArray([
            'id'         => 1,
            'name'       => 'Richard',
            'email'      => 'richard@teste.com',
            'password'   => password_hash('senha123', PASSWORD_BCRYPT),
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ]);

        $this->userRepo->method('findByEmail')->willReturn(null);
        $this->userRepo->method('create')->willReturn($fakeUser);

        $result = $this->authService->register('Richard', 'richard@teste.com', 'senha123');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertSame('richard@teste.com', $result['user']['email']);
        $this->assertNotEmpty($result['token']);
    }

    public function testRegisterThrowsWhenEmailAlreadyExists(): void
    {
        $existingUser = User::fromArray([
            'id'       => 1,
            'name'     => 'Outro',
            'email'    => 'richard@teste.com',
            'password' => 'hashed',
        ]);

        $this->userRepo->method('findByEmail')->willReturn($existingUser);

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(409);

        $this->authService->register('Richard', 'richard@teste.com', 'senha123');
    }

    public function testRegisterThrowsOnInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(422);

        $this->authService->register('Richard', 'email-invalido', 'senha123');
    }

    public function testRegisterThrowsWhenPasswordTooShort(): void
    {
        $this->userRepo->method('findByEmail')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(422);

        $this->authService->register('Richard', 'richard@teste.com', '123');
    }

    // ── login ────────────────────────────────────────────────────────────────

    public function testLoginReturnsTokenOnValidCredentials(): void
    {
        $fakeUser = User::fromArray([
            'id'       => 1,
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => password_hash('senha123', PASSWORD_BCRYPT),
        ]);

        $this->userRepo->method('findByEmail')->willReturn($fakeUser);

        $result = $this->authService->login('richard@teste.com', 'senha123');

        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
    }

    public function testLoginThrowsOnWrongPassword(): void
    {
        $fakeUser = User::fromArray([
            'id'       => 1,
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => password_hash('senha123', PASSWORD_BCRYPT),
        ]);

        $this->userRepo->method('findByEmail')->willReturn($fakeUser);

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);

        $this->authService->login('richard@teste.com', 'errada');
    }

    public function testLoginThrowsWhenUserNotFound(): void
    {
        $this->userRepo->method('findByEmail')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);

        $this->authService->login('inexistente@teste.com', 'senha123');
    }

    // ── validateToken ────────────────────────────────────────────────────────

    public function testValidateTokenReturnsClaimsForValidToken(): void
    {
        $fakeUser = User::fromArray([
            'id'       => 42,
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => 'hashed',
        ]);

        $this->userRepo->method('findByEmail')->willReturn(null);
        $this->userRepo->method('create')->willReturn($fakeUser);

        $result = $this->authService->register('Richard', 'richard@teste.com', 'senha123');
        $claims = $this->authService->validateToken($result['token']);

        $this->assertArrayHasKey('sub', $claims);
        $this->assertSame(42, (int) $claims['sub']);
    }

    public function testValidateTokenThrowsForInvalidToken(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);

        $this->authService->validateToken('token.invalido.aqui');
    }
}
