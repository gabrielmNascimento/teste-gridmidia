<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\RegisterExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ExpenseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Expense::query()
                ->with('allocations.unit')
                ->latest('expense_date')
                ->paginate(20),
        ]);
    }

    public function store(StoreExpenseRequest $request, RegisterExpense $registerExpense): JsonResponse|RedirectResponse
    {
        $expense = $registerExpense->handle($request->validated());

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['data' => $expense], 201);
        }

        return redirect()->route('dashboard')->with('status', 'Despesa cadastrada com sucesso.');
    }
}
