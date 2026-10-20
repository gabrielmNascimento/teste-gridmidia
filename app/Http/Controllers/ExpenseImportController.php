<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportExpensesCsvRequest;
use App\Services\ImportExpensesCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ExpenseImportController extends Controller
{
    public function store(ImportExpensesCsvRequest $request, ImportExpensesCsv $importExpensesCsv): JsonResponse|RedirectResponse
    {
        $result = $importExpensesCsv->handle($request->file('file'));

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['data' => $result]);
        }

        $summary = $result['summary'];

        return redirect()->route('dashboard')
            ->with('status', "Importação concluída: {$summary['imported']} linha(s) importada(s), {$summary['failed']} com erro.")
            ->with('import_results', $result['results']);
    }
}
