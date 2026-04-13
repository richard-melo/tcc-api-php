<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Middleware\AuthMiddleware;
use App\Models\Expense;
use App\Repositories\ExpenseRepository;

class ExpenseController
{
    public function __construct(
        private readonly AuthMiddleware    $auth,
        private readonly ExpenseRepository $expenseRepository,
    ) {}

    public function index(): void
    {
        $claims  = $this->auth->handle();
        $filters = [
            'category'   => $_GET['category']   ?? null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date'   => $_GET['end_date']   ?? null,
        ];

        $expenses = $this->expenseRepository->findAllByUser((int) $claims['sub'], $filters);
        $this->respond(array_map(fn($e) => $e->toArray(), $expenses));
    }

    public function show(int $id): void
    {
        $claims  = $this->auth->handle();
        $expense = $this->expenseRepository->findById($id, (int) $claims['sub']);

        if (!$expense) {
            $this->respond(['error' => 'Gasto não encontrado.'], 404);
            return;
        }

        $this->respond($expense->toArray());
    }

    public function store(): void
    {
        $claims = $this->auth->handle();
        $body   = Request::body();

        $error = $this->validatePayload($body);
        if ($error) {
            $this->respond(['error' => $error], 422);
            return;
        }

        $data = [
            'user_id'        => (int) $claims['sub'],
            'description'    => trim($body['description']),
            'amount'         => (float) $body['amount'],
            'category'       => $body['category'],
            'payment_method' => $body['payment_method'],
            'expense_date'   => $body['expense_date'],
            'notes'          => isset($body['notes']) ? trim($body['notes']) : null,
        ];

        $expense = $this->expenseRepository->create($data);
        $this->respond($expense->toArray(), 201);
    }

    public function update(int $id): void
    {
        $claims = $this->auth->handle();
        $body   = Request::body();
        $fields = [];

        if (isset($body['description']) && trim($body['description']) !== '') {
            $fields['description'] = trim($body['description']);
        }
        if (isset($body['amount']) && is_numeric($body['amount']) && $body['amount'] > 0) {
            $fields['amount'] = (float) $body['amount'];
        }
        if (isset($body['category']) && in_array($body['category'], Expense::CATEGORIES, true)) {
            $fields['category'] = $body['category'];
        }
        if (isset($body['payment_method']) && in_array($body['payment_method'], Expense::PAYMENT_METHODS, true)) {
            $fields['payment_method'] = $body['payment_method'];
        }
        if (isset($body['expense_date'])) {
            $fields['expense_date'] = $body['expense_date'];
        }
        if (array_key_exists('notes', $body)) {
            $fields['notes'] = $body['notes'] ? trim($body['notes']) : null;
        }

        if (empty($fields)) {
            $this->respond(['error' => 'Nenhum campo válido para atualizar.'], 422);
            return;
        }

        $expense = $this->expenseRepository->update($id, (int) $claims['sub'], $fields);

        if (!$expense) {
            $this->respond(['error' => 'Gasto não encontrado.'], 404);
            return;
        }

        $this->respond($expense->toArray());
    }

    public function destroy(int $id): void
    {
        $claims  = $this->auth->handle();
        $deleted = $this->expenseRepository->delete($id, (int) $claims['sub']);

        if (!$deleted) {
            $this->respond(['error' => 'Gasto não encontrado.'], 404);
            return;
        }

        $this->respond(['message' => 'Gasto excluído com sucesso.']);
    }

    private function validatePayload(array $body): ?string
    {
        if (empty($body['description'])) {
            return 'O campo description é obrigatório.';
        }
        if (!isset($body['amount']) || !is_numeric($body['amount']) || (float) $body['amount'] <= 0) {
            return 'O campo amount deve ser um número positivo.';
        }
        if (empty($body['category']) || !in_array($body['category'], Expense::CATEGORIES, true)) {
            return 'Categoria inválida. Opções: ' . implode(', ', Expense::CATEGORIES);
        }
        if (empty($body['payment_method']) || !in_array($body['payment_method'], Expense::PAYMENT_METHODS, true)) {
            return 'Método de pagamento inválido. Opções: ' . implode(', ', Expense::PAYMENT_METHODS);
        }
        if (empty($body['expense_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['expense_date'])) {
            return 'O campo expense_date deve estar no formato YYYY-MM-DD.';
        }

        return null;
    }

    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }
}
