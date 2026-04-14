<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Repositories\ExpenseRepository;

class ReportController
{
    public function __construct(
        private readonly AuthMiddleware    $auth,
        private readonly ExpenseRepository $expenseRepository,
    ) {}

    public function summary(): void
    {
        $claims = $this->auth->handle();

        $startDate = $_GET['start_date'] ?? date('Y-m-01');       // primeiro dia do mês atual
        $endDate   = $_GET['end_date']   ?? date('Y-m-t');         // último dia do mês atual

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $this->respond(['error' => 'Datas devem estar no formato YYYY-MM-DD.'], 422);
            return;
        }

        $rows  = $this->expenseRepository->getSummaryByCategory((int) $claims['sub'], $startDate, $endDate);
        $total = array_sum(array_column($rows, 'total_amount'));

        $categories = array_map(function ($row) use ($total) {
            return [
                'category'     => $row['category'],
                'total_count'  => (int) $row['total_count'],
                'total_amount' => round((float) $row['total_amount'], 2),
                'avg_amount'   => round((float) $row['avg_amount'], 2),
                'min_amount'   => round((float) $row['min_amount'], 2),
                'max_amount'   => round((float) $row['max_amount'], 2),
                'percentage'   => $total > 0 ? round(((float) $row['total_amount'] / $total) * 100, 2) : 0,
            ];
        }, $rows);

        $this->respond([
            'period'          => ['start' => $startDate, 'end' => $endDate],
            'total_amount'    => round($total, 2),
            'total_expenses'  => array_sum(array_column($rows, 'total_count')),
            'by_category'     => $categories,
        ]);
    }

    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }
}
