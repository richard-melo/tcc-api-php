<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;

class AuthMiddleware
{
    public function __construct(private readonly AuthService $authService) {}

    public function handle(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            http_response_code(401);
            echo json_encode(['error' => 'Token não fornecido.']);
            exit;
        }

        $token = substr($header, 7);

        try {
            return $this->authService->validateToken($token);
        } catch (\DomainException $e) {
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}
