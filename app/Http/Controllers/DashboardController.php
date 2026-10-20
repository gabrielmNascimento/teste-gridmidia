<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseReportRequest;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Unit;
use App\Services\UnitExpenseReport;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ExpenseReportRequest $request, UnitExpenseReport $report): View
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        return view('dashboard', [
            'companies' => Company::query()->orderBy('name')->get(),
            'units' => Unit::query()->with('company')->orderBy('name')->get(),
            'expenses' => Expense::query()
                ->with('allocations.unit')
                ->latest('expense_date')
                ->latest('id')
                ->get(),
            'report' => $report->forPeriod($startDate, $endDate),
            'importResults' => session('import_results', []),
        ]);
    }
}
