<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Controllers\AuthController;
use App\Controllers\ExpenseController;
use App\Controllers\ReportController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use App\Repositories\ExpenseRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

// --- Cabeçalhos globais ---
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Injeção de dependências (manual, sem container) ---
$db             = Database::getConnection();
$userRepo       = new UserRepository($db);
$expenseRepo    = new ExpenseRepository($db);
$authService    = new AuthService($userRepo);
$authMiddleware = new AuthMiddleware($authService);

$controllers = [
    'auth'    => new AuthController($authService),
    'user'    => new UserController($authMiddleware, $userRepo),
    'expense' => new ExpenseController($authMiddleware, $expenseRepo),
    'report'  => new ReportController($authMiddleware, $expenseRepo),
];

// --- Roteamento ---
$method = $_SERVER['REQUEST_METHOD'];
$uri    = strtok($_SERVER['REQUEST_URI'], '?');
$uri    = '/' . trim($uri, '/');

$routes = require __DIR__ . '/../config/routes.php';

$matched   = false;
$routeList = $routes[$method] ?? [];

foreach ($routeList as $pattern => [$class, $action]) {
    $regex = preg_replace('/\{(\w+)\}/', '(\d+)', $pattern);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $uri, $matches)) {
        array_shift($matches); // remove full match

        // Encontra a instância do controller correto
        $controller = null;
        foreach ($controllers as $c) {
            if ($c instanceof $class) {
                $controller = $c;
                break;
            }
        }

        if (!$controller) {
            http_response_code(500);
            echo json_encode(['error' => 'Controller não encontrado.']);
            exit;
        }

        try {
            $controller->$action(...array_map('intval', $matches));
        } catch (\App\Http\HttpException $e) {
            http_response_code($e->getCode());
            echo json_encode(['error' => $e->getMessage()]);
        }
        $matched = true;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode(['error' => 'Rota não encontrada.', 'method' => $method, 'uri' => $uri]);
}
