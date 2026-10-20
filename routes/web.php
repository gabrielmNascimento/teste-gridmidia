<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseImportController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::post('/expenses/import', [ExpenseImportController::class, 'store'])->name('expenses.import');
});
