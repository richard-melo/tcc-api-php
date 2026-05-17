<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\ExportService;

class ExportController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly ExportService $exportService,
    ) {
    }

    public function csv(): void
    {
        $claims   = $this->auth->handle();
        $userId   = (int) $claims['sub'];
        $filters  = [
            'category'   => $_GET['category']   ?? null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date'   => $_GET['end_date']   ?? null,
        ];

        $expenses = $this->exportService->getExpenses($userId, $filters);
        $csv      = $this->exportService->toCsv($expenses);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="expenses-' . date('Y-m-d') . '.csv"');
        echo $csv;
    }

    public function summary(): void
    {
        $claims    = $this->auth->handle();
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate   = $_GET['end_date']   ?? date('Y-m-t');

        $summary = $this->exportService->getSummary((int) $claims['sub'], $startDate, $endDate);

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($summary);
    }
}
