<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseReportRequest;
use App\Services\UnitExpenseReport;
use Illuminate\Http\JsonResponse;

class ExpenseReportController extends Controller
{
    public function index(ExpenseReportRequest $request, UnitExpenseReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->forPeriod(
                $request->validated('start_date'),
                $request->validated('end_date'),
            ),
        ]);
    }
}
