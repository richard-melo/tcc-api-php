<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BudgetController;
use App\Controllers\ExportController;
use App\Controllers\ExpenseController;
use App\Controllers\ReportController;
use App\Controllers\UserController;

return [
    'POST'   => [
        '/api/auth/register' => [AuthController::class,  'register'],
        '/api/auth/login'    => [AuthController::class,  'login'],
        '/api/expenses'      => [ExpenseController::class, 'store'],
        '/api/budgets'       => [BudgetController::class, 'store'],
    ],
    'GET'    => [
        '/api/users/me'          => [UserController::class,    'me'],
        '/api/expenses'          => [ExpenseController::class, 'index'],
        '/api/expenses/{id}'     => [ExpenseController::class, 'show'],
        '/api/reports/summary'   => [ReportController::class,  'summary'],
        '/api/budgets'           => [BudgetController::class,  'index'],
        '/api/exports/csv'       => [ExportController::class,  'csv'],
        '/api/exports/summary'   => [ExportController::class,  'summary'],
    ],
    'PUT'    => [
        '/api/users/me'       => [UserController::class,    'update'],
        '/api/expenses/{id}'  => [ExpenseController::class, 'update'],
        '/api/budgets/{id}'   => [BudgetController::class,  'update'],
    ],
    'DELETE' => [
        '/api/users/me'      => [UserController::class,    'destroy'],
        '/api/expenses/{id}' => [ExpenseController::class, 'destroy'],
    ],
];
