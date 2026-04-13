<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\Support\FunctionalTestCase;

/**
 * Testes funcionais do fluxo de autenticação.
 * Exercita a cadeia completa: Controller → Service → Repository → SQLite.
 */
class AuthTest extends FunctionalTestCase
{
    // ── POST /api/auth/register ──────────────────────────────────────────────

    public function testRegisterReturnsUserAndToken(): void
    {
        $response = $this->callAuth('register', [
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => 'senha123',
        ]);

        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('token', $response);
        $this->assertSame('richard@teste.com', $response['user']['email']);
        $this->assertArrayNotHasKey('password', $response['user']);
    }

    public function testRegisterFailsWithMissingFields(): void
    {
        $response = $this->callAuth('register', ['name' => 'Richard']);

        $this->assertArrayHasKey('error', $response);
    }

    public function testRegisterFailsWithInvalidEmail(): void
    {
        $response = $this->callAuth('register', [
            'name'     => 'Richard',
            'email'    => 'nao-e-um-email',
            'password' => 'senha123',
        ]);

        $this->assertArrayHasKey('error', $response);
    }

    public function testRegisterFailsWithShortPassword(): void
    {
        $response = $this->callAuth('register', [
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => '123',
        ]);

        $this->assertArrayHasKey('error', $response);
    }

    public function testRegisterFailsWithDuplicateEmail(): void
    {
        $this->callAuth('register', [
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => 'senha123',
        ]);

        $response = $this->callAuth('register', [
            'name'     => 'Outro',
            'email'    => 'richard@teste.com',
            'password' => 'senha456',
        ]);

        $this->assertArrayHasKey('error', $response);
    }

    // ── POST /api/auth/login ─────────────────────────────────────────────────

    public function testLoginReturnsTokenOnValidCredentials(): void
    {
        $this->callAuth('register', [
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => 'senha123',
        ]);

        $response = $this->callAuth('login', [
            'email'    => 'richard@teste.com',
            'password' => 'senha123',
        ]);

        $this->assertArrayHasKey('token', $response);
        $this->assertNotEmpty($response['token']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->callAuth('register', [
            'name'     => 'Richard',
            'email'    => 'richard@teste.com',
            'password' => 'senha123',
        ]);

        $response = $this->callAuth('login', [
            'email'    => 'richard@teste.com',
            'password' => 'errada',
        ]);

        $this->assertArrayHasKey('error', $response);
    }

    public function testLoginFailsWithNonExistentEmail(): void
    {
        $response = $this->callAuth('login', [
            'email'    => 'fantasma@teste.com',
            'password' => 'qualquer',
        ]);

        $this->assertArrayHasKey('error', $response);
    }

    public function testLoginFailsWithMissingFields(): void
    {
        $response = $this->callAuth('login', ['email' => 'richard@teste.com']);

        $this->assertArrayHasKey('error', $response);
    }
}
