<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Middleware\AuthMiddleware;
use App\Services\BudgetService;
use App\Validators\ExpenseValidator;

class BudgetController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly BudgetService  $budgetService,
    ) {
    }

    public function index(): void
    {
        $claims = $this->auth->handle();
        $month  = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
        $year   = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');

        if ($month < 1 || $month > 12) {
            $this->respond(['error' => 'O campo month deve ser um valor entre 1 e 12.'], 422);
            return;
        }
        if ($year < 2000 || $year > 2100) {
            $this->respond(['error' => 'O campo year deve ser um valor entre 2000 e 2100.'], 422);
            return;
        }

        $budgets = $this->budgetService->getByUser((int) $claims['sub'], $month, $year);
        $this->respond($budgets);
    }

    public function store(): void
    {
        $claims = $this->auth->handle();
        $body   = Request::body();

        $error = ExpenseValidator::validateBudget($body);
        if ($error) {
            $this->respond(['error' => $error], 422);
            return;
        }

        $budget = $this->budgetService->createOrUpdate(
            userId:   (int)   $claims['sub'],
            category: $body['category'],
            amount:   (float) $body['amount'],
            month:    (int)   $body['month'],
            year:     (int)   $body['year'],
        );

        $this->respond($budget->toArray(), 201);
    }

    public function update(int $id): void
    {
        $claims = $this->auth->handle();
        $body   = Request::body();

        if (!isset($body['amount']) || !is_numeric($body['amount']) || (float) $body['amount'] <= 0) {
            $this->respond(['error' => 'O campo amount deve ser um número positivo.'], 422);
            return;
        }
        if ((float) $body['amount'] > ExpenseValidator::MAX_AMOUNT) {
            $this->respond(['error' => 'O campo amount não pode exceder ' . number_format(ExpenseValidator::MAX_AMOUNT, 2, '.', '') . '.'], 422);
            return;
        }

        $budget = $this->budgetService->update($id, (int) $claims['sub'], (float) $body['amount']);

        if (!$budget) {
            $this->respond(['error' => 'Orçamento não encontrado.'], 404);
            return;
        }

        $this->respond($budget->toArray());
    }

    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }
}
