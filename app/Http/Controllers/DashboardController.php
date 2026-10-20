<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Expense;
use App\Models\Unit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'companies' => Company::query()->orderBy('name')->get(),
            'units' => Unit::query()->with('company')->orderBy('name')->get(),
            'expenses' => Expense::query()
                ->with('allocations.unit')
                ->latest('expense_date')
                ->latest('id')
                ->get(),
        ]);
    }
}
