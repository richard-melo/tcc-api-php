<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Config\Database;
use App\Controllers\AuthController;
use App\Controllers\ExpenseController;
use App\Controllers\ReportController;
use App\Controllers\UserController;
use App\Http\Request;
use App\Middleware\AuthMiddleware;
use App\Repositories\ExpenseRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

/**
 * Base para testes funcionais: simula ciclo completo request → controller → response
 * sem precisar de servidor HTTP nem Docker.
 *
 * Técnica:
 *  - Injeta corpo da requisição via Request::$stub
 *  - Simula $_SERVER e $_GET
 *  - Captura JSON via output buffering
 */
abstract class FunctionalTestCase extends DatabaseTestCase
{
    protected UserRepository    $userRepo;
    protected ExpenseRepository $expenseRepo;
    protected AuthService       $authService;
    protected AuthMiddleware    $authMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $db                   = Database::getConnection();
        $this->userRepo       = new UserRepository($db);
        $this->expenseRepo    = new ExpenseRepository($db);
        $this->authService    = new AuthService($this->userRepo);
        $this->authMiddleware = new AuthMiddleware($this->authService);
    }

    protected function tearDown(): void
    {
        Request::reset();
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_GET = [];
        parent::tearDown();
    }

    // ── Helpers para autenticação ──────────────────────────────────────────

    protected function registerAndLogin(
        string $name     = 'Richard',
        string $email    = 'richard@teste.com',
        string $password = 'senha123',
    ): string {
        $result = $this->authService->register($name, $email, $password);
        return $result['token'];
    }

    protected function withAuth(string $token): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$token}";
    }

    // ── Dispatcher de controllers ──────────────────────────────────────────

    protected function callAuth(string $action, array $body = []): array
    {
        Request::$stub = $body;
        $ctrl = new AuthController($this->authService);
        return $this->capture(fn() => $ctrl->$action());
    }

    protected function callUser(string $action, array $body = []): array
    {
        Request::$stub = $body;
        $ctrl = new UserController($this->authMiddleware, $this->userRepo);
        return $this->capture(fn() => $ctrl->$action());
    }

    protected function callExpense(string $action, array $body = [], ?int $id = null, array $query = []): array
    {
        Request::$stub = $body;
        $_GET          = $query;
        $ctrl          = new ExpenseController($this->authMiddleware, $this->expenseRepo);
        return $this->capture(fn() => $id !== null ? $ctrl->$action($id) : $ctrl->$action());
    }

    protected function callReport(string $action, array $query = []): array
    {
        $_GET = $query;
        $ctrl = new ReportController($this->authMiddleware, $this->expenseRepo);
        return $this->capture(fn() => $ctrl->$action());
    }

    // ── Captura de output ──────────────────────────────────────────────────

    private function capture(callable $fn): array
    {
        ob_start();
        $fn();
        $raw = ob_get_clean();
        return json_decode($raw, true) ?? [];
    }
}
