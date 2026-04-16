<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpException;
use App\Services\AuthService;

class AuthMiddleware
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function handle(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            throw new HttpException(401, 'Token não fornecido.');
        }

        $token = substr($header, 7);

        try {
            return $this->authService->validateToken($token);
        } catch (\DomainException $e) {
            throw new HttpException(401, $e->getMessage());
        }
    }
}
