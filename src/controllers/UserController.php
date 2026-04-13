<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;

class UserController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly UserRepository $userRepository,
    ) {}

    public function me(): void
    {
        $claims = $this->auth->handle();
        $user   = $this->userRepository->findById((int) $claims['sub']);

        if (!$user) {
            $this->respond(['error' => 'Usuário não encontrado.'], 404);
            return;
        }

        $this->respond($user->toArray());
    }

    public function update(): void
    {
        $claims = $this->auth->handle();
        $body   = Request::body();
        $fields = [];

        if (isset($body['name']) && trim($body['name']) !== '') {
            $fields['name'] = trim($body['name']);
        }

        if (isset($body['password']) && strlen($body['password']) >= 6) {
            $fields['password'] = password_hash($body['password'], PASSWORD_BCRYPT);
        } elseif (isset($body['password'])) {
            $this->respond(['error' => 'A senha deve ter pelo menos 6 caracteres.'], 422);
            return;
        }

        if (empty($fields)) {
            $this->respond(['error' => 'Nenhum campo válido para atualizar.'], 422);
            return;
        }

        $user = $this->userRepository->update((int) $claims['sub'], $fields);
        $this->respond($user->toArray());
    }

    public function destroy(): void
    {
        $claims = $this->auth->handle();
        $this->userRepository->delete((int) $claims['sub']);
        $this->respond(['message' => 'Conta excluída com sucesso.']);
    }

    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }
}
