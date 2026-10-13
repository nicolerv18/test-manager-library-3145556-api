<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Presenter;
use App\Application\Services\MostBorrowedReportService;
use App\Domain\DomainError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController
{
    public function mostBorrowed(Request $request, MostBorrowedReportService $report): JsonResponse
    {
        $from = Input::queryString($request, 'from');
        $to = Input::queryString($request, 'to');
        $missing = [];
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value === null || trim($value) === '') {
                $missing[] = ['field' => $field, 'issue' => 'es obligatorio'];
            }
        }
        if ($missing !== []) {
            throw DomainError::validation($missing);
        }

        $rows = $report->execute((string) $from, (string) $to);

        return new JsonResponse(['from' => $from, 'to' => $to, 'rows' => array_map([Presenter::class, 'reportRow'], $rows)]);
    }
}
