<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\ExpenseController;
use App\Controllers\ReportController;

return [
    'POST'   => [
        '/api/auth/register' => [AuthController::class, 'register'],
        '/api/auth/login'    => [AuthController::class, 'login'],
        '/api/expenses'      => [ExpenseController::class, 'store'],
    ],
    'GET'    => [
        '/api/users/me'          => [UserController::class, 'me'],
        '/api/expenses'          => [ExpenseController::class, 'index'],
        '/api/expenses/{id}'     => [ExpenseController::class, 'show'],
        '/api/reports/summary'   => [ReportController::class, 'summary'],
    ],
    'PUT'    => [
        '/api/users/me'      => [UserController::class, 'update'],
        '/api/expenses/{id}' => [ExpenseController::class, 'update'],
    ],
    'DELETE' => [
        '/api/users/me'      => [UserController::class, 'destroy'],
        '/api/expenses/{id}' => [ExpenseController::class, 'destroy'],
    ],
];
