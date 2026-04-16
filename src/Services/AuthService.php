<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private string $jwtSecret;
    private int $jwtTtl;

    public function __construct(private readonly UserRepository $userRepository)
    {
        $config          = require __DIR__ . '/../../config/app.php';
        $this->jwtSecret = $config['jwt_secret'];
        $this->jwtTtl    = $config['jwt_ttl'];
    }

    public function register(string $name, string $email, string $password): array
    {
        $this->validateEmail($email);
        $this->validatePassword($password);

        if ($this->userRepository->findByEmail($email)) {
            throw new \DomainException('E-mail já está em uso.', 409);
        }

        $user  = $this->userRepository->create($name, $email, password_hash($password, PASSWORD_BCRYPT));
        $token = $this->generateToken($user);

        return ['user' => $user->toArray(), 'token' => $token];
    }

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user->password)) {
            throw new \DomainException('Credenciais inválidas.', 401);
        }

        $token = $this->generateToken($user);

        return ['user' => $user->toArray(), 'token' => $token];
    }

    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new \DomainException('Token inválido ou expirado.', 401);
        }
    }

    private function generateToken(User $user): string
    {
        $now     = time();
        $payload = [
            'iss' => 'expense-api',
            'iat' => $now,
            'exp' => $now + $this->jwtTtl,
            'sub' => $user->id,
            'email' => $user->email,
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail inválido.', 422);
        }
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 6) {
            throw new \InvalidArgumentException('A senha deve ter pelo menos 6 caracteres.', 422);
        }
    }
}
