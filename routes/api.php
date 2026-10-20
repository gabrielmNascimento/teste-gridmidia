<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseImportController;
use App\Http\Controllers\ExpenseReportController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
    Route::post('/auth/token', [ApiTokenController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('auth.token');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/auth/token', [ApiTokenController::class, 'destroy'])->name('auth.token.destroy');
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/import', [ExpenseImportController::class, 'store'])->name('expenses.import');
        Route::get('/reports/units', [ExpenseReportController::class, 'index'])->name('reports.units');
    });
});
