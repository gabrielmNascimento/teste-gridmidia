<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseImportController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
Route::post('/units', [UnitController::class, 'store'])->name('units.store');
Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
Route::post('/expenses/import', [ExpenseImportController::class, 'store'])->name('expenses.import');
