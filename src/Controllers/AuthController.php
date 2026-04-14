<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Services\AuthService;

class AuthController
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(): void
    {
        $body = $this->parseBody();

        $name     = trim($body['name'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$name || !$email || !$password) {
            $this->respond(['error' => 'name, email e password são obrigatórios.'], 422);
            return;
        }

        try {
            $result = $this->authService->register($name, $email, $password);
            $this->respond($result, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respond(['error' => $e->getMessage()], (int) $e->getCode() ?: 422);
        } catch (\DomainException $e) {
            $this->respond(['error' => $e->getMessage()], (int) $e->getCode() ?: 400);
        }
    }

    public function login(): void
    {
        $body = $this->parseBody();

        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            $this->respond(['error' => 'email e password são obrigatórios.'], 422);
            return;
        }

        try {
            $result = $this->authService->login($email, $password);
            $this->respond($result);
        } catch (\DomainException $e) {
            $this->respond(['error' => $e->getMessage()], (int) $e->getCode() ?: 401);
        }
    }

    private function parseBody(): array
    {
        return Request::body();
    }

    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }
}
